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

use Codefog\HasteBundle\UrlParser;
use Contao\Backend;
use Contao\BackendTemplate;
use Contao\BackendUser;
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
use Contao\Image;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use League\Csv\CannotInsertRecord;
use League\Csv\InvalidArgument;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Controller\BackendModule\EventParticipantEmailController;
use Markocupic\SacEventToolBundle\Controller\BackendModule\NotifyEventParticipantController;
use Markocupic\SacEventToolBundle\Csv\EventRegistrationListGeneratorCsv;
use Markocupic\SacEventToolBundle\DocxTemplator\EventRegistrationListGeneratorDocx;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\NotificationType\SubscriptionStateChangeNotificationType;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\EventRegistrationUtil;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;

class CalendarEventsMember
{
    public const TABLE = 'tl_calendar_events_member';

    // Adapters
    private Adapter $backend;
    private Adapter $calendarEvents;
    private Adapter $calendarEventsHelper;
    private Adapter $calendarEventsMember;
    private Adapter $controller;
    private Adapter $events;
    private Adapter $image;
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
        private readonly NotificationCenter $notificationCenter,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        // Adapters
        $this->image = $this->framework->getAdapter(Image::class);
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->events = $this->framework->getAdapter(Events::class);
        $this->member = $this->framework->getAdapter(MemberModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->user = $this->framework->getAdapter(UserModel::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    /**
     * Set the correct referer.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function setCorrectReferer(): void
    {
        $this->util->setCorrectReferer();
    }

    /**
     * Load backend assets.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function loadBackendAssets(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (('calendar' === $request->query->get('do') || 'sac_calendar_events_tool' === $request->query->get('do')) && '' !== $request->query->get('ref')) {
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
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function checkPermission(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        $request = $this->requestStack->getCurrentRequest();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Do not allow non-admins to delete event registrations with the booking type 'onlineForm'!
        if ('delete' === $request->query->get('act') || 'deleteAll' === $request->query->get('act')) {
            $registration = $this->calendarEventsMember->findByPk($dc->id);

            if (null !== $registration && BookingType::ONLINE_FORM === $registration->bookingType) {
                throw new AccessDeniedException('Not enough permissions to '.$request->query->get('act').' the event registration with ID '.$dc->id.'.');
            }
        }

        if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $dc->currentPid)) {
            // Grant write access to the event registration table if the user is member of an allowed group.
            return;
        }

        // Do not show certain buttons if the user is not member of an allowed group.
        if (!$request->query->get('act') && $request->query->get('id')) {
            $objEvent = $this->calendarEvents->findByPk($request->query->get('id'));

            if (null !== $objEvent) {
                $arrAuthors = $this->stringUtil->deserialize($objEvent->author, true);
                $arrRegistrationGoesTo = $this->stringUtil->deserialize($objEvent->registrationGoesTo, true);

                if (!\in_array($user->id, $arrAuthors, false) && !\in_array($user->id, $arrRegistrationGoesTo, true)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['closed'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCopyable'] = true;

                    unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['all'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['downloadEventMemberList'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['edit'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['delete'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['toggleStateOfParticipation']);
                }
            }
        }

        // Grant write-access to the event-author, to event tour guides and registration admins (tl_calendar_events.registrationGoesTo) on the respective event.
        if ('toggle' === $request->query->get('act') || 'edit' === $request->query->get('act') || 'select' === $request->query->get('act')) {
            if ('select' === $request->query->get('act')) {
                $objEvent = $this->calendarEvents->findByPk($dc->id);
            } else {
                $registration = $this->calendarEventsMember->findByPk($dc->id);

                /** @var CalendarEventsMemberModel $objEvent */
                if (null !== $registration) {
                    /** @var CalendarEventsModel $objEvent */
                    $objEvent = $registration->getRelated('eventId');
                }
            }

            if (null !== $objEvent) {
                $arrAuthors = $this->stringUtil->deserialize($objEvent->author, true);
                $arrRegistrationGoesTo = $this->stringUtil->deserialize($objEvent->registrationGoesTo, true);
                $arrInstructor = CalendarEventsHelper::getInstructorsAsArray($objEvent);

                if (!\in_array($user->id, $arrAuthors, false) && !\in_array($user->id, $arrRegistrationGoesTo, true) && !\in_array($user->id, $arrInstructor, true)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['closed'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCopyable'] = true;
                    $this->message->addError($this->translator->trans('ERR.accessDenied', [], 'contao_default'));
                    $this->controller->redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.$objEvent->id);
                }
            }
        }
    }

    /**
     * Download registration list as a DOCX or CSV file. *.
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

        if ($request->query->has('id') && null !== ($objEvent = $this->calendarEvents->findByPk($request->query->get('id')))) {
            // Download the registration list as a docx file
            if ('downloadEventRegistrationListDocx' === $request->query->get('action')) {
                throw new ResponseException($this->registrationListGeneratorDocx->generate($objEvent, 'docx'));
            }

            // Download the registration list as a csv file
            if ('downloadEventRegistrationListCsv' === $request->query->get('action')) {
                throw new ResponseException($this->registrationListGeneratorCsv->generate($objEvent));
            }
        }
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

                if (EventSubscriptionState::SUBSCRIPTION_ACCEPTED === $varValue && null !== $objMember && !$objEventMemberModel->allowMultiSignUp && $this->calendarEventsHelper->areBookingDatesOccupied($objEvent, $objMember)) {
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
        $request = $this->requestStack->getCurrentRequest();

        if ($dc->id && 'hasteAjaxOperation' === $request->request->get('action')) {
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
     * Generate href for $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']
     * Generate href for $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice'].
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function setGlobalOperations(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        // Remove edit_all (mehrere bearbeiten) button
        if (!$user->admin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['all']);
        }

        $blnAllowTourReportButton = false;
        $blnAllowInstructorInvoiceButton = false;

        $eventId = $request->query->get('id');
        $objEvent = $this->calendarEvents->findByPk($eventId);

        if (null !== $objEvent) {
            // Check if backend user is allowed
            if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $objEvent->id) || $objEvent->registrationGoesTo === $user->id) {
                if (EventType::TOUR === $objEvent->eventType || EventType::LAST_MINUTE_TOUR === $objEvent->eventType) {
                    $href = $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']['href'];
                    $href = sprintf($href, $eventId);
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']['href'] = $href;
                    $blnAllowTourReportButton = true;

                    $href = $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']['href'];
                    $href = sprintf($href, $eventId);
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']['href'] = $href;
                    $blnAllowInstructorInvoiceButton = true;
                }
            }
        }

        if (!$blnAllowTourReportButton) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']);
        }

        if (!$blnAllowInstructorInvoiceButton) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']);
        }
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

                        $uri = $this->urlParser->addQueryString('key='.NotifyEventParticipantController::PARAM_KEY);

                        $hrefs = [];

                        foreach (NotifyEventParticipantController::ACTIONS as $action) {
                            $hrefs[$action] = $this->urlParser->addQueryString('action='.$action, $uri);
                        }

                        $template->button_hrefs = $hrefs;
                        $template->event = $event->row();
                        $template->show_email_buttons = true;
                        $template->event_is_fully_booked = $this->calendarEventsHelper->eventIsFullyBooked($event);

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

        $href = sprintf(
            'contao?do=%s&table=tl_calendar_events&id=%d&act=edit&rt=%s&ref=%s',
            $request->query->get('do'),
            $request->query->get('id'),
            $this->contaoCsrfTokenManager->getDefaultTokenValue(),
            $request->attributes->get('_contao_referer_id'),
        );

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

        return sprintf(' <a href="%s" class="%s" title="%s" %s>%s</a>', $this->stringUtil->ampersand($url), $class, $title, $attributes, $label);
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'edit.buttons', priority: 100)]
    public function buttonsCallback(array $arrButtons, DataContainer $dc): array
    {
        unset($arrButtons['saveNback'], $arrButtons['saveNduplicate'], $arrButtons['saveNcreate']);

        return $arrButtons;
    }

    /**
     * Return the delete user button.
     *
     * @param array       $row
     * @param string|null $href
     * @param string      $label
     * @param string      $title
     * @param string|null $icon
     * @param string      $attributes
     *
     * @return string
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.operations.delete.button', priority: 100)]
    public function deleteRegistration(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $allowDeletion = false;

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $allowDeletion = true;
        } elseif (isset($row['bookingType']) && BookingType::ONLINE_FORM !== $row['bookingType']) {
            $allowDeletion = true;
        }

        return $allowDeletion ? '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ' : $this->image->getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }
}
