<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Date;
use Contao\Email;
use Contao\Environment;
use Contao\EventReleaseLevelPolicyModel;
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
use Markocupic\SacEventToolBundle\Csv\ExportEventRegistrationList;
use Markocupic\SacEventToolBundle\DocxTemplator\EventMemberList2Docx;
use NotificationCenter\Model\Notification;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarEventsMember
{
    public const TABLE = 'tl_calendar_events_member';
    private const SESSION_FLASH_ERROR = 'sacevt.be.tl_calendar_events_member.error';
    private const SESSION_FLASH_INFO = 'sacevt.be.tl_calendar_events_member.info';

    private RequestStack $requestStack;
    private Connection $connection;
    private Util $util;
    private TranslatorInterface $translator;
    private Security $security;
    private ExportEventRegistrationList $registrationListExporterCsv;
    private EventMemberList2Docx $registrationListExporterDocx;
    private string $projectDir;
    private string $eventAdminName;
    private string $eventAdminEmail;
    private string $locale;

    public function __construct(RequestStack $requestStack, Connection $connection, Util $util, TranslatorInterface $translator, Security $security, ExportEventRegistrationList $registrationListExporterCsv, EventMemberList2Docx $registrationListExporterDocx, string $projectDir, string $eventAdminName, string $eventAdminEmail, string $locale)
    {
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->util = $util;
        $this->translator = $translator;
        $this->security = $security;
        $this->registrationListExporterCsv = $registrationListExporterCsv;
        $this->registrationListExporterDocx = $registrationListExporterDocx;
        $this->projectDir = $projectDir;
        $this->eventAdminName = $eventAdminName;
        $this->eventAdminEmail = $eventAdminEmail;
        $this->locale = $locale;
    }

    /**
     * Set correct referer.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     */
    public function setCorrectReferer(): void
    {
        $this->util->setCorrectReferer();
    }

    /**
     * Load backend assets.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     */
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
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     *
     * @throws Exception
     */
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
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     *
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws Exception
     */
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

        if (null === ($objEvent = CalendarEventsModel::findByPk($eventId))) {
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

        $arrGuideIDS = CalendarEventsHelper::getInstructorsAsArray($objEvent, false);

        foreach ($arrGuideIDS as $userId) {
            $objInstructor = UserModel::findByPk($userId);

            if (null !== $objInstructor) {
                if ('' !== $objInstructor->email) {
                    if (Validator::isEmail($objInstructor->email)) {
                        $options['tl_user-'.$objInstructor->id] = $objInstructor->firstname.' '.$objInstructor->lastname.' (Leiter)';
                    }
                }
            }
        }

        // Then get event participants
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events_member WHERE eventId = ? ORDER BY stateOfSubscription, firstname', [$eventId]);

        while (false !== ($arrReg = $stmt->fetchAssociative())) {
            if (Validator::isEmail($arrReg['email'])) {
                $arrSubscriptionStates = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'];

                if (empty($arrSubscriptionStates) || !\is_array($arrSubscriptionStates)) {
                    throw new \Exception('$GLOBALS["TL_CONFIG"]["SAC-EVENT-TOOL-CONFIG"]["MEMBER-SUBSCRIPTION-STATE"] not found. Please check the config file.');
                }

                $regState = (string) $arrReg['stateOfSubscription'];
                $regState = \in_array($regState, $arrSubscriptionStates, true) ? $regState : EventSubscriptionLevel::SUBSCRIPTION_STATE_UNDEFINED;
                $strLabel = $GLOBALS['TL_LANG']['tl_calendar_events_member'][$regState] ?? $regState;

                $options['tl_calendar_events_member-'.$arrReg['id']] = $arrReg['firstname'].' '.$arrReg['lastname'].' ('.$strLabel.')';
            }
        }

        // Set the email recipient list
        $GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['emailRecipients']['options'] = $options;

        // Process form
        if ('tl_calendar_events_member' === $request->request->get('FORM_SUBMIT') && isset($_POST['saveNclose'])) {
            $arrRecipients = [];

            foreach ($request->request->get('emailRecipients') as $key) {
                if (false !== strpos($key, 'tl_user-')) {
                    $id = str_replace('tl_user-', '', $key);
                    $objInstructor = UserModel::findByPk($id);

                    if (null !== $objInstructor) {
                        if (Validator::isEmail($objInstructor->email)) {
                            $arrRecipients[] = $objInstructor->email;
                        }
                    }
                } elseif (false !== strpos($key, 'tl_calendar_events_member-')) {
                    $id = str_replace('tl_calendar_events_member-', '', $key);
                    $objEventMember = CalendarEventsMemberModel::findByPk($id);

                    if (null !== $objEventMember) {
                        if (Validator::isEmail($objEventMember->email)) {
                            $arrRecipients[] = $objEventMember->email;
                        }
                    }
                }
            }

            if (!Validator::isEmail($this->eventAdminEmail)) {
                throw new \Exception('Please set a valid email address in parameter %sacevt.event_admin_email%.');
            }

            $objEmail = new Email();
            $objEmail->fromName = html_entity_decode($this->eventAdminName);
            $objEmail->from = $this->eventAdminEmail;
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
                            $objFile = FilesModel::findByUuid($uuid);

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
            }
        }
    }

    /**
     * Check permissions.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     *
     * @throws \Exception
     */
    public function checkPermissions(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        $request = $this->requestStack->getCurrentRequest();

        // Allow full access only to admins, owners and allowed groups
        if ($user->isAdmin) {
            return;
        }

        if (EventReleaseLevelPolicyModel::hasWritePermission($user->id, CURRENT_ID)) {
            // User is allowed to edit table
            return;
        }

        if (!$request->query->get('act') && $request->query->get('id')) {
            $objEvent = CalendarEventsModel::findByPk($request->query->get('id'));

            if (null !== $objEvent) {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                $arrRegistrationGoesTo = StringUtil::deserialize($objEvent->registrationGoesTo, true);

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
                $objEvent = CalendarEventsModel::findByPk($id);
            } else {
                /** @var CalendarEventsMemberModel $objEvent */
                if (null !== ($objMember = CalendarEventsMemberModel::findByPk($id))) {
                    /** @var CalendarEventsModel $objEvent */
                    $objEvent = $objMember->getRelated('eventId');
                }
            }

            if (null !== $objEvent) {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                $arrRegistrationGoesTo = StringUtil::deserialize($objEvent->registrationGoesTo, true);

                if (!\in_array($user->id, $arrAuthors, false) && !\in_array($user->id, $arrRegistrationGoesTo, true)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['closed'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'] = true;
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCopyable'] = true;

                    Message::addError($this->translator->trans('ERR.accessDenied', [], 'contao_default'));
                    Controller::redirect('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.$objEvent->id);
                }
            }
        }
    }

    /**
     * Export registration list as a DOCX or CSV file.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws CannotInsertRecord
     * @throws InvalidArgument
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function exportMemberList(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->has('id') && null !== ($objEvent = CalendarEventsModel::findByPk($request->query->get('id')))) {
            // Download the registration list as a docx file
            if ('downloadEventMemberListDocx' === $request->query->get('action')) {
                $this->registrationListExporterDocx->generate($objEvent, 'docx');
            }

            // Download the registration list as a csv file
            if ('downloadEventMemberListCsv' === $request->query->get('action')) {
                $this->registrationListExporterCsv->generate($objEvent);
            }
        }
    }

    /**
     * Delete orphaned records.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     *
     * @throws Exception
     */
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
            Controller::reload();
        }
    }

    /**
     * List SAC sections.
     *
     * @Callback(table="tl_calendar_events_member", target="fields.sectionId.options")
     *
     * @throws Exception
     */
    public function listSections(): array
    {
        return $this->connection
            ->fetchAllKeyValue('SELECT sectionId, name FROM tl_sac_section')
        ;
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="fields.stateOfSubscription.save")
     *
     * @return mixed|string|null
     */
    public function saveCallbackStateOfSubscription($varValue, DataContainer $dc)
    {
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            // Retrieve the event id from CURRENT_ID if we have a new entry.
            $eventId = !empty($objEventMemberModel->eventId) ? $objEventMemberModel->eventId : CURRENT_ID;
            $objEvent = CalendarEventsModel::findByPk($eventId);

            if (null !== $objEvent && $objEventMemberModel->stateOfSubscription !== $varValue) {
                // Check if member has already booked at the same time
                $objMember = MemberModel::findOneBySacMemberId($objEventMemberModel->sacMemberId);

                // Do not allow the maximum number of participants to be exceeded.
                if (EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED === $varValue) {
                    if (!CalendarEventsMemberModel::canAcceptSubscription($objEventMemberModel, $objEvent)) {
                        $varValue = EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED;
                    }
                }

                if (EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED === $varValue && null !== $objMember && !$objEventMemberModel->allowMultiSignUp && CalendarEventsHelper::areBookingDatesOccupied($objEvent, $objMember)) {
                    $session = $this->requestStack->getCurrentRequest()->getSession();
                    $flashBag = $session->getFlashBag();

                    $flashBag->set(self::SESSION_FLASH_ERROR, 'Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht angemeldet werden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde. Wenn Sie das trotzdem erlauben möchten, dann setzen Sie das Flag "Mehrfachbuchung zulassen".');
                    $varValue = $objEventMemberModel->stateOfSubscription;
                } elseif (Validator::isEmail($objEventMemberModel->email)) {
                    // Use terminal42/notification_center
                    $objNotification = Notification::findOneByType('onchange_state_of_subscription');

                    if (null !== $objNotification) {
                        $arrTokens = [
                            'participant_state_of_subscription' => html_entity_decode((string) $GLOBALS['TL_LANG']['tl_calendar_events_member'][$varValue]),
                            'event_name' => html_entity_decode($objEvent->title),
                            'participant_uuid' => $objEventMemberModel->uuid,
                            'participant_name' => html_entity_decode($objEventMemberModel->firstname.' '.$objEventMemberModel->lastname),
                            'participant_email' => $objEventMemberModel->email,
                            'event_link_detail' => 'https://'.Environment::get('host').'/'.Events::generateEventUrl($objEvent),
                        ];

                        $objNotification->send($arrTokens, $this->locale);
                    }
                }
            }
        }

        return $varValue;
    }

    /**
     * Add more data to the registration, when user adds a new registration manually.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onsubmit")
     *
     * @throws Exception
     */
    public function onsubmitCallback(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $set = [
            'addedOn' => time(),
            'tstamp' => time(),
            'sacMemberId' => '',
            'contaoMemberId' => 0,
        ];

        $arrReg = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events_member WHERE id = ?', [$dc->id]);

        // Set the SAC member id e.g: 185155
        if ($arrReg) {
            $set['sacMemberId'] = $arrReg['sacMemberId'];
        }

        // Set the Contao member id
        if (!empty($arrReg['sacMemberId'])) {
            $id = $this->connection->fetchOne('SELECT id FROM tl_member WHERE sacMemberId = ?', [$arrReg['sacMemberId']]);

            if ($id) {
                $set['contaoMemberId'] = $id;
            }
        }

        $eventId = $arrReg['eventId'] > 0 ? $arrReg['eventId'] : CURRENT_ID;

        // Add correct event id and event title
        $arrEvent = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events WHERE id = ?', [$eventId]);

        if ($arrEvent) {
            // Set correct event title and eventId
            $set['eventName'] = $arrEvent['title'];
            $set['eventId'] = $arrEvent['id'];
        }

        $this->connection->update('tl_calendar_events_member', $set, ['id' => $dc->id]);
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="config.onload")
     *
     * @throws \Exception
     */
    public function setStateOfSubscription(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$dc->id || !$request->query->has('act')) {
            return;
        }

        if ('create' === $request->query->get('act')) {
            return;
        }

        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null === $objEventMemberModel) {
            throw new \Exception(sprintf('Registration with ID %s not found.', $dc->id));
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

        $session = $request->getSession();
        $flashBag = $session->getFlashBag();

        if ($flashBag->has(self::SESSION_FLASH_ERROR)) {
            Message::addError($flashBag->get(self::SESSION_FLASH_ERROR)[0]);
        }

        if ($flashBag->has(self::SESSION_FLASH_INFO)) {
            Message::addInfo($flashBag->get(self::SESSION_FLASH_INFO)[0]);
        }

        if (isset($_POST['refuseWithEmail'])) {
            // Show another palette
            Controller::redirect(Backend::addToUrl('action=refuseWithEmail'));
        }

        if (isset($_POST['acceptWithEmail'])) {
            $blnAllow = true;

            $objEvent = CalendarEventsModel::findByPk($objEventMemberModel->eventId);

            if (null !== $objEvent && !CalendarEventsMemberModel::canAcceptSubscription($objEventMemberModel, $objEvent)) {
                $blnAllow = false;
            }

            if ($blnAllow) {
                // Show another palette
                Controller::redirect(Backend::addToUrl('action=acceptWithEmail'));
            } else {
                $flashBag->set(self::SESSION_FLASH_ERROR, 'Dem Teilnehmer kann die Teilnahme am Event nicht bestätigt werden, da die maximale Teilnehmerzahl bereits erreicht wurde.');
            }
        }

        if (isset($_POST['addToWaitlist'])) {
            // Show another palette
            Controller::redirect(Backend::addToUrl('action=addToWaitlist'));
        }
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="config.onload")
     */
    public function setGlobalOperations(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        // Remove edit_all (mehrere bearbeiten) button
        if (!$user->admin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['all']);
        }

        // Generate href for $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']
        // Generate href for  $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']

        $blnAllowTourReportButton = false;
        $blnAllowInstructorInvoiceButton = false;

        $eventId = $request->query->get('id');
        $objEvent = CalendarEventsModel::findByPk($eventId);

        if (null !== $objEvent) {
            // Check if backend user is allowed
            if (EventReleaseLevelPolicyModel::hasWritePermission($user->id, $objEvent->id) || $objEvent->registrationGoesTo === $user->id) {
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
     *
     * @Callback(table="tl_calendar_events_member", target="config.onshow")
     */
    public function decryptSectionIds(array $data, array $row, DataContainer $dc): array
    {
        return $this->util->decryptSectionIds($data, $row, $dc, self::TABLE);
    }

    /**
     * Add an icon to each record.
     *
     * @Callback(table="tl_calendar_events_member", target="list.label.label")
     */
    public function addIcon(array $row, string $label, DataContainer $dc, array $args): array
    {
        $icon = 'icons/'.$row['stateOfSubscription'].'.svg';
        $args[0] = sprintf('<div><img src="%s/%s" alt="%s" width="16" height=16"></div>', Bundle::ASSET_DIR, $icon, $row['stateOfSubscription']);

        return $args;
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="fields.dashboard.input_field")
     */
    public function parseDashboard(DataContainer $dc): string
    {
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            $objTemplate = new BackendTemplate('be_calendar_events_registration_dashboard');
            $objTemplate->objRegistration = $objEventMemberModel;
            $objTemplate->stateOfSubscription = $objEventMemberModel->stateOfSubscription;
            $objEvent = CalendarEventsModel::findByPk($objEventMemberModel->eventId);

            if (null !== $objEvent) {
                $objTemplate->objEvent = $objEvent;

                if (!$objEventMemberModel->hasParticipated && '' !== $objEventMemberModel->email) {
                    if (Validator::isEmail($objEventMemberModel->email)) {
                        $objTemplate->showEmailButtons = true;
                    }
                }

                return $objTemplate->parse();
            }
        }

        return '';
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="fields.refuseWithEmail.input_field")
     * @Callback(table="tl_calendar_events_member", target="fields.acceptWithEmail.input_field")
     * @Callback(table="tl_calendar_events_member", target="fields.addToWaitlist.input_field")
     *
     * @throws \Exception
     */
    public function inputFieldCallbackNotifyMemberAboutSubscriptionState(DataContainer $dc): string
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        $session = $this->requestStack->getCurrentRequest()->getSession();
        $flashBag = $session->getFlashBag();

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
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_REFUSED,
                'sessionInfoText' => 'Dem Benutzer wurde mit einer E-Mail eine Absage versandt.',
                'emailTemplate' => 'be_email_templ_refuse_registration',
                'emailSubject' => 'Absage für %s',
            ],
        ];

        if (!$request->query->has('action') || !\is_array($arrActions[$request->query->get('action')]) || empty($arrActions[$request->query->get('action')])) {
            $flashBag->set(self::SESSION_FLASH_INFO, 'Es ist ein Fehler aufgetreten.');
            Controller::redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.$request->query->get('id').'&act=edit&rt='.$request->query->get('rt'));
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
                $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

                if (null !== $objEventMemberModel) {
                    if (!Validator::isEmail($this->eventAdminEmail)) {
                        throw new \Exception('Please set a valid email address in parameter sacevt.event_admin_email.');
                    }
                    $objEmail = Notification::findOneByType('default_email');

                    // Use terminal42/notification_center
                    if (null !== $objEmail) {
                        // Set token array
                        $arrTokens = [
                            'email_sender_name' => html_entity_decode(html_entity_decode($this->eventAdminName)),
                            'email_sender_email' => $this->eventAdminEmail,
                            'send_to' => $objEventMemberModel->email,
                            'reply_to' => $user->email,
                            'email_subject' => html_entity_decode((string) $request->request->get('subject')),
                            'email_text' => html_entity_decode(strip_tags((string) $request->request->get('text'))),
                            'attachment_tokens' => null,
                            'recipient_cc' => null,
                            'recipient_bcc' => null,
                            'email_html' => null,
                        ];

                        // Check if member has already booked at the same time
                        $objMember = MemberModel::findOneBySacMemberId($objEventMemberModel->sacMemberId);
                        $objEvent = CalendarEventsModel::findByPk($objEventMemberModel->eventId);

                        if ('acceptWithEmail' === $request->query->get('action') && null !== $objMember && !$objEventMemberModel->allowMultiSignUp && null !== $objEvent && CalendarEventsHelper::areBookingDatesOccupied($objEvent, $objMember)) {
                            $flashBag->set(self::SESSION_FLASH_ERROR, 'Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht angemeldet werden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde. Wenn Sie das trotzdem erlauben möchten, dann setzen Sie das Flag "Mehrfachbuchung zulassen".');
                        } elseif ('acceptWithEmail' === $request->query->get('action') && null !== $objEvent && !CalendarEventsMemberModel::canAcceptSubscription($objEventMemberModel, $objEvent)) {
                            $flashBag->set(self::SESSION_FLASH_ERROR, 'Es ist ein Fehler aufgetreten. Da die maximale Teilnehmerzahl bereits erreicht ist, kann für den Teilnehmer die Teilnahme am Event nicht bestätigt werden.');
                        } // Send email
                        elseif (Validator::isEmail($objEventMemberModel->email)) {
                            $objEmail->send($arrTokens, $this->locale);

                            $set = ['stateOfSubscription' => $arrAction['stateOfSubscription']];
                            $this->connection->update('tl_calendar_events_member', $set, ['id' => $dc->id]);

                            $flashBag->set(self::SESSION_FLASH_INFO, $arrAction['sessionInfoText']);
                        } else {
                            $flashBag->set(self::SESSION_FLASH_INFO, 'Es ist ein Fehler aufgetreten. Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.');
                        }
                    }
                }
                Controller::redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.$request->query->get('id').'&act=edit&rt='.$request->query->get('rt'));
            } else {
                // Add value to ffields
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
            $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

            if (null !== $objEventMemberModel) {
                /** @var CalendarEventsModel $objEvent */
                $objEvent = $objEventMemberModel->getRelated('eventId');

                // Get event dates as a comma separated string
                $eventDates = CalendarEventsHelper::getEventTimestamps($objEvent);
                $strDates = implode(', ', array_map(
                    static fn ($tstamp) => Date::parse(Config::get('dateFormat'), $tstamp),
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
                    'eventUrl' => Events::generateEventUrl($objEvent, true),
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

    /**
     * @Callback(table="tl_calendar_events_member", target="list.global_operations.backToEventSettings.button")
     */
    public function buttonCbBackToEventSettings(?string $href, string $label, string $title, string $class, string $attributes, string $table): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $href = StringUtil::ampersand('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s&ref=%s');
        $eventId = $request->query->get('id');
        $refererId = $request->get('_contao_referer_id');

        $href = sprintf($href, $eventId, REQUEST_TOKEN, $refererId);

        return sprintf(' <a href="%s" class="%s" title="%s" %s>%s</a>', $href, $class, $title, $attributes, $label);
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="edit.buttons")
     */
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
