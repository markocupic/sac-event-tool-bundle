<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Code4Nix\UriSigner\UriSigner;
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
use Contao\DataContainer;
use Contao\Events;
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
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Controller\BackendModule\EventParticipantEmailController;
use Markocupic\SacEventToolBundle\Controller\BackendModule\NotifyEventRegistrationStateController;
use Markocupic\SacEventToolBundle\Csv\EventRegistrationListGeneratorCsv;
use Markocupic\SacEventToolBundle\DocxTemplator\EventRegistrationListGeneratorDocx;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\NotificationType\SubscriptionStateChangeNotificationType;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\EventRegistrationUtil;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;

class CalendarEventsMember
{
    public const TABLE = 'tl_calendar_events_member';

    // Adapters
    private Adapter $calendarEvents;
    private Adapter $calendarEventsUtil;
    private Adapter $calendarEventsMember;
    private Adapter $controller;
    private Adapter $events;
    private Adapter $member;
    private Adapter $message;
    private Adapter $stringUtil;
    private Adapter $validator;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly EventRegistrationListGeneratorCsv $registrationListGeneratorCsv,
        private readonly EventRegistrationListGeneratorDocx $registrationListGeneratorDocx,
        private readonly EventRegistrationUtil $eventRegistrationUtil,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly UrlParser $urlParser,
        private readonly Util $util,
        private readonly UriSigner $uriSigner,
        private readonly NotificationCenter $notificationCenter,
        private readonly RouterInterface $router,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        // Adapters
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsUtil = $this->framework->getAdapter(CalendarEventsUtil::class);
        $this->calendarEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->events = $this->framework->getAdapter(Events::class);
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
            $GLOBALS['TL_JAVASCRIPT'][] = Bundle::ASSET_DIR.'/js/backend_member_autocomplete.js';
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
        $regId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$eventId]);

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
            'downloadEventRegistrationListDocx' => throw new ResponseException($this->registrationListGeneratorDocx->generate($objEvent, 'docx')),
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
            $rowsAffected = $this->connection->executeStatement('DELETE FROM tl_calendar_events_member WHERE id IN('.implode(',', $ids).')', []);

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

    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.stateOfSubscription.save', priority: 100)]
    public function saveCallbackStateOfSubscription($varValue, DataContainer $dc): mixed
    {
        $objEventMemberModel = $this->calendarEventsMember->findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            // Retrieve the event id
            $eventId = !empty($objEventMemberModel->eventId) ? $objEventMemberModel->eventId : $dc->currentPid;
            $objEvent = $this->calendarEvents->findByPk($eventId);

            if (null !== $objEvent && $objEventMemberModel->stateOfSubscription !== $varValue) {
                // Check if member has already booked at the same time
                $objMember = $this->member->findOneBySacMemberId($objEventMemberModel->sacMemberId);

                // Do not allow the maximum number of participants to be exceeded.
                if (EventSubscriptionState::SUBSCRIPTION_ACCEPTED === $varValue) {
                    if (!$this->calendarEventsMember->canAcceptSubscription($objEventMemberModel, $objEvent)) {
                        $varValue = EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST;
                    }
                }

                if (EventSubscriptionState::SUBSCRIPTION_ACCEPTED === $varValue && null !== $objMember && !$objEventMemberModel->allowMultiSignUp && $this->calendarEventsUtil->areBookingDatesOccupied($objEvent, $objMember)) {
                    $this->message->addError('Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht angemeldet werden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde. Wenn Sie das trotzdem erlauben möchten, dann setzen Sie das Flag "Mehrfachbuchung zulassen".');
                    $varValue = $objEventMemberModel->stateOfSubscription;
                } elseif ($this->validator->isEmail($objEventMemberModel->email)) {
                    $notificationId = $this->connection->fetchOne('SELECT id FROM tl_nc_notification WHERE type = :type', ['type' => SubscriptionStateChangeNotificationType::NAME], ['type' => Types::STRING]);

                    if ($notificationId) {
                        $arrTokens = [
                            'participant_state_of_subscription' => html_entity_decode((string) $GLOBALS['TL_LANG']['MSC'][$varValue]),
                            'event_name' => html_entity_decode($objEvent->title),
                            'participant_uuid' => $objEventMemberModel->uuid,
                            'participant_name' => html_entity_decode($objEventMemberModel->firstname.' '.$objEventMemberModel->lastname),
                            'participant_email' => $objEventMemberModel->email,
                            'event_link_detail' => $this->events->generateEventUrl($objEvent, true),
                        ];

                        $this->notificationCenter->sendNotification($notificationId, $arrTokens, $this->sacevtLocale);
                    }
                }
            }
        }

        return $varValue;
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.hasParticipated.save', priority: 100)]
    public function saveCallbackHasParticipated(string $varValue, DataContainer $dc): string
    {
        if ($dc->id) {
            $registration = $this->calendarEventsMember->findByPk($dc->id);

            if (null !== $registration) {
                $event = $this->calendarEvents->findByPk($registration->eventId);

                if (null !== $event) {
                    $sacMemberId = $registration->sacMemberId ?? '0';

                    if ($varValue) {
                        $log = 'Participation state for "%s %s [%s]" on "%s [%s]" has been set from "unconfirmed" to "confirmed".';
                        $context = Log::EVENT_PARTICIPATION_CONFIRM;
                    } else {
                        $log = 'Participation state for "%s %s [%s]" on "%s [%s]" has been set from "confirmed" to "unconfirmed".';
                        $context = Log::EVENT_PARTICIPATION_UNCONFIRM;
                    }

                    // System log
                    $this->contaoGeneralLogger?->info(
                        sprintf($log, $registration->firstname, $registration->lastname, $sacMemberId, $event->title, $event->id),
                        ['contao' => new ContaoContext(__METHOD__, $context)],
                    );
                }
            }
        }

        return $varValue;
    }

    /**
     * Add more data to the registration, when a backend user manually adds a new registration.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.oncreate', priority: 100)]
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.oncopy', priority: 100)]
    public function oncreateCallback(string $strTable, int $insertId, array $arrFields, DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        if (empty($arrFields['dateAdded'])) {
            $set = ['dateAdded' => time()];
            $this->connection->update('tl_calendar_events_member', $set, ['id' => $insertId]);
        }
    }

    /**
     * Add more data to the registration, when user adds a new registration manually.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onsubmit', priority: 100)]
    public function onsubmitCallback(DataContainer $dc): void
    {
        if (!$dc->activeRecord) {
            return;
        }

        $arrReg = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events_member WHERE id = ?', [$dc->id]);

        $set = [
            'dateAdded' => empty($arrReg['dateAdded']) ? time() : $arrReg['dateAdded'],
            'tstamp' => time(),
            'contaoMemberId' => 0,
        ];

        // Set the Contao member id
        if (!empty($arrReg['sacMemberId'])) {
            $id = $this->connection->fetchOne('SELECT id FROM tl_member WHERE sacMemberId = ?', [$arrReg['sacMemberId']]);

            if ($id) {
                $set['contaoMemberId'] = $id;
                $dc->activeRecord->contaoMemberId = $id;
            }
        }

        // Add correct event id and event title
        $arrEvent = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events WHERE id = ?', [$arrReg['eventId']]);

        if ($arrEvent) {
            // Set correct event title and eventId
            $set['eventName'] = $arrEvent['title'];
            $dc->activeRecord->eventName = $arrEvent['title'];

            $set['eventId'] = $arrEvent['id'];
            $dc->activeRecord->eventId = $arrEvent['id'];
        }

        $this->connection->update('tl_calendar_events_member', $set, ['id' => $dc->id]);
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
        $registrationModel = $this->calendarEventsMember->findByPk($row['id']);
        $icon = $this->eventRegistrationUtil->getSubscriptionStateIcon($registrationModel);
        $args[0] = sprintf('<div>%s</div>', $icon);

        return $args;
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.dashboard.input_field', priority: 100)]
    public function parseNotificationButtonDashboard(DataContainer $dc): string
    {
        $registration = $this->calendarEventsMember->findByPk($dc->id);

        if (null !== $registration) {
            $event = $this->calendarEvents->findByPk($registration->eventId);

            if (null !== $event) {
                if ($registration->tstamp && !$this->validator->isEmail($registration->email)) {
                    $this->message->addInfo($this->translator->trans('tl_calendar_events_member.notificationDueToMissingEmailDisabled', [], 'contao_default'));
                }

                if ($registration->hasParticipated) {
                    $this->message->addInfo('Dieser Teilnehmer/diese Teilnehmerin hat am Anlass teilgenommen. Es können deshalb keine Benachrichtigungen versandt werden.');
                }

                if (!$registration->hasParticipated && $this->validator->isEmail($registration->email)) {
                    if ($this->validator->isEmail($registration->email)) {
                        $template = new BackendTemplate('be_calendar_events_registration_dashboard');
                        $template->registration = $registration;
                        $template->state_of_subscription = $registration->stateOfSubscription;

                        $uri = $this->urlParser->addQueryString('key='.NotifyEventRegistrationStateController::PARAM_KEY);

                        $hrefs = [];

                        foreach (NotifyEventRegistrationStateController::ACTIONS as $action) {
                            $hrefs[$action] = $this->urlParser->addQueryString('action='.$action, $uri);
                        }

                        $template->button_hrefs = $hrefs;
                        $template->event = $event->row();
                        $template->show_email_buttons = true;
                        $template->event_is_fully_booked = $this->calendarEventsUtil->eventIsFullyBooked($event);

                        return $template->parse();
                    }
                }
            }
        }

        return '';
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

        $url = System::getContainer()->get('code4nix_uri_signer.uri_signer')->sign(
            System::getContainer()->get('router')->generate(EventParticipantEmailController::class, [
                'event_id' => $request->query->get('id'),
                'rt' => $request->query->get('rt'),
                'sid' => uniqid(),
            ])
        );

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
