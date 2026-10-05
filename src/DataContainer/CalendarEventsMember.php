<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Codefog\HasteBundle\UrlParser;
use Contao\BackendTemplate;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\DataContainer;
use Contao\Image;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use League\Csv\CannotInsertRecord;
use League\Csv\InvalidArgument;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Controller\BackendModule\EventParticipantEmailController;
use Markocupic\SacEventToolBundle\Csv\EventRegistrationListGeneratorCsv;
use Markocupic\SacEventToolBundle\DocxTemplator\EventRegistrationListGeneratorDocx;
use Markocupic\SacEventToolBundle\DocxTemplator\OutputType;
use Markocupic\SacEventToolBundle\Event\DataContainer\ContaoPostUpdateEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\NotificationType\SubscriptionStateChangeNotificationType;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Markocupic\SacEventToolBundle\Util\EventRegistrationUtil;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;

/**
 * DCA callbacks and event listeners for the event registrations (tl_calendar_events_member):
 * participation confirmation, subscription state changes and notifications, export of the
 * registration list, defaults of manual registrations and the list view.
 *
 * Access checks are in AccessDecision\CalendarEventsMember.
 */
class CalendarEventsMember
{
    public const string TABLE = 'tl_calendar_events_member';

    private const array EXPORT_ACTIONS = ['downloadEventRegistrationListDocx', 'downloadEventRegistrationListCsv'];

    // Adapters
    private Adapter $calendarEvents;

    private Adapter $calendarEventsMember;

    private Adapter $controller;

    private Adapter $image;

    private Adapter $member;

    private Adapter $message;

    private Adapter $stringUtil;

    private Adapter $validator;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly EventRegistrationListGeneratorCsv $registrationListGeneratorCsv,
        private readonly EventRegistrationListGeneratorDocx $registrationListGeneratorDocx,
        private readonly EventRegistrationUtil $eventRegistrationUtil,
        private readonly NotificationCenter $notificationCenter,
        private readonly Packages $packages,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
        private readonly Util $util,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        // Adapters
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->image = $this->framework->getAdapter(Image::class);
        $this->member = $this->framework->getAdapter(MemberModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    /**
     * Load the backend assets (autocomplete of members).
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function loadBackendAssets(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('calendar' === $request->query->get('do') && $request->query->get('ref')) {
            $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('js/backend_member_autocomplete.js', 'markocupic_sac_event_tool');
        }
    }

    /**
     * Participation can only be confirmed for accepted registrations and registrations on the waiting list.
     * For all other registrations, the toggle icon is greyed out and has no link.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.operations.toggleParticipationState.button', priority: 100)]
    public function disableParticipationToggle(DataContainerOperation $operation): void
    {
        $registration = $operation->getRecord();

        if (\in_array($registration['stateOfSubscription'] ?? '', EventSubscriptionState::PARTICIPATION_CONFIRMATION_ALLOWED, true)) {
            return;
        }

        // Greyed out icon that still shows the current state
        $icon = $registration['hasParticipated'] ? 'icons/fontawesome/square-check--disabled.svg' : 'icons/fontawesome/square-check_--disabled.svg';
        $title = $this->translator->trans('MSC.participationConfirmationNotAllowed', [], 'contao_default');

        $operation->setHtml(
            $this->image->getHtml(
                $this->packages->getUrl($icon, 'markocupic_sac_event_tool'),
                $title,
                'title="'.$this->stringUtil->specialchars($title).'"',
            ),
        );
    }

    /**
     * Server-side guard (e.g. edit form or manipulated toggle URL):
     * hasParticipated cannot be set for registrations that are neither accepted nor on the waiting list.
     * Unsetting is always allowed.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.hasParticipated.save', priority: 100)]
    public function preventInvalidParticipationConfirmation(mixed $value, DataContainer $dc): mixed
    {
        if (!$value) {
            return $value;
        }

        // In the edit form the subscription state may be changed in the same request
        $state = $this->requestStack->getCurrentRequest()?->request->get('stateOfSubscription') ?? $dc->activeRecord?->stateOfSubscription;

        if (!\in_array($state, EventSubscriptionState::PARTICIPATION_CONFIRMATION_ALLOWED, true)) {
            throw new \RuntimeException($this->translator->trans('ERR.participationConfirmationNotAllowed', [], 'contao_default'));
        }

        return $value;
    }

    /**
     * Redirect the user to the NotifyEventRegistrationStateController if a
     * "change subscription state" button has been clicked.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onsubmit', priority: -999999)]
    public function handleChangeSubscriptionStateButtonClicks(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' !== $request->query->get('act') || !$request->request->has('changeSubscriptionStateWithEmail')) {
            return;
        }

        $url = $this->urlParser->addQueryString(\sprintf('key=notify_event_registration_state&action=%s', $request->request->get('changeSubscriptionStateWithEmail')));

        // Remove the old hash before signing the url again
        $url = $this->urlParser->removeQueryString(['_hash'], $url);

        $this->controller->redirect($this->uriSigner->sign($url));
    }

    /**
     * The global operation "send email" is only shown if there are registrations.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function showSendEmailButton(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // List view: $dc->id is the event id
        $eventId = $dc->id;

        if (!$eventId || $request->query->has('act')) {
            return;
        }

        $hasRegistrations = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$eventId], [Types::INTEGER]);

        if (!$hasRegistrations) {
            unset($GLOBALS['TL_DCA'][self::TABLE]['list']['global_operations']['sendEmail']);
        }
    }

    /**
     * Download the registration list as a DOCX or CSV file.
     *
     * @throws CannotInsertRecord
     * @throws Exception
     * @throws InvalidArgument
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function exportMemberList(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $action = $request->query->get('action', '');

        if (!\in_array($action, self::EXPORT_ACTIONS, true)) {
            return;
        }

        $eventId = $request->query->get('id', 0);
        $event = $this->calendarEvents->findById($eventId);

        if (null === $event) {
            throw new \InvalidArgumentException(\sprintf('Could not find event with ID "%s".', $eventId));
        }

        if (!$this->security->isGranted(CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS, $event->id)) {
            throw new AccessDeniedException(\sprintf('Not enough permissions to download the registration list of the event with ID %d.', $event->id));
        }

        match ($action) {
            'downloadEventRegistrationListDocx' => throw new ResponseException($this->registrationListGeneratorDocx->generate($event, OutputType::DOCX)),
            'downloadEventRegistrationListCsv' => throw new ResponseException($this->registrationListGeneratorCsv->generate($event)),
        };
    }

    /**
     * List the SAC sections.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.sectionId.options', priority: 100)]
    public function listSections(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT sectionId, name FROM tl_sac_section');
    }

    /**
     * All subscription states except "undefined". Non-admins cannot switch back to
     * "not confirmed".
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.stateOfSubscription.options', priority: 100)]
    public function listEventSubscriptionStates(DataContainer $dc): array
    {
        $states = array_values(array_diff(EventSubscriptionState::ALL, [EventSubscriptionState::SUBSCRIPTION_STATE_UNDEFINED]));

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $states;
        }

        $currentState = $this->connection->fetchOne('SELECT stateOfSubscription FROM tl_calendar_events_member WHERE id = ?', [$dc->id]);

        if (EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED !== $currentState) {
            $states = array_values(array_diff($states, [EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED]));
        }

        return $states;
    }

    /**
     * A registration can only be accepted if the event is not fully booked and the
     * member has not been accepted for another event at the same time. Otherwise, it
     * is put on the waiting list.
     *
     * Priority 110: runs before self::onBeforeSubmitCallback() (priority 100).
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onbeforesubmit', priority: 110)]
    public function checkStateOfSubscriptionChange(array $updatedFields, DataContainer $dc): array
    {
        $registration = $this->calendarEventsMember->findById($dc->id);

        if (null === $registration) {
            return $updatedFields;
        }

        // Temporarily apply the changes to the registration model
        $registration->mergeRow($updatedFields);

        $event = $this->calendarEvents->findById($registration->eventId);

        if (null === $event) {
            throw new \Exception(\sprintf('The event ID %d that is associated with the registration does not exist.', $registration->eventId));
        }

        if (EventSubscriptionState::SUBSCRIPTION_ACCEPTED !== $registration->stateOfSubscription) {
            return $updatedFields;
        }

        // The maximum number of participants must not be exceeded
        if (!$this->calendarEventsMember->canAcceptSubscription($registration, $event)) {
            $updatedFields['stateOfSubscription'] = EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST;

            $this->message->addInfo($this->translator->trans('MSC.participantHasBeenAddedToTheWaitingList', [$registration->firstname, $registration->lastname], 'contao_default'));

            return $updatedFields;
        }

        // The member must not have been accepted for another event at the same time
        $member = $this->member->findOneBySacMemberId($registration->sacMemberId);

        if (null !== $member && !$registration->allowMultiSignUp && $this->calendarEventsUtil->areBookingDatesOccupied($event, $member)) {
            $updatedFields['stateOfSubscription'] = EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST;

            $this->message->addError($this->translator->trans('MSC.participantHasBeenNotifiedCannotBeRegisteredBecauseHeHasBeenConfirmedAtAnotherEvent', [], 'contao_default'));
            $this->message->addInfo($this->translator->trans('MSC.participantHasBeenAddedToTheWaitingList', [$registration->firstname, $registration->lastname], 'contao_default'));
        }

        return $updatedFields;
    }

    /**
     * Notify the member if the subscription state has been changed manually.
     */
    #[AsEventListener]
    public function notifyMemberOnParticipationStateUpdate(ContaoPostUpdateEvent $event): void
    {
        if (self::TABLE !== $event->getTableName() || !isset($event->getDiffData()['stateOfSubscription'])) {
            return;
        }

        $registration = $event->getPostUpdateRecord();

        $calendarEvent = $this->calendarEvents->findById($registration['eventId']);

        // The registration has already been saved, so do not throw an exception.
        if (null === $calendarEvent) {
            $this->logFailedNotification($registration, new \RuntimeException(\sprintf('The event with ID %d does not exist.', $registration['eventId'])));
            $this->message->addError($this->translator->trans('ERR.participantCouldNotBeNotifiedAboutTheRegistrationStatusChange', [$registration['firstname'], $registration['lastname']], 'contao_default'));

            return;
        }

        if (!$this->validator->isEmail($registration['email'])) {
            if ($this->scopeMatcher->isBackendRequest($this->requestStack->getCurrentRequest())) {
                $stateOfSubscription = $this->translator->trans('MSC.'.$registration['stateOfSubscription'], [], 'contao_default');
                $this->message->addInfo($this->translator->trans('tl_calendar_events_member.bookingStateHasBeenChangedButParticipantWasNotNotifiedDueToMissingEmail', [$stateOfSubscription], 'contao_default'));
            }

            return;
        }

        $notificationIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_nc_notification WHERE type = ?', [SubscriptionStateChangeNotificationType::NAME], [Types::STRING]);

        if (empty($notificationIds)) {
            $this->message->addInfo($this->translator->trans('MSC.participantNotNotifiedBecauseNoNotificationIsConfigured', [$registration['firstname'], $registration['lastname']], 'contao_default'));

            return;
        }

        $deliveredCount = 0;
        $failedCount = 0;

        // The registration has already been saved. If the notification fails, the user
        // gets an error message instead of an error page.
        try {
            $tokens = [
                'participant_state_of_subscription' => $this->stringUtil->revertInputEncoding($this->translator->trans('MSC.'.$registration['stateOfSubscription'], [], 'contao_default')),
                'event_title' => $this->stringUtil->revertInputEncoding($calendarEvent->title),
                'participant_uuid' => $registration['uuid'],
                'participant_name' => $this->stringUtil->revertInputEncoding($registration['firstname'].' '.$registration['lastname']),
                'participant_email' => $registration['email'],
                'event_link_detail' => $this->contentUrlGenerator->generate($calendarEvent, [], UrlGeneratorInterface::ABSOLUTE_URL),
            ];

            foreach ($notificationIds as $notificationId) {
                foreach ($this->notificationCenter->sendNotification((int) $notificationId, $tokens, $this->sacevtLocale) as $receipt) {
                    if ($receipt->wasDelivered()) {
                        ++$deliveredCount;
                    } else {
                        ++$failedCount;
                        $this->logFailedNotification($registration, $receipt->getException());
                    }
                }
            }
        } catch (\Throwable $e) {
            ++$failedCount;
            $this->logFailedNotification($registration, $e);
        }

        if ($deliveredCount > 0) {
            $this->message->addInfo($this->translator->trans('MSC.participantHasBeenNotifiedAboutTheRegistrationStatusChange', [$registration['firstname'], $registration['lastname']], 'contao_default'));
        }

        if ($failedCount > 0) {
            $this->message->addError($this->translator->trans('ERR.participantCouldNotBeNotifiedAboutTheRegistrationStatusChange', [$registration['firstname'], $registration['lastname']], 'contao_default'));
        }
    }

    /**
     * Log the confirmation and the removal of the participation in the Contao system log.
     *
     * @throws \Exception
     */
    #[AsEventListener]
    public function writeParticipationStateChangeToContaoSystemLog(ContaoPostUpdateEvent $event): void
    {
        $diff = $event->getDiffData();

        if (self::TABLE !== $event->getTableName() || !isset($diff['hasParticipated'])) {
            return;
        }

        $registration = $this->calendarEventsMember->findById($event->getRecordId());

        if (null === $registration) {
            throw new \Exception(\sprintf('Registration with ID %d not found.', $event->getRecordId()));
        }

        $calendarEvent = $this->calendarEvents->findById($registration->eventId);

        if (null === $calendarEvent) {
            throw new \Exception(\sprintf('The event ID %d that is associated with the registration with ID %d does not exist.', $registration->eventId, $registration->id));
        }

        if ((bool) $diff['hasParticipated']) {
            $logText = 'Participation state for "%s %s [%s]" on "%s [%s]" has been set from "unconfirmed" to "confirmed".';
            $context = Log::EVENT_PARTICIPATION_CONFIRM;
        } else {
            $logText = 'Participation state for "%s %s [%s]" on "%s [%s]" has been set from "confirmed" to "unconfirmed".';
            $context = Log::EVENT_PARTICIPATION_UNCONFIRM;
        }

        $this->contaoGeneralLogger?->info(
            \sprintf($logText, $registration->firstname, $registration->lastname, $registration->sacMemberId ?? '0', $calendarEvent->title, $calendarEvent->id),
            ['contao' => new ContaoContext(__METHOD__, $context)],
        );
    }

    /**
     * Add the event id, uuid and the date added timestamp to the record, if a backend
     * user manually adds a new registration.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.oncreate', priority: 100)]
    public function oncreateCallback(string $strTable, int $insertId, array $arrFields, DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $set = [
            'uuid' => Uuid::uuid4()->toString(),
            'eventId' => $this->requestStack->getCurrentRequest()->query->get('id'),
            'dateAdded' => time(),
        ];

        $this->connection->update(self::TABLE, $set, ['id' => $insertId]);
    }

    /**
     * Keep the Contao member id and the event title of the registration up to date.
     * Runs after self::checkStateOfSubscriptionChange() (priority 110).
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onbeforesubmit', priority: 100)]
    public function onBeforeSubmitCallback(array $arrData, DataContainer $dc): array
    {
        if (!$dc->activeRecord) {
            return $arrData;
        }

        // $arrData only contains the values that have been changed
        $registration = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events_member WHERE id = ?', [$dc->activeRecord->id]);

        if (false === $registration) {
            return $arrData;
        }

        $sacMemberId = $arrData['sacMemberId'] ?? $registration['sacMemberId'];

        // Contao member id, if there is a member with this SAC member id
        $contaoMemberId = 0;

        if (!empty($sacMemberId)) {
            $contaoMemberId = $this->connection->fetchOne('SELECT id FROM tl_member WHERE sacMemberId = ?', [(int) $sacMemberId], [Types::INTEGER]);
        }

        $set = ['contaoMemberId' => (int) $contaoMemberId];
        $dc->activeRecord->contaoMemberId = (int) $contaoMemberId;

        // Event title
        $event = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events WHERE id = ?', [$dc->activeRecord->eventId], [Types::INTEGER]);

        if ($event) {
            $set['eventName'] = $event['title'];
            $arrData['eventName'] = $event['title'];
            $dc->activeRecord->eventName = $event['title'];
        }

        $this->connection->update(self::TABLE, $set, ['id' => $dc->id]);

        return $arrData;
    }

    /**
     * Display the section name instead of the section id 4250,4252 becomes SAC
     * PILATUS, SAC PILATUS NAPF.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onshow', priority: 100)]
    public function decryptSectionIds(array $data, array $row, DataContainer $dc): array
    {
        return $this->util->decryptSectionIds($data, $row, $dc, self::TABLE);
    }

    /**
     * Add the age group to the info list. See:
     * https://github.com/jonasmueller1/sac-pilatus-website/issues/202
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onshow', priority: 100)]
    public function addAgeGroup(array $data, array $row, DataContainer $dc): array
    {
        $registration = $this->calendarEventsMember->findById($row['id']);

        if (null === $registration || !isset($data[self::TABLE][0])) {
            return $data;
        }

        $ageGroup = $this->eventRegistrationUtil->getAgeGroup($registration);
        $data[self::TABLE][0]['J+S/Jugend'] = '' === $ageGroup ? '-' : $ageGroup;

        return $data;
    }

    /**
     * Add the subscription state icon and the age group to each record.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.label.label', priority: 100)]
    public function addIcon(array $row, string $label, DataContainer $dc, array $args): array
    {
        $registration = $this->calendarEventsMember->findById($row['id']);

        $args[0] = \sprintf('<div>%s</div>', $this->eventRegistrationUtil->getSubscriptionStateIcon($registration));

        $index = array_search('J+S/Jugend', $GLOBALS['TL_DCA'][self::TABLE]['list']['label']['fields'], true);

        if (false === $index) {
            throw new \Exception('The entry "J+S/Jugend" does not exist in the tl_calendar_events_member.list.label.fields (DCA).');
        }

        $args[$index] = $this->eventRegistrationUtil->getAgeGroup($registration);

        return $args;
    }

    /**
     * Buttons to change the subscription state and notify the participant.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.dashboard.input_field', priority: 100)]
    public function parseNotificationButtonDashboard(DataContainer $dc): string
    {
        $registration = $this->calendarEventsMember->findById($dc->id);

        if (null === $registration) {
            return '';
        }

        $event = $this->calendarEvents->findById($registration->eventId);

        if (null === $event) {
            return '';
        }

        $hasEmail = $this->validator->isEmail($registration->email);

        if ($registration->tstamp && !$hasEmail) {
            $this->message->addInfo($this->translator->trans('tl_calendar_events_member.notificationDueToMissingEmailDisabled', [], 'contao_default'));
        }

        if ($registration->hasParticipated) {
            $this->message->addInfo($this->translator->trans('MSC.participantHasParticipatedNoNotifications', [], 'contao_default'));

            return '';
        }

        if (!$hasEmail) {
            return '';
        }

        $template = new BackendTemplate('be_calendar_events_registration_dashboard');
        $template->registration = $registration;
        $template->state_of_subscription = $registration->stateOfSubscription;
        $template->event = $event->row();
        $template->show_email_buttons = true;
        $template->event_is_fully_booked = $this->calendarEventsUtil->eventIsFullyBooked($event);

        return $template->parse();
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.global_operations.backToEventSettings.button', priority: 100)]
    public function showBackToEventSettingsButton(string|null $href, string $label, string $title, string $class, string $attributes, string $table): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $href = $this->router->generate('contao_backend', [
            'do' => 'calendar',
            'table' => 'tl_calendar_events',
            'id' => $request->query->get('id'),
            'act' => 'edit',
            'rt' => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
            'ref' => $request->attributes->get('_contao_referer_id'),
        ]);

        return $this->renderGlobalOperation($href, $label, $title, $class, $attributes);
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.global_operations.sendEmail.button', priority: 100)]
    public function generateSendEmailButton(string|null $href, string $label, string $title, string $class, string $attributes, string $table): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $href = $this->router->generate(EventParticipantEmailController::class);
        $href = $this->urlParser->addQueryString('eventId='.$request->query->get('id'), $href);
        $href = $this->urlParser->addQueryString('rt='.$this->contaoCsrfTokenManager->getDefaultTokenValue(), $href);
        $href = $this->urlParser->addQueryString('sid='.uniqid(), $href);
        $href = $this->uriSigner->sign($href);

        return $this->renderGlobalOperation($href, $label, $title, $class, $attributes);
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'edit.buttons', priority: 100)]
    public function buttonsCallback(array $arrButtons, DataContainer $dc): array
    {
        unset($arrButtons['saveNback'], $arrButtons['saveNduplicate'], $arrButtons['saveNcreate']);

        return $arrButtons;
    }

    private function renderGlobalOperation(string $href, string $label, string $title, string $class, string $attributes): string
    {
        $href = $this->stringUtil->ampersand($href);

        return \sprintf(' <a href="%s" class="%s" title="%s" %s>%s</a>', $this->stringUtil->specialcharsUrl($href), $this->stringUtil->specialchars($class), $this->stringUtil->specialchars($title), $attributes, $label);
    }

    private function logFailedNotification(array $registration, \Throwable|null $exception): void
    {
        // The gateway exception (e.g. of the mailer) is wrapped by the notification center
        $reason = $exception?->getPrevious()?->getMessage() ?? $exception?->getMessage() ?? 'unknown reason';

        $this->contaoGeneralLogger?->error(
            \sprintf('Could not notify "%s %s" (registration ID %d) about the changed registration state: %s', $registration['firstname'], $registration['lastname'], $registration['id'], $reason),
            ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)],
        );
    }
}
