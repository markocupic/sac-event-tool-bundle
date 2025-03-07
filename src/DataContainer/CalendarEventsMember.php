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
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\DataContainer;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
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
 * Represents the Calendar Events Member handling component with various data manipulation functionalities.
 */
class CalendarEventsMember
{
    public const string TABLE = 'tl_calendar_events_member';

    // Adapters
    private Adapter $calendarEvents;
    private Adapter $calendarEventsMember;
    private Adapter $controller;
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
        $this->member = $this->framework->getAdapter(MemberModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    /**
     * Load backend assets.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function loadBackendAssets(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('calendar' === $request->query->get('do') && '' !== $request->query->get('ref')) {
            $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('js/backend_member_autocomplete.js', 'markocupic_sac_event_tool');
        }
    }

    /**
     * This will redirect the user to the NotifyEventRegistrationStateController
     * if a change subscription state button has been clicked.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onsubmit', priority: -999999)]
    public function handleChangeSubscriptionStateButtonClicks(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' !== $request->query->get('act')) {
            return;
        }

        if ($request->request->has('changeSubscriptionStateWithEmail')) {
            $strQuery = sprintf('key=notify_event_registration_state&action=%s', $request->request->get('changeSubscriptionStateWithEmail'));

            $url = $this->urlParser->addQueryString($strQuery);

            // Remove the old hash before append the new one to the uri.
            $url = $this->urlParser->removeQueryString(['_hash'], $url);

            // Redirect the user to the NotifyEventRegistrationStateController.
            $this->controller->redirect($this->uriSigner->sign($url));
        }
    }

    /**
     * Show or hide the "send email" button in the global operations section.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function showSendEmailButton(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $eventId = $dc->id;

        if (!$eventId || $request->query->has('act')) {
            return;
        }

        // Do only show email buttons in the global operation's section if there are registrations
        $regId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$eventId], [Types::INTEGER]);

        if (!$regId) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']);
        }
    }

    /**
     * Download registration list as a DOCX or CSV file.
     *
     * @param DataContainer $dc
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
        $supported = ['downloadEventRegistrationListDocx', 'downloadEventRegistrationListCsv'];

        if (!\in_array($action, $supported, true)) {
            return;
        }

        $eventId = $request->query->get('id', 0);
        $objEvent = $this->calendarEvents->findByPk($eventId);

        if (null === $objEvent) {
            throw new \InvalidArgumentException(sprintf('Could not find event with ID "%s".', $eventId));
        }

        if (!$this->security->isGranted(CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS, $objEvent->id)) {
            throw new AccessDeniedException('');
        }

        match ($action) {
            // Download the registration list as a docx file
            'downloadEventRegistrationListDocx' => throw new ResponseException($this->registrationListGeneratorDocx->generate($objEvent, OutputType::DOCX)),
            // Download the registration list as a csv file
            'downloadEventRegistrationListCsv' => throw new ResponseException($this->registrationListGeneratorCsv->generate($objEvent)),
        };
    }

    /**
     * Delete orphaned records.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function reviseTable(): void
    {
        $reload = false;

        // Delete orphaned records
        $ids = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_events_member AS em WHERE em.sacMemberId > ? AND em.tstamp > ? AND NOT EXISTS (SELECT * FROM tl_member AS m WHERE em.sacMemberId = m.sacMemberId)', [0, 0]);

        if (!empty($ids)) {
            $rowsAffected = $this->connection->executeStatement('DELETE FROM tl_calendar_events_member WHERE id IN('.implode(',', $ids).')');

            if ($rowsAffected) {
                $reload = true;
            }
        }

        // Delete event members without sacMemberId that are not related to an event
        $ids = $this->connection
            ->fetchFirstColumn(
                'SELECT id FROM tl_calendar_events_member AS m WHERE (m.sacMemberId < ? OR m.sacMemberId = ?) AND tstamp > ? AND NOT EXISTS (SELECT * FROM tl_calendar_events AS e WHERE m.eventId = e.id)',
                [1, '', 0]
            )
        ;

        if (!empty($ids)) {
            $rowsAffected = $this->connection->executeStatement('DELETE FROM tl_calendar_events_member WHERE id IN('.implode(',', $ids).')', []);

            if ($rowsAffected) {
                $reload = true;
            }
        }

        if ($reload) {
            $this->controller->reload();
        }
    }

    /**
     * List SAC sections.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.sectionId.options', priority: 100)]
    public function listSections(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT sectionId, name FROM tl_sac_section');
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.stateOfSubscription.options', priority: 100)]
    public function listEventSubscriptionStates(DataContainer $dc): array
    {
        $stateOfSubscription = $this->connection->fetchOne('SELECT stateOfSubscription FROM tl_calendar_events_member WHERE id = ?', [$dc->id]);
        $arrEventSubscriptionStates = EventSubscriptionState::ALL;

        // Do not allow the undefined event subscription state
        $arrEventSubscriptionStates = array_values(array_diff($arrEventSubscriptionStates, [EventSubscriptionState::SUBSCRIPTION_STATE_UNDEFINED]));

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $arrEventSubscriptionStates;
        }

        // Do not allow switching back to the initial state to non-admins
        if (EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED !== $stateOfSubscription) {
            $arrEventSubscriptionStates = array_values(array_diff($arrEventSubscriptionStates, [EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED]));
        }

        return array_values($arrEventSubscriptionStates);
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onbeforesubmit', priority: 100)]
    public function checkStateOfSubscriptionChange($updatedFields, DataContainer $dc): mixed
    {
        $objReg = $this->calendarEventsMember->findByPk($dc->id);

        if (null === $objReg) {
            return $updatedFields;
        }

        // Temporary apply changes on the registration model
        $objReg->mergeRow($updatedFields);

        $objEvent = $this->calendarEvents->findByPk($objReg->eventId);

        if (null === $objEvent) {
            throw new \Exception(sprintf('The event ID %d that is associated with the registration does not exist.', $objReg->eventId));
        }

        // Do not allow the maximum number of participants to be exceeded.
        if (EventSubscriptionState::SUBSCRIPTION_ACCEPTED === $objReg->stateOfSubscription) {
            if (!$this->calendarEventsMember->canAcceptSubscription($objReg, $objEvent)) {
                $updatedFields['stateOfSubscription'] = EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST;

                // Show a message in the backend
                $msg = $this->translator->trans('MSC.participantHasBeenAddedToTheWaitingList', [$objReg->firstname, $objReg->lastname], 'contao_default');
                $this->message->addInfo($msg);

                return $updatedFields;
            }

            // Check if member has already booked at the same time
            $objMember = $this->member->findOneBySacMemberId($objReg->sacMemberId);

            if (null !== $objMember && !$objReg->allowMultiSignUp && $this->calendarEventsUtil->areBookingDatesOccupied($objEvent, $objMember)) {
                $updatedFields['stateOfSubscription'] = EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST;

                // Show messages in the backend
                $msg = $this->translator->trans('MSC.participantHasBeenNotifiedCannotBeRegisteredBecauseHeHasBeenConfirmedAtAnotherEvent', [], 'contao_default');
                $this->message->addError($msg);
                $msg = $this->translator->trans('MSC.participantHasBeenAddedToTheWaitingList', [$objReg->firstname, $objReg->lastname], 'contao_default');
                $this->message->addInfo($msg);
            }
        }

        return $updatedFields;
    }

    /**
     * Notify the member if the event subscription state was changed manually.
     */
    #[AsEventListener]
    public function notifyMemberOnParticipationStateUpdate(ContaoPostUpdateEvent $event): void
    {
        $arrDiff = $event->getDiffData();

        if ('tl_calendar_events_member' !== $event->getTableName()) {
            return;
        }

        if (!isset($arrDiff['stateOfSubscription'])) {
            return;
        }

        $arrReg = $event->getPostUpdateRecord();

        $objEvent = $this->calendarEvents->findByPk($arrReg['eventId']);

        if (null === $objEvent) {
            throw new \Exception(sprintf('The event ID %d that is associated with the registration does not exist.', $arrReg['id']));
        }

        if (!$this->validator->isEmail($arrReg['email'])) {
            if ($this->scopeMatcher->isBackendRequest($this->requestStack->getCurrentRequest())) {
                $stateOfSubscription = $this->translator->trans('MSC.'.$arrReg['stateOfSubscription'], [], 'contao_default');
                $message = $this->translator->trans('tl_calendar_events_member.bookingStateHasBeenChangedButParticipantWasNotNotifiedDueToMissingEmail', [$stateOfSubscription], 'contao_default');
                $this->message->addInfo($message);
            }

            return;
        }

        $notificationIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_nc_notification WHERE type = ?', [SubscriptionStateChangeNotificationType::NAME], [Types::STRING]);

        if (empty($notificationIds)) {
            return;
        }

        $arrTokens = [
            'participant_state_of_subscription' => $this->stringUtil->revertInputEncoding((string) $GLOBALS['TL_LANG']['MSC'][$arrReg['stateOfSubscription']]),
            'event_name' => $this->stringUtil->revertInputEncoding($objEvent->title),
            'participant_uuid' => $arrReg['uuid'],
            'participant_name' => $this->stringUtil->revertInputEncoding($arrReg['firstname'].' '.$arrReg['lastname']),
            'participant_email' => $arrReg['email'],
            'event_link_detail' => $this->contentUrlGenerator->generate($objEvent, [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        $messageCount = 0;

        foreach ($notificationIds as $notificationId) {
            $receiptCollection = $this->notificationCenter->sendNotification($notificationId, $arrTokens, $this->sacevtLocale);

            if ($receiptCollection->count()) {
                $messageCount += $receiptCollection->count();
            }
        }

        if ($messageCount) {
            $msg = $this->translator->trans('MSC.participantHasBeenNotifiedAboutTheRegistrationStatusChange', [$arrReg['firstname'], $arrReg['lastname']], 'contao_default');
            $this->message->addInfo($msg);
        }
    }

    #[AsEventListener]
    public function writeParticipationStateChangeToContaoSystemLog(ContaoPostUpdateEvent $event): void
    {
        $arrDiff = $event->getDiffData();

        if ('tl_calendar_events_member' !== $event->getTableName()) {
            return;
        }

        if (!isset($arrDiff['hasParticipated'])) {
            return;
        }

        $objReg = $this->calendarEventsMember->findByPk($event->getRecordId());

        if (null === $objReg) {
            throw new \Exception(sprintf('Registration with ID %d not found.', $event->getRecordId()));
        }

        $objEvent = $this->calendarEvents->findByPk($objReg->eventId);

        if (null === $objEvent) {
            throw new \Exception(sprintf('The event ID %d that is associated with the registration does not exist.', $objReg->id));
        }

        if (true === (bool) $arrDiff['hasParticipated']) {
            $logText = 'Participation state for "%s %s [%s]" on "%s [%s]" has been set from "unconfirmed" to "confirmed".';
            $context = Log::EVENT_PARTICIPATION_CONFIRM;
        } else {
            $logText = 'Participation state for "%s %s [%s]" on "%s [%s]" has been set from "confirmed" to "unconfirmed".';
            $context = Log::EVENT_PARTICIPATION_UNCONFIRM;
        }

        $sacMemberId = $objReg->sacMemberId ?? '0';

        $this->contaoGeneralLogger?->info(
            sprintf($logText, $objReg->firstname, $objReg->lastname, $sacMemberId, $objEvent->title, $objEvent->id),
            ['contao' => new ContaoContext(__METHOD__, $context)],
        );
    }

    /**
     * Add the event id, uuid and the date added timestamp to the record,
     * if a backend user manually adds a new registration.
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

        $this->connection->update('tl_calendar_events_member', $set, ['id' => $insertId]);
    }

    /**
     * Add more data to the registration,
     * if the user manually adds a new registration.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onbeforesubmit', priority: 100)]
    public function onBeforeSubmitCallback(array $arrData, DataContainer $dc): array
    {
        if (!$dc->activeRecord) {
            return $arrData;
        }

        $set = [
            'contaoMemberId' => 0,
        ];

        // $arrData will only contain values that have been changed.
        $arrReg = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events_member WHERE id = ?', [$dc->activeRecord->id]);

        $sacMemberId = $arrData['sacMemberId'] ?? $arrReg['sacMemberId'];

        // Set the Contao member id, if it has one.
        $id = 0;

        if (!empty($sacMemberId)) {
            $id = $this->connection->fetchOne(
                'SELECT id FROM tl_member WHERE sacMemberId = ?',
                [
                    (int) $sacMemberId,
                ],
                [
                    Types::INTEGER,
                ],
            );
        }

        $set['contaoMemberId'] = (int) $id;
        $dc->activeRecord->contaoMemberId = (int) $id;

        // Add correct event id and event title
        $arrEvent = $this->connection->fetchAssociative(
            'SELECT * FROM tl_calendar_events WHERE id = ?',
            [
                $dc->activeRecord->eventId,
            ],
            [
                Types::INTEGER,
            ],
        );

        if ($arrEvent) {
            // Set correct event title and eventId
            $set['eventName'] = $arrEvent['title'];
            $arrData['eventName'] = $arrEvent['title'];
            $dc->activeRecord->eventName = $arrEvent['title'];
        }

        $this->connection->update('tl_calendar_events_member', $set, ['id' => $dc->id]);

        return $arrData;
    }

    /**
     * Display the section name instead of the section id
     * 4250,4252 becomes SAC PILATUS, SAC PILATUS NAPF.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onshow', priority: 100)]
    public function decryptSectionIds(array $data, array $row, DataContainer $dc): array
    {
        return $this->util->decryptSectionIds($data, $row, $dc, self::TABLE);
    }

    /**
     * Add an icon to each record.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.label.label', priority: 100)]
    public function addIcon(array $row, string $label, DataContainer $dc, array $args): array
    {
        $objReg = $this->calendarEventsMember->findByPk($row['id']);
        $icon = $this->eventRegistrationUtil->getSubscriptionStateIcon($objReg);
        $args[0] = sprintf('<div>%s</div>', $icon);

        return $args;
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.dashboard.input_field', priority: 100)]
    public function parseNotificationButtonDashboard(DataContainer $dc): string
    {
        $objReg = $this->calendarEventsMember->findByPk($dc->id);

        if (null === $objReg) {
            return '';
        }

        $objEvent = $this->calendarEvents->findByPk($objReg->eventId);

        if (null === $objEvent) {
            return '';
        }

        if ($objReg->tstamp && !$this->validator->isEmail($objReg->email)) {
            $this->message->addInfo($this->translator->trans('tl_calendar_events_member.notificationDueToMissingEmailDisabled', [], 'contao_default'));
        }

        if ($objReg->hasParticipated) {
            $this->message->addInfo('Dieser Teilnehmer/diese Teilnehmerin hat am Anlass teilgenommen. Es können deshalb keine Benachrichtigungen versandt werden.');

            return '';
        }

        if (!$this->validator->isEmail($objReg->email)) {
            return '';
        }

        $template = new BackendTemplate('be_calendar_events_registration_dashboard');
        $template->registration = $objReg;
        $template->state_of_subscription = $objReg->stateOfSubscription;
        $template->event = $objEvent->row();
        $template->show_email_buttons = true;
        $template->event_is_fully_booked = $this->calendarEventsUtil->eventIsFullyBooked($objEvent);

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

        return sprintf(' <a href="%s" class="%s" title="%s" %s>%s</a>', $this->stringUtil->ampersand($href), $class, $title, $attributes, $label);
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.global_operations.sendEmail.button', priority: 100)]
    public function generateSendEmailButton(string|null $href, string $label, string $title, string $class, string $attributes, string $table): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $url = System::getContainer()->get('router')->generate(EventParticipantEmailController::class);
        $url = $this->urlParser->addQueryString('eventId='.$request->query->get('id'), $url);
        $url = $this->urlParser->addQueryString('rt='.$this->contaoCsrfTokenManager->getDefaultTokenValue(), $url);
        $url = $this->urlParser->addQueryString('sid='.uniqid(), $url);
        $url = $this->uriSigner->sign($url);

        return sprintf(' <a href="%s" class="%s" title="%s" %s>%s</a>', $this->stringUtil->ampersand($url), $class, $title, $attributes, $label);
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'edit.buttons', priority: 100)]
    public function buttonsCallback(array $arrButtons, DataContainer $dc): array
    {
        unset($arrButtons['saveNback'], $arrButtons['saveNduplicate'], $arrButtons['saveNcreate']);

        return $arrButtons;
    }
}
