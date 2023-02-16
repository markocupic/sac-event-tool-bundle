<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\DataContainer;
use Contao\Email;
use Contao\Environment;
use Contao\Events;
use Contao\FilesModel;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Haste\Form\Form;
use League\Csv\CannotInsertRecord;
use League\Csv\InvalidArgument;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Csv\ExportEventRegistrationList;
use Markocupic\SacEventToolBundle\DocxTemplator\EventMemberList2Docx;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use NotificationCenter\Model\Notification;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarEventsMember
{
    public const TABLE = 'tl_calendar_events_member';

    // Adapters
    private Adapter $backend;
    private Adapter $calendarEvents;
    private Adapter $calendarEventsHelper;
    private Adapter $calendarEventsMember;
    private Adapter $controller;
    private Adapter $environment;
    private Adapter $events;
    private Adapter $files;
    private Adapter $member;
    private Adapter $message;
    private Adapter $notification;
    private Adapter $stringUtil;
    private Adapter $user;
    private Adapter $validator;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
        private readonly Util $util,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly ExportEventRegistrationList $registrationListExporterCsv,
        private readonly EventMemberList2Docx $registrationListExporterDocx,
        private readonly string $projectDir,
        private readonly string $sacevtEventAdminName,
        private readonly string $sacevtEventAdminEmail,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        // Adapters
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->events = $this->framework->getAdapter(Events::class);
        $this->files = $this->framework->getAdapter(FilesModel::class);
        $this->member = $this->framework->getAdapter(MemberModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->notification = $this->framework->getAdapter(Notification::class);
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

        if ('sac_calendar_events_tool' === $request->query->get('do') && '' !== $request->query->get('ref')) {
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

        // Do only show email buttons in the global operation's section there are registrations
        $regId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$eventId]);

        if (!$regId) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']);
        } else {
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']['href'] = str_replace('sendEmail', 'sendEmail&id='.$regId.'&eventId='.$eventId, $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']['href']);
        }
    }

    /**
     * Send emails to event members.
     *
     * @throws \Doctrine\Dbal\Exception
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function sendEmailAction(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $user = $this->security->getUser();

        if ('sendEmail' !== $request->query->get('action')) {
            return;
        }

        $eventId = $request->query->get('eventId');

        if (null === ($objEvent = $this->calendarEvents->findByPk($eventId))) {
            return;
        }

        // Reset email fields
        $set = [
            'emailRecipients' => '',
            'emailSubject' => '',
            'emailText' => '',
            'emailSendCopy' => '',
            'addEmailAttachment' => '',
            'emailAttachment' => '',
        ];

        $this->connection->update('tl_calendar_events_member', $set, ['id' => $dc->id]);

        // Set correct palette
        $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['sendEmail'];

        $options = [];

        $arrGuideIDS = $this->calendarEventsHelper->getInstructorsAsArray($objEvent, false);

        foreach ($arrGuideIDS as $userId) {
            $objInstructor = $this->user->findByPk($userId);

            if (null !== $objInstructor) {
                if ('' !== $objInstructor->email) {
                    if ($this->validator->isEmail($objInstructor->email)) {
                        $options['tl_user-'.$objInstructor->id] = $objInstructor->firstname.' '.$objInstructor->lastname.' (Leiter)';
                    }
                }
            }
        }

        // Then get event participants
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events_member WHERE eventId = ? ORDER BY stateOfSubscription, firstname', [$eventId]);

        while (false !== ($arrReg = $stmt->fetchAssociative())) {
            if ($this->validator->isEmail($arrReg['email'])) {
                $arrSubscriptionStates = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'];

                if (empty($arrSubscriptionStates) || !\is_array($arrSubscriptionStates)) {
                    throw new \Exception('$GLOBALS["TL_CONFIG"]["SAC-EVENT-TOOL-CONFIG"]["MEMBER-SUBSCRIPTION-STATE"] not found. Please check the config file.');
                }

                $regState = (string) $arrReg['stateOfSubscription'];
                $regState = \in_array($regState, $arrSubscriptionStates, true) ? $regState : EventSubscriptionLevel::SUBSCRIPTION_STATE_UNDEFINED;
                $strLabel = $GLOBALS['TL_LANG']['MSC'][$regState] ?? $regState;

                $options['tl_calendar_events_member-'.$arrReg['id']] = $arrReg['firstname'].' '.$arrReg['lastname'].' ('.$strLabel.')';
            }
        }

        // Set the email recipient list
        $GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['emailRecipients']['options'] = $options;

        // Process form
        if ('tl_calendar_events_member' === $request->request->get('FORM_SUBMIT') && isset($_POST['saveNclose'])) {
            $arrRecipients = [];

            foreach ($request->request->get('emailRecipients') as $key) {
                if (str_contains($key, 'tl_user-')) {
                    $id = str_replace('tl_user-', '', $key);
                    $objInstructor = $this->user->findByPk($id);

                    if (null !== $objInstructor) {
                        if ($this->validator->isEmail($objInstructor->email)) {
                            $arrRecipients[] = $objInstructor->email;
                        }
                    }
                } elseif (str_contains($key, 'tl_calendar_events_member-')) {
                    $id = str_replace('tl_calendar_events_member-', '', $key);
                    $objEventMember = $this->calendarEventsMember->findByPk($id);

                    if (null !== $objEventMember) {
                        if ($this->validator->isEmail($objEventMember->email)) {
                            $arrRecipients[] = $objEventMember->email;
                        }
                    }
                }
            }

            if (!$this->validator->isEmail($this->sacevtEventAdminEmail)) {
                throw new \Exception('Please set a valid email address in parameter %sacevt.event_admin_email%.');
            }

            $objEmail = new Email();
            $objEmail->fromName = html_entity_decode($this->sacevtEventAdminName);
            $objEmail->from = $this->sacevtEventAdminEmail;
            $objEmail->replyTo($user->email);
            $objEmail->subject = html_entity_decode((string) $request->request->get('emailSubject'));
            $objEmail->text = html_entity_decode((string) $request->request->get('emailText'));

            if ($request->request->get('emailSendCopy')) {
                $objEmail->sendBcc($user->email);
            }

            // Add email attachments
            if ($request->request->get('addEmailAttachment')) {
                if ($request->request->has('emailAttachment')) {
                    $uuids = explode(',', $request->request->get('emailAttachment'));

                    if (!empty($uuids) && \is_array($uuids)) {
                        foreach ($uuids as $uuid) {
                            $objFile = $this->files->findByUuid($uuid);

                            if (null !== $objFile) {
                                if (is_file($this->projectDir.'/'.$objFile->path)) {
                                    $objEmail->attachFile($objFile->path);
                                }
                            }
                        }
                    }
                }
            }

            if ($objEmail->sendTo($arrRecipients)) {
                unset($_POST['addEmailAttachment'], $_POST['emailAttachment']);

                // Show a message in the backend
                $msg = $this->translator->trans('MSC.emailSentToEventMembers', [], 'contao_default');
                $this->message->addInfo($msg);

                // Redirect user to the event member list
                $strUrl = 'contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s';
                $eventId = $request->query->get('eventId');
                $rt = $request->query->get('rt');
                $href = sprintf($strUrl, $eventId, $rt);
                $this->controller->redirect($href);
            }
        }
    }

    /**
     * Check permissions.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function checkPermissions(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        $request = $this->requestStack->getCurrentRequest();

        // Allow full access only to admins, owners and allowed groups
        if ($user->admin) {
            return;
        }

        if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, CURRENT_ID)) {
            // User is allowed to edit table
            return;
        }

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

        if ('delete' === $request->query->get('act') || 'toggle' === $request->query->get('act') || 'edit' === $request->query->get('act') || 'select' === $request->query->get('act')) {
            $id = \strlen((string) $request->query->get('id')) ? $request->query->get('id') : CURRENT_ID;

            if ('select' === $request->query->get('act')) {
                $objEvent = $this->calendarEvents->findByPk($id);
            } else {
                /** @var CalendarEventsMemberModel $objEvent */
                if (null !== ($objMember = $this->calendarEventsMember->findByPk($id))) {
                    /** @var CalendarEventsModel $objEvent */
                    $objEvent = $objMember->getRelated('eventId');
                }
            }

            if (null !== $objEvent) {
                // Allow write-access to authors, tour guides and registration admins (tl_calendar_events.registrationGoesTo)
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
     * Export registration list as a DOCX or CSV file.
     *
     * @throws Exception
     * @throws CannotInsertRecord
     * @throws InvalidArgument
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function exportMemberList(DataContainer $dc): Response|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->has('id') && null !== ($objEvent = $this->calendarEvents->findByPk($request->query->get('id')))) {
            // Download the registration list as a docx file
            if ('downloadEventMemberListDocx' === $request->query->get('action')) {
                return $this->registrationListExporterDocx->generate($objEvent, 'docx');
            }

            // Download the registration list as a csv file
            if ('downloadEventMemberListCsv' === $request->query->get('action')) {
                return $this->registrationListExporterCsv->generate($objEvent);
            }
        }

        return null;
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
        return $this->connection
            ->fetchAllKeyValue('SELECT sectionId, name FROM tl_sac_section')
        ;
    }

    /**
     * @return mixed|string|null
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.stateOfSubscription.save', priority: 100)]
    public function saveCallbackStateOfSubscription($varValue, DataContainer $dc): mixed
    {
        $objEventMemberModel = $this->calendarEventsMember->findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            // Retrieve the event id from CURRENT_ID if we have a new entry.
            $eventId = !empty($objEventMemberModel->eventId) ? $objEventMemberModel->eventId : CURRENT_ID;
            $objEvent = $this->calendarEvents->findByPk($eventId);

            if (null !== $objEvent && $objEventMemberModel->stateOfSubscription !== $varValue) {
                // Check if member has already booked at the same time
                $objMember = $this->member->findOneBySacMemberId($objEventMemberModel->sacMemberId);

                // Do not allow the maximum number of participants to be exceeded.
                if (EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED === $varValue) {
                    if (!$this->calendarEventsMember->canAcceptSubscription($objEventMemberModel, $objEvent)) {
                        $varValue = EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED;
                    }
                }

                if (EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED === $varValue && null !== $objMember && !$objEventMemberModel->allowMultiSignUp && $this->calendarEventsHelper->areBookingDatesOccupied($objEvent, $objMember)) {
                    $this->message->addError('Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht angemeldet werden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde. Wenn Sie das trotzdem erlauben möchten, dann setzen Sie das Flag "Mehrfachbuchung zulassen".');
                    $varValue = $objEventMemberModel->stateOfSubscription;
                } elseif ($this->validator->isEmail($objEventMemberModel->email)) {
                    // Use terminal42/notification_center
                    $objNotification = $this->notification->findOneByType('onchange_state_of_subscription');

                    if (null !== $objNotification) {
                        $arrTokens = [
                            'participant_state_of_subscription' => html_entity_decode((string) $GLOBALS['TL_LANG']['MSC'][$varValue]),
                            'event_name' => html_entity_decode($objEvent->title),
                            'participant_uuid' => $objEventMemberModel->uuid,
                            'participant_name' => html_entity_decode($objEventMemberModel->firstname.' '.$objEventMemberModel->lastname),
                            'participant_email' => $objEventMemberModel->email,
                            'event_link_detail' => 'https://'.$this->environment->get('host').'/'.$this->events->generateEventUrl($objEvent),
                        ];

                        $objNotification->send($arrTokens, $this->sacevtLocale);
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
     * Add more data to the registration, when a backend user adds a new registration manually.
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

    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function setStateOfSubscription(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('select' === $request->query->get('act')) {
            return;
        }

        if ('editAll' === $request->query->get('act')) {
            return;
        }

        if ('overrideAll' === $request->query->get('act')) {
            return;
        }

        if (!$dc->id || !$request->query->has('act')) {
            return;
        }

        if ('create' === $request->query->get('act')) {
            return;
        }

        $objEventMemberModel = $this->calendarEventsMember->findByPk($dc->id);

        if (null === $objEventMemberModel) {
            return;
        }

        if ('refuseWithEmail' === $request->query->get('action')) {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['refuseWithEmail'];

            return;
        }

        if ('acceptWithEmail' === $request->query->get('action')) {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['acceptWithEmail'];

            return;
        }

        if ('addToWaitlist' === $request->query->get('action')) {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['addToWaitlist'];

            return;
        }

        if (isset($_POST['refuseWithEmail'])) {
            // Show another palette
            $this->controller->redirect($this->backend->addToUrl('action=refuseWithEmail'));
        }

        if (isset($_POST['acceptWithEmail'])) {
            $blnAllow = true;

            $objEvent = $this->calendarEvents->findByPk($objEventMemberModel->eventId);

            if (null !== $objEvent && !$this->calendarEventsMember->canAcceptSubscription($objEventMemberModel, $objEvent)) {
                $blnAllow = false;
            }

            if ($blnAllow) {
                // Show another palette
                $this->controller->redirect($this->backend->addToUrl('action=acceptWithEmail'));
            } else {
                $this->message->addError('Dem Teilnehmer kann die Teilnahme am Event nicht bestätigt werden, da die maximale Teilnehmerzahl bereits erreicht wurde.');
            }
        }

        if (isset($_POST['addToWaitlist'])) {
            // Show another palette
            $this->controller->redirect($this->backend->addToUrl('action=addToWaitlist'));
        }
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
                if ('tour' === $objEvent->eventType || 'lastMinuteTour' === $objEvent->eventType) {
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
        $icon = 'icons/subscription-states/'.$row['stateOfSubscription'].'.svg';
        $args[0] = sprintf('<div><img src="%s/%s" alt="%s" width="16" height=16"></div>', Bundle::ASSET_DIR, $icon, $row['stateOfSubscription']);

        return $args;
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.dashboard.input_field', priority: 100)]
    public function parseDashboard(DataContainer $dc): string
    {
        $objEventMemberModel = $this->calendarEventsMember->findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            $objTemplate = new BackendTemplate('be_calendar_events_registration_dashboard');
            $objTemplate->objRegistration = $objEventMemberModel;
            $objTemplate->stateOfSubscription = $objEventMemberModel->stateOfSubscription;
            $objEvent = $this->calendarEvents->findByPk($objEventMemberModel->eventId);

            if (null !== $objEvent) {
                $objTemplate->objEvent = $objEvent;

                if (!$objEventMemberModel->hasParticipated && '' !== $objEventMemberModel->email) {
                    if ($this->validator->isEmail($objEventMemberModel->email)) {
                        $objTemplate->showEmailButtons = true;
                    }
                }

                return $objTemplate->parse();
            }
        }

        return '';
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.refuseWithEmail.input_field', priority: 100)]
    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.acceptWithEmail.input_field', priority: 100)]
    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.addToWaitlist.input_field', priority: 100)]
    public function inputFieldCallbackNotifyMemberAboutSubscriptionState(DataContainer $dc): string
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        // Build action array first
        $arrActions = [
            'acceptWithEmail' => [
                'formId' => 'subscription-accepted-form',
                'headline' => 'Zusage zum Event',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED,
                'sessionInfoText' => 'Dem Benutzer wurde mit einer E-Mail eine Zusage für diesen Event versandt.',
                'emailTemplate' => 'be_email_templ_accept_registration',
                'emailSubject' => 'Zusage für %s',
            ],
            'addToWaitlist' => [
                'formId' => 'subscription-waitlisted-form',
                'headline' => 'Auf Warteliste setzen',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED,
                'sessionInfoText' => 'Dem Benutzer wurde auf die Warteliste gesetzt und mit einer E-Mail darüber informiert.',
                'emailTemplate' => 'be_email_templ_added_to_waitlist',
                'emailSubject' => 'Auf Warteliste für %s',
            ],
            'refuseWithEmail' => [
                'formId' => 'subscription-refused-form',
                'headline' => 'Absage mitteilen',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_REJECTED,
                'sessionInfoText' => 'Dem Benutzer wurde mit einer E-Mail eine Absage versandt.',
                'emailTemplate' => 'be_email_templ_refuse_registration',
                'emailSubject' => 'Absage für %s',
            ],
        ];

        if (!$request->query->has('action') || !\is_array($arrActions[$request->query->get('action')]) || empty($arrActions[$request->query->get('action')])) {
            $this->message->addInfo('Es ist ein Fehler aufgetreten.');
            $this->controller->redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.$request->query->get('id').'&act=edit&rt='.$request->query->get('rt'));
        }

        // Set action array
        $arrAction = $arrActions[$request->query->get('action')];

        // Generate form fields
        $objForm = new Form(
            $arrAction['formId'],
            'POST',
            static fn ($objHaste) => $request->request->get('FORM_SUBMIT') === $objHaste->getFormId()
        );

        // Now let's add form fields:
        $objForm->addFormField('subject', [
            'label' => 'Betreff',
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
        ]);

        $objForm->addFormField('text', [
            'label' => 'Nachricht',
            'inputType' => 'textarea',
            'eval' => ['rows' => 20, 'cols' => 80, 'mandatory' => true],
        ]);

        $objForm->addFormField('submit', [
            'label' => 'Nachricht absenden',
            'inputType' => 'submit',
        ]);

        // Send notification
        if ('tl_calendar_events_member' === $request->request->get('FORM_SUBMIT')) {
            if ('' !== $request->request->get('subject') && '' !== $request->request->get('text')) {
                $objEventMemberModel = $this->calendarEventsMember->findByPk($dc->id);

                if (null !== $objEventMemberModel) {
                    if (!$this->validator->isEmail($this->sacevtEventAdminEmail)) {
                        throw new \Exception('Please set a valid email address in parameter sacevt.event_admin_email.');
                    }

                    $objEmail = new Email();
                    $objEmail->fromName = html_entity_decode(html_entity_decode($this->sacevtEventAdminName));
                    $objEmail->from = $this->sacevtEventAdminEmail;
                    $objEmail->replyTo($user->email);
                    $objEmail->subject = html_entity_decode((string) $request->request->get('subject'));
                    $objEmail->text = html_entity_decode(strip_tags((string) $request->request->get('text')));

                    // Check if member has already booked at the same time
                    $objMember = $this->member->findOneBySacMemberId($objEventMemberModel->sacMemberId);
                    $objEvent = $this->calendarEvents->findByPk($objEventMemberModel->eventId);

                    if ('acceptWithEmail' === $request->query->get('action') && null !== $objMember && !$objEventMemberModel->allowMultiSignUp && null !== $objEvent && $this->calendarEventsHelper->areBookingDatesOccupied($objEvent, $objMember)) {
                        $this->message->addError('Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht angemeldet werden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde. Wenn Sie das trotzdem erlauben möchten, dann setzen Sie das Flag "Mehrfachbuchung zulassen".');
                    } elseif ('acceptWithEmail' === $request->query->get('action') && null !== $objEvent && !$this->calendarEventsMember->canAcceptSubscription($objEventMemberModel, $objEvent)) {
                        $this->message->addError('Es ist ein Fehler aufgetreten. Da die maximale Teilnehmerzahl bereits erreicht ist, kann für den Teilnehmer die Teilnahme am Event nicht bestätigt werden.');
                    } // Send email
                    elseif ($this->validator->isEmail($objEventMemberModel->email)) {
                        $objEmail->sendTo($objEventMemberModel->email);

                        $set = ['stateOfSubscription' => $arrAction['stateOfSubscription']];
                        $this->connection->update('tl_calendar_events_member', $set, ['id' => $dc->id]);

                        $this->message->addInfo($arrAction['sessionInfoText']);
                    } else {
                        $this->message->addInfo('Es ist ein Fehler aufgetreten. Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.');
                    }
                }
                $this->controller->redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.$request->query->get('id').'&act=edit&rt='.$request->query->get('rt'));
            } else {
                // Add value to fields
                if ('' !== $request->request->get('subject')) {
                    $objForm->getWidget('subject')->value = $request->request->get('subject');
                }

                if ('' !== $request->request->get('text')) {
                    $objForm->getWidget('text')->value = strip_tags($request->request->get('text'));
                }

                // Generate template
                $objTemplate = new BackendTemplate('be_calendar_events_registration_email');
                $objTemplate->headline = $arrAction['headline'];
                $objTemplate->form = $objForm;

                return $objTemplate->parse();
            }
        } else { // Prefill form
            // Get the registration object
            $objEventMemberModel = $this->calendarEventsMember->findByPk($dc->id);

            if (null !== $objEventMemberModel) {
                /** @var CalendarEventsModel $objEvent */
                $objEvent = $objEventMemberModel->getRelated('eventId');

                // Get event dates as a comma separated string
                $eventDates = $this->calendarEventsHelper->getEventTimestamps($objEvent);
                $strDates = implode(', ', array_map(
                    static fn ($tstamp) => date(Config::get('dateFormat'), (int) $tstamp),
                    $eventDates
                ));

                // Build token array
                $arrTokens = [
                    'participantFirstname' => $objEventMemberModel->firstname,
                    'participantLastname' => $objEventMemberModel->lastname,
                    'participant_uuid' => $objEventMemberModel->uuid,
                    'eventName' => $objEvent->title,
                    'eventIban' => $objEvent->addIban ? $objEvent->iban : '',
                    'eventIbanBeneficiary' => $objEvent->addIban ? $objEvent->ibanBeneficiary : '',
                    'courseId' => $objEvent->courseId,
                    'eventType' => $objEvent->eventType,
                    'eventUrl' => $this->events->generateEventUrl($objEvent, true),
                    'eventDates' => $strDates,
                    'instructorName' => $user->name,
                    'instructorFirstname' => $user->firstname,
                    'instructorLastname' => $user->lastname,
                    'instructorPhone' => $user->phone,
                    'instructorMobile' => $user->mobile,
                    'instructorStreet' => $user->street,
                    'instructorPostal' => $user->postal,
                    'instructorCity' => $user->city,
                    'instructorEmail' => $user->email,
                ];

                if ('acceptWithEmail' === $request->query->get('action') && $objEvent->customizeEventRegistrationConfirmationEmailText && '' !== $objEvent->customEventRegistrationConfirmationEmailText) {
                    // Only for acceptWithEmail!!!
                    // Replace tags for custom notification set in the events settings (tags can be used case-insensitive!)
                    $emailBodyText = $objEvent->customEventRegistrationConfirmationEmailText;

                    foreach ($arrTokens as $k => $v) {
                        $strPattern = '/##'.$k.'##/i';
                        $emailBodyText = preg_replace($strPattern, $v, $emailBodyText);
                    }
                    $emailBodyText = strip_tags($emailBodyText);
                } else {
                    // Build email text from template
                    $objEmailTemplate = new BackendTemplate($arrAction['emailTemplate']);

                    foreach ($arrTokens as $k => $v) {
                        $objEmailTemplate->{$k} = $v;
                    }
                    $emailBodyText = strip_tags($objEmailTemplate->parse());
                }

                // Get event type
                $eventType = \strlen((string) $GLOBALS['TL_LANG']['MSC'][$objEvent->eventType]) ? $GLOBALS['TL_LANG']['MSC'][$objEvent->eventType].': ' : 'Event: ';

                // Add value to ffields
                $objForm->getWidget('subject')->value = sprintf($arrAction['emailSubject'], $eventType.$objEvent->title);
                $objForm->getWidget('text')->value = $emailBodyText;

                // Generate template
                $objTemplate = new BackendTemplate('be_calendar_events_registration_email');
                $objTemplate->headline = $arrAction['headline'];
                $objTemplate->form = $objForm;

                return $objTemplate->parse();
            }

            return '';
        }
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.global_operations.backToEventSettings.button', priority: 100)]
    public function buttonCbBackToEventSettings(string|null $href, string $label, string $title, string $class, string $attributes, string $table): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $href = $this->stringUtil->ampersand('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s&ref=%s');
        $eventId = $request->query->get('id');
        $refererId = $request->attributes->get('_contao_referer_id');

        $href = sprintf($href, $eventId, REQUEST_TOKEN, $refererId);

        return sprintf(' <a href="%s" class="%s" title="%s" %s>%s</a>', $href, $class, $title, $attributes, $label);
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'edit.buttons', priority: 100)]
    public function buttonsCallback(array $arrButtons, DataContainer $dc): array
    {
        $request = $this->requestStack->getCurrentRequest();

        // Remove all buttons
        if ('refuseWithEmail' === $request->query->get('action') || 'acceptWithEmail' === $request->query->get('action') || 'addToWaitlist' === $request->query->get('action')) {
            $arrButtons = [];
        }

        if ('sendEmail' === $request->query->get('action')) {
            $arrButtons['saveNclose'] = '<button type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c">E-Mail absenden</button>';
            unset($arrButtons['save']);
        }

        unset($arrButtons['saveNback'], $arrButtons['saveNduplicate'], $arrButtons['saveNcreate']);

        return $arrButtons;
    }
}
