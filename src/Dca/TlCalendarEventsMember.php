<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\Environment;
use Contao\EventReleaseLevelPolicyModel;
use Contao\Events;
use Contao\FilesModel;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Haste\Form\Form;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use NotificationCenter\Model\Notification;

/**
 * Class TlCalendarEventsMember.
 */
class TlCalendarEventsMember extends Backend
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        $this->import('BackendUser', 'User');
        parent::__construct();

        // Set correct referer
        if ('sac_calendar_events_tool' === Input::get('do') && '' !== Input::get('ref')) {
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicsaceventtool/js/backend_member_autocomplete.js';
        }

        // Set correct referer
        if ('sac_calendar_events_tool' === Input::get('do') && '' !== Input::get('ref')) {
            $objSession = System::getContainer()->get('session');
            $ref = Input::get('ref');
            $session = $objSession->get('referer');

            if (isset($session[$ref]['tl_calendar_container'])) {
                $session[$ref]['tl_calendar_container'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_container']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar'])) {
                $session[$ref]['tl_calendar'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar_events'])) {
                $session[$ref]['tl_calendar_events'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar_events_instructor_invoice'])) {
                $session[$ref]['tl_calendar_events_instructor_invoice'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events_instructor_invoice']);
                $objSession->set('referer', $session);
            }
        }

        $objDb = $this->Database
            ->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=?')
            ->limit(1)
            ->execute(Input::get('id'))
        ;

        if ($objDb->numRows) {
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']['href'] = str_replace('sendEmail', 'sendEmail&id='.$objDb->id.'&eventId='.Input::get('id'), $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']['href']);
        } else {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']);
        }

        if ('sendEmail' === Input::get('call')) {
            // Delete E-Mail fields
            $opt = [
                'emailRecipients' => '',
                'emailSubject' => '',
                'emailText' => '',
                'emailSendCopy' => '',
                'addEmailAttachment' => '',
                'emailAttachment' => '',
            ];
            $this->Database
                ->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')
                ->set($opt)
                ->execute(Input::get('id'))
            ;
        }
    }

    /**
     * OnLoad Callback.
     *
     * @throws Exception
     */
    public function onloadCallback(DataContainer $dc): void
    {
        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin) {
            // Allow
        } elseif (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, CURRENT_ID)) {
            // User is allowed to edit table
        } else {
            if (!Input::get('act') && Input::get('id')) {
                $objEvent = CalendarEventsModel::findByPk(Input::get('id'));

                if (null !== $objEvent) {
                    $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                    $arrRegistrationGoesTo = StringUtil::deserialize($objEvent->registrationGoesTo, true);

                    if (!\in_array($this->User->id, $arrAuthors, false) && !\in_array($this->User->id, $arrRegistrationGoesTo, true)) {
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'];
                        unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['all'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['downloadEventMemberList'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['edit'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['delete'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['toggleStateOfParticipation']);
                    }
                }
            }

            if ('delete' === Input::get('act') || 'toggle' === Input::get('act') || 'edit' === Input::get('act') || 'select' === Input::get('act')) {
                $id = \strlen((string) Input::get('id')) ? Input::get('id') : CURRENT_ID;

                if ('select' === Input::get('act')) {
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

                    if (!\in_array($this->User->id, $arrAuthors, false) && !\in_array($this->User->id, $arrRegistrationGoesTo, true)) {
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'];
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'];
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notSortable'];
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCopyable'];
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'];

                        Message::addError('Du hast nicht die nötigen Berechtigungen um Datensätze zu bearbeiten, zu löschen oder hinzuzufügen.');
                        $this->redirect('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.$objEvent->id);
                    }
                }
            }
        }

        // Download the registration list as a docx file
        if ('downloadEventMemberList' === Input::get('act')) {
            $objMemberList = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\EventMemberList2Docx');
            /** @var CalendarEventsModel $objEvent */
            $objEvent = CalendarEventsModel::findByPk(Input::get('id'));
            $objMemberList->generate($objEvent, 'docx');
            exit;
        }

        if ('sendEmail' === Input::get('call')) {
            // Set Recipient Array for the checkbox list
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['sendEmail'];
            $options = [];

            /**
             * Get the event instructor.
             *
             * @var CalendarEventsModel $objEvent
             */
            $objEvent = CalendarEventsModel::findByPk(Input::get('eventId'));

            if (null !== $objEvent) {
                $arrGuideIDS = CalendarEventsHelper::getInstructorsAsArray($objEvent, false);

                foreach ($arrGuideIDS as $userId) {
                    /** @var UserModel $objInstructor */
                    $objInstructor = UserModel::findByPk($userId);

                    if (null !== $objInstructor) {
                        if ('' !== $objInstructor->email) {
                            if (Validator::isEmail($objInstructor->email)) {
                                $options['tl_user-'.$objInstructor->id] = $objInstructor->firstname.' '.$objInstructor->lastname.' (Leiter)';
                            }
                        }
                    }
                }
            }

            // Then get event participants
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? ORDER BY stateOfSubscription, firstname')->execute(Input::get('eventId'));

            while ($objDb->next()) {
                if (Validator::isEmail($objDb->email)) {
                    $arrSubscriptionStates = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'];

                    if (empty($arrSubscriptionStates) || !\is_array($arrSubscriptionStates)) {
                        throw new \Exception('$GLOBALS["TL_CONFIG"]["SAC-EVENT-TOOL-CONFIG"]["MEMBER-SUBSCRIPTION-STATE"] not found. Please check the config file.');
                    }

                    $memberState = (string) $objDb->stateOfSubscription;
                    $memberState = \in_array($memberState, $arrSubscriptionStates, true) ? $memberState : 'subscription-state-undefined';
                    $strLabel = $GLOBALS['TL_LANG']['tl_calendar_events_member'][$memberState] ?? $memberState;
                    $options['tl_calendar_events_member-'.$objDb->id] = $objDb->firstname.' '.$objDb->lastname.' ('.$strLabel.')';
                }
            }

            $GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['emailRecipients']['options'] = $options;

            // Send E-Mail
            if ('tl_calendar_events_member' === Input::post('FORM_SUBMIT') && isset($_POST['saveNclose'])) {
                $arrRecipients = [];

                foreach (Input::post('emailRecipients') as $key) {
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

                // Send e-mail
                if (!Validator::isEmail(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL'))) {
                    throw new \Exception('Please set a valid SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL Address in the Contao Backend Settings. Error in '.__METHOD__.' LINE: '.__LINE__);
                }

                $objEmail = Notification::findOneByType('default_email');

                // Use terminal42/notification_center
                if (null !== $objEmail) {
                    // Set token array
                    $arrTokens = [
                        'email_sender_name' => html_entity_decode((string) Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME')),
                        'email_sender_email' => Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL'),
                        'reply_to' => $this->User->email,
                        'email_subject' => html_entity_decode((string) Input::post('emailSubject')),
                        'email_text' => html_entity_decode((string) strip_tags(Input::post('emailText'))),
                        'attachment_tokens' => '',
                    ];

                    if (Input::post('emailSendCopy')) {
                        $arrTokens['recipient_bcc'] = $this->User->email;
                    }

                    $arrFiles = [];

                    // Add attachment
                    if (Input::post('addEmailAttachment')) {
                        if ('' !== Input::post('emailAttachment')) {
                            $arrUUID = explode(',', Input::post('emailAttachment'));

                            if (!empty($arrUUID) && \is_array($arrUUID)) {
                                foreach ($arrUUID as $uuid) {
                                    $objFile = FilesModel::findByUuid($uuid);

                                    if (null !== $objFile) {
                                        if (is_file(TL_ROOT.'/'.$objFile->path)) {
                                            $arrFiles[] = $objFile->path;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $strAttachments = implode(',', $arrFiles);

                    if ('' !== $strAttachments) {
                        $arrTokens['attachment_tokens'] = $strAttachments;
                    }

                    $arrRecipients = array_unique($arrRecipients);

                    if (\count($arrRecipients) > 0) {
                        $arrTokens['send_to'] = implode(',', $arrRecipients);
                        $objEmail->send($arrTokens, 'de');
                    }
                }
            }
        }
    }

    /**
     * onload_callback onloadCallbackExportMemberlist
     * CSV-export of all members of a certain event.
     */
    public function onloadCallbackExportMemberlist(DataContainer $dc): void
    {
        if ('onloadCallbackExportMemberlist' === Input::get('action') && Input::get('id') > 0) {
            // Create empty document
            $csv = Writer::createFromString('');

            // Set encoding from utf-8 to iso-8859-15 (windows)
            $encoder = (new CharsetConverter())
                ->outputEncoding('iso-8859-15')
            ;
            $csv->addFormatter($encoder);

            // Set delimiter
            $csv->setDelimiter(';');

            // Selected fields
            $arrFields = ['id', 'stateOfSubscription', 'addedOn', 'carInfo', 'ticketInfo', 'notes', 'instructorNotes', 'bookingType', 'sacMemberId', 'firstname', 'lastname', 'gender', 'dateOfBirth', 'foodHabits', 'street', 'postal', 'city', 'mobile', 'email', 'emergencyPhone', 'emergencyPhoneName', 'hasParticipated'];

            // Insert headline first
            Controller::loadLanguageFile('tl_calendar_events_member');

            $arrHeadline = array_map(
                static function ($field) {
                    return $GLOBALS['TL_LANG']['tl_calendar_events_member'][$field][0] ?? $field;
                },
                $arrFields
            );
            $csv->insertOne($arrHeadline);

            $objEvent = CalendarEventsModel::findByPk(Input::get('id'));
            $objEventMember = Database::getInstance()
                ->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? ORDER BY lastname, firstname')
                ->execute(Input::get('id'))
            ;

            while ($objEventMember->next()) {
                $arrRow = [];

                foreach ($arrFields as $field) {
                    $value = html_entity_decode((string) $objEventMember->{$field});

                    if ('stateOfSubscription' === $field) {
                        $arrRow[] = '' !== $GLOBALS['TL_LANG']['tl_calendar_events_member'][$value] ? $GLOBALS['TL_LANG']['tl_calendar_events_member'][$value] : $value;
                    } elseif ('gender' === $field) {
                        $arrRow[] = '' !== $GLOBALS['TL_LANG']['MSC'][$value] ? $GLOBALS['TL_LANG']['MSC'][$value] : $value;
                    } elseif ('addedOn' === $field) {
                        $arrRow[] = Date::parse(Config::get('datimFormat'), $value);
                    } elseif ('dateOfBirth' === $field) {
                        $arrRow[] = Date::parse(Config::get('dateFormat'), $value);
                    } else {
                        $arrRow[] = $value;
                    }
                }
                $csv->insertOne($arrRow);
            }

            // Sanitize filename
            $filename = $name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($objEvent->title)).'.csv';

            $csv->output($filename);
            exit;
        }
    }

    /**
     * onload_callback
     * Delete orphaned records.
     */
    public function reviseTable(): void
    {
        $reload = false;

        // Delete orphaned records
        $objStmt = $this->Database->prepare('SELECT id FROM tl_calendar_events_member AS em WHERE em.sacMemberId > ? AND em.tstamp > ? AND NOT EXISTS (SELECT * FROM tl_member AS m WHERE em.sacMemberId = m.sacMemberId)')->execute(0, 0);

        if ($objStmt->numRows) {
            $arrIDS = $objStmt->fetchEach('id');
            $objStmt2 = $this->Database->execute('DELETE FROM tl_calendar_events_member WHERE id IN('.implode(',', $arrIDS).')');

            if ($objStmt2->affectedRows > 0) {
                $reload = true;
            }
        }

        // Delete event members without sacMemberId that are not related to an event
        $objStmt = $this->Database->prepare('SELECT id FROM tl_calendar_events_member AS em WHERE (em.sacMemberId < ? OR em.sacMemberId = ?) AND tstamp > ? AND NOT EXISTS (SELECT * FROM tl_calendar_events AS e WHERE em.eventId = e.id)')->execute(1, '', 0);

        if ($objStmt->numRows) {
            $arrIDS = $objStmt->fetchEach('id');
            $objStmt2 = $this->Database->execute('DELETE FROM tl_calendar_events_member WHERE id IN('.implode(',', $arrIDS).')');

            if ($objStmt2->affectedRows > 0) {
                $reload = true;
            }
        }

        if ($reload) {
            $this->reload();
        }
    }

    public function listSections(): array
    {
        Controller::loadLanguageFile('tl_member');
        $ids = Config::get('SAC_EVT_SAC_SECTION_IDS');

        if ('' !== $ids) {
            $arrIds = explode(',', $ids);
        }

        $arrOptions = [];

        foreach ($arrIds as $id) {
            $arrOptions[$id] = $GLOBALS['TL_LANG']['tl_member']['section'][$id] ?: $id;
        }

        return $arrOptions;
    }

    /**
     * This method is used when an instructor signs in a member manualy.
     */
    public function setContaoMemberIdFromSacMemberId(DataContainer $dc): void
    {
        $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE tl_calendar_events_member.contaoMemberId < ? AND tl_calendar_events_member.sacMemberId > ? AND tl_calendar_events_member.sacMemberId IN (SELECT sacMemberId FROM tl_member)')->execute(1, 0);

        while ($objDb->next()) {
            $objMemberModel = MemberModel::findOneBySacMemberId($objDb->sacMemberId);

            if (null !== $objMemberModel) {
                $set = [
                    'contaoMemberId' => $objMemberModel->id,
                    'gender' => $objMemberModel->gender,
                    'firstname' => $objMemberModel->firstname,
                    'lastname' => $objMemberModel->lastname,
                    'street' => $objMemberModel->street,
                    'email' => $objMemberModel->email,
                    'postal' => $objMemberModel->postal,
                    'city' => $objMemberModel->city,
                    'mobile' => $objMemberModel->mobile,
                ];
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($objDb->id);
            }
        }
    }

    /**
     * @param $varValue
     */
    public function saveCallbackSacMemberId($varValue, DataContainer $dc)
    {
        // Set correct contaoMemberId if there is a sacMemberId
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            if ('' !== $varValue) {
                $objMemberModel = MemberModel::findOneBySacMemberId($varValue);

                if (null !== $objMemberModel) {
                    $set = [
                        'contaoMemberId' => $objMemberModel->id,
                    ];
                    $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
                } else {
                    $varValue = '';
                    $set = [
                        'sacMemberId' => '',
                        'contaoMemberId' => '',
                    ];
                    $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
                }
            } else {
                $set = [
                    'sacMemberId' => '',
                    'contaoMemberId' => '',
                ];
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
            }
        }

        return $varValue;
    }

    /**
     * @param $varValue
     */
    public function saveCallbackStateOfSubscription($varValue, DataContainer $dc)
    {
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            $objEvent = CalendarEventsModel::findByPk($objEventMemberModel->eventId);

            if (null !== $objEvent && $objEventMemberModel->stateOfSubscription !== $varValue) {
                // Check if member has already booked at the same time
                $objMember = MemberModel::findOneBySacMemberId($objEventMemberModel->sacMemberId);

                if ('subscription-accepted' === $varValue && null !== $objMember && CalendarEventsHelper::areBookingDatesOccupied($objEvent, $objMember)) {
                    $_SESSION['addError'] = 'Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht bestätigt serden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde.';
                    $varValue = $objEventMemberModel->stateOfSubscription;
                } elseif (Validator::isEmail($objEventMemberModel->email)) {
                    // Use terminal42/notification_center
                    $objNotification = Notification::findOneByType('onchange_state_of_subscription');

                    if (null !== $objNotification) {
                        $arrTokens = [
                            'participant_state_of_subscription' => html_entity_decode((string) $GLOBALS['TL_LANG']['tl_calendar_events_member'][$varValue]),
                            'event_name' => html_entity_decode((string) $objEvent->title),
                            'participant_name' => html_entity_decode($objEventMemberModel->firstname.' '.$objEventMemberModel->lastname),
                            'participant_email' => $objEventMemberModel->email,
                            'event_link_detail' => 'https://'.Environment::get('host').'/'.Events::generateEventUrl($objEvent),
                        ];
                        $objNotification->send($arrTokens, 'de');
                    }
                }
            }
        }

        return $varValue;
    }

    public function onsubmitCallback(DataContainer $dc): void
    {
        // Set correct contaoMemberId if there is a sacMemberId
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            // Set correct addedOn timestamp
            if (!$objEventMemberModel->addedOn) {
                $set = [
                    'addedOn' => time(),
                ];
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
            }

            $eventId = $objEventMemberModel->eventId > 0 ? $objEventMemberModel->eventId : CURRENT_ID;

            $objEventModel = CalendarEventsModel::findByPk($eventId);

            if (null !== $objEventModel) {
                // Set correct event title and eventId
                $set = [
                    'eventName' => $objEventModel->title,
                    'eventId' => $eventId,
                ];
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
            }
        }
    }

    /**
     * @param DC $dc
     */
    public function setStateOfSubscription(DataContainer $dc): void
    {
        // start session
        session_start();

        if ('refuseWithEmail' === Input::get('call')) {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['refuseWithEmail'];

            return;
        }

        if ('acceptWithEmail' === Input::get('call')) {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['acceptWithEmail'];

            return;
        }

        if ('addToWaitlist' === Input::get('call')) {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['addToWaitlist'];

            return;
        }

        if (isset($_SESSION['addError'])) {
            Message::addError($_SESSION['addError']);
            unset($_SESSION['addError']);
        }

        if (isset($_SESSION['addInfo'])) {
            Message::addConfirmation($_SESSION['addInfo']);
            unset($_SESSION['addInfo']);
        }

        if (isset($_POST['refuseWithEmail'])) {
            // Show another palette
            $this->redirect($this->addToUrl('call=refuseWithEmail'));
        }

        if (isset($_POST['acceptWithEmail'])) {
            // Show another palette
            $this->redirect($this->addToUrl('call=acceptWithEmail'));
        }

        if (isset($_POST['addToWaitlist'])) {
            // Show another palette
            $this->redirect($this->addToUrl('call=addToWaitlist'));
        }

        if (isset($_POST['refuseWithoutEmail'])) {
            $objRegistration = CalendarEventsMemberModel::findByPk(Input::get('id'));

            if (null !== $objRegistration) {
                $set = ['stateOfSubscription' => 'subscription-refused'];
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                $_SESSION['addInfo'] = 'Der Benutzer wurde ohne E-Mail von der Event-Teilnahme abgelehnt und muss noch darüber informiert werden.';
            }

            $this->reload();
        }

        if (isset($_POST['acceptWithoutEmail'])) {
            $objRegistration = CalendarEventsMemberModel::findByPk(Input::get('id'));

            if (null !== $objRegistration) {
                $set = ['stateOfSubscription' => 'subscription-accepted'];
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                $_SESSION['addInfo'] = 'Der Benutzer wurde ohne E-Mail zum Event zugelassen und muss noch darüber informiert werden.';
            }

            $this->reload();
        }

        if (isset($_POST['addToWaitlist'])) {
            $objRegistration = CalendarEventsMemberModel::findByPk(Input::get('id'));

            if (null !== $objRegistration) {
                $set = ['stateOfSubscription' => 'subscription-waitlisted'];
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                $_SESSION['addInfo'] = 'Der Benutzer wurde ohne E-Mail auf die Warteliste gesetzt und muss noch darüber informiert werden.';
            }

            $this->reload();
        }
    }

    public function setGlobalOperations(DataContainer $dc): void
    {
        // Remove edit_all (mehrere bearbeiten) button
        if (!$this->User->admin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['all']);
        }

        // Generate href for $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']
        // Generate href for  $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']

        $blnAllowTourReportButton = false;
        $blnAllowInstructorInvoiceButton = false;

        // Get the refererId
        $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');

        // Get the backend module name
        $module = Input::get('do');

        $eventId = Input::get('id');
        $objEvent = CalendarEventsModel::findByPk($eventId);

        if (null !== $objEvent) {
            // Check if backend user is allowed
            if (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objEvent->id) || $objEvent->registrationGoesTo === $this->User->id) {
                if ('tour' === $objEvent->eventType || 'lastMinuteTour' === $objEvent->eventType) {
                    $url = sprintf('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&call=writeTourReport&rt=%s&ref=%s', $eventId, REQUEST_TOKEN, $refererId);
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']['href'] = $url;
                    $blnAllowTourReportButton = true;

                    $url = sprintf('contao?do=%s&table=tl_calendar_events_instructor_invoice&id=%s&rt=%s&ref=%s', $module, Input::get('id'), REQUEST_TOKEN, $refererId);
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']['href'] = $url;
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
     * Add an image to each record.
     *
     * @param array  $row
     * @param string $label
     * @param array  $args
     *
     * @return array
     */
    public function addIcon($row, $label, DataContainer $dc, $args)
    {
        $icon = 'icons/'.$row['stateOfSubscription'].'.svg';
        $args[0] = sprintf('<div><img src="%s/%s" alt="%s" width="16" height=16"></div>', Config::get('SAC_EVT_ASSETS_DIR'), $icon, $row['stateOfSubscription']);

        return $args;
    }

    /**
     * @return string
     */
    public function inputFieldCallbackDashboard(DataContainer $dc)
    {
        $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objRegistration) {
            $objTemplate = new BackendTemplate('be_calendar_events_registration_dashboard');
            $objTemplate->objRegistration = $objRegistration;
            $objTemplate->stateOfSubscription = $objRegistration->stateOfSubscription;
            $objEvent = CalendarEventsModel::findByPk($objRegistration->eventId);

            if (null !== $objEvent) {
                $objTemplate->objEvent = $objEvent;

                if (!$objRegistration->hasParticipated && '' !== $objRegistration->email) {
                    if (Validator::isEmail($objRegistration->email)) {
                        $objTemplate->showEmailButtons = true;
                    }
                }

                return $objTemplate->parse();
            }
        }

        return '';
    }

    /**
     * @throws Exception
     *
     * @return string
     */
    public function inputFieldCallbackNotifyMemberAboutSubscriptionState(DataContainer $dc)
    {
        // Start session
        session_start();

        // Build action array first
        $arrActions = [
            'acceptWithEmail' => [
                'formId' => 'subscription-accepted-form',
                'headline' => 'Zusage zum Event',
                'stateOfSubscription' => 'subscription-accepted',
                'sessionInfoText' => 'Dem Benutzer wurde mit einer E-Mail eine Zusage für diesen Event versandt.',
                'emailTemplate' => 'be_email_templ_accept_registration',
                'emailSubject' => 'Zusage für %s',
            ],
            'addToWaitlist' => [
                'formId' => 'subscription-waitlisted-form',
                'headline' => 'Auf Warteliste setzen',
                'stateOfSubscription' => 'subscription-waitlisted',
                'sessionInfoText' => 'Dem Benutzer wurde auf die Warteliste gesetzt und mit einer E-Mail darüber informiert.',
                'emailTemplate' => 'be_email_templ_added_to_waitlist',
                'emailSubject' => 'Auf Warteliste für %s',
            ],
            'refuseWithEmail' => [
                'formId' => 'subscription-refused-form',
                'headline' => 'Absage mitteilen',
                'stateOfSubscription' => 'subscription-refused',
                'sessionInfoText' => 'Dem Benutzer wurde mit einer E-Mail eine Absage versandt.',
                'emailTemplate' => 'be_email_templ_refuse_registration',
                'emailSubject' => 'Absage für %s',
            ],
        ];

        if (empty(Input::get('call')) || !\is_array($arrActions[Input::get('call')]) || empty($arrActions[Input::get('call')])) {
            $_SESSION['addInfo'] = 'Es ist ein Fehler aufgetreten.';
            $this->redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.Input::get('id').'&act=edit&rt='.Input::get('rt'));
        }

        // Set action array
        $arrAction = $arrActions[Input::get('call')];

        // Generate form fields
        $objForm = new Form(
            $arrAction['formId'],
            'POST',
            static function ($objHaste) {
                return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
            }
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
        if ('tl_calendar_events_member' === Input::post('FORM_SUBMIT')) {
            if ('' !== Input::post('subject') && '' !== Input::post('text')) {
                $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);

                if (null !== $objRegistration) {
                    if (!Validator::isEmail(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL'))) {
                        throw new \Exception('Please set a valid SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL Address in the Contao Backend Settings. Error in '.__METHOD__.' LINE: '.__LINE__);
                    }
                    $objEmail = Notification::findOneByType('default_email');

                    // Use terminal42/notification_center
                    if (null !== $objEmail) {
                        // Set token array
                        $arrTokens = [
                            'email_sender_name' => html_entity_decode((string) html_entity_decode((string) Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME'))),
                            'email_sender_email' => Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL'),
                            'send_to' => $objRegistration->email,
                            'reply_to' => $this->User->email,
                            // 'recipient_cc'       => '',
                            // 'recipient_bcc'      => '',
                            'email_subject' => html_entity_decode((string) Input::post('subject')),
                            'email_text' => html_entity_decode((string) strip_tags(Input::post('text'))),
                            'email_html' => html_entity_decode(''),
                        ];

                        // Check if member has already booked at the same time
                        $objMember = MemberModel::findOneBySacMemberId($objRegistration->sacMemberId);
                        $objEvent = CalendarEventsModel::findByPk($objRegistration->eventId);

                        if ('acceptWithEmail' === Input::get('call') && null !== $objMember && null !== $objEvent && CalendarEventsHelper::areBookingDatesOccupied($objEvent, $objMember)) {
                            $_SESSION['addError'] = 'Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht bestätigt serden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde.';
                        }
                        // Send email
                        elseif (Validator::isEmail($objRegistration->email)) {
                            $objEmail->send($arrTokens, 'de');
                            $set = ['stateOfSubscription' => $arrAction['stateOfSubscription']];
                            $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                            $_SESSION['addInfo'] = $arrAction['sessionInfoText'];
                        } else {
                            $_SESSION['addInfo'] = 'Es ist ein Fehler aufgetreten. Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.';
                        }
                    }
                }
                $this->redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.Input::get('id').'&act=edit&rt='.Input::get('rt'));
            } else {
                // Add value to ffields
                if ('' !== Input::post('subject')) {
                    $objForm->getWidget('subject')->value = Input::post('subject');
                }

                if ('' !== Input::post('text')) {
                    $objForm->getWidget('text')->value = strip_tags(Input::post('text'));
                }

                // Generate template
                $objTemplate = new BackendTemplate('be_calendar_events_registration_email');
                $objTemplate->headline = $arrAction['headline'];
                $objTemplate->form = $objForm;

                return $objTemplate->parse();
            }
        } else { // Prefill form
            // Get the registration object
            $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);

            if (null !== $objRegistration) {
                // Get the event object
                $objEvent = $objRegistration->getRelated('eventId');

                // Get event dates as a comma separated string
                $eventDates = CalendarEventsHelper::getEventTimestamps($objEvent);
                $strDates = implode(', ', array_map(
                    static function ($tstamp) {
                        return Date::parse(Config::get('dateFormat'), $tstamp);
                    },
                    $eventDates
                ));

                // Build token array
                $arrTokens = [
                    'participantFirstname' => $objRegistration->firstname,
                    'participantLastname' => $objRegistration->lastname,
                    'eventName' => $objEvent->title,
                    'courseId' => $objEvent->courseId,
                    'eventType' => $objEvent->eventType,
                    'eventUrl' => Events::generateEventUrl($objEvent, true),
                    'eventDates' => $strDates,
                    'instructorName' => $this->User->name,
                    'instructorFirstname' => $this->User->firstname,
                    'instructorLastname' => $this->User->lastname,
                    'instructorPhone' => $this->User->phone,
                    'instructorMobile' => $this->User->mobile,
                    'instructorStreet' => $this->User->street,
                    'instructorPostal' => $this->User->postal,
                    'instructorCity' => $this->User->city,
                    'instructorEmail' => $this->User->email,
                ];

                if ('acceptWithEmail' === Input::get('call') && $objEvent->customizeEventRegistrationConfirmationEmailText && '' !== $objEvent->customEventRegistrationConfirmationEmailText) {
                    // Only for acceptWithEmail!!!
                    // Replace tags for custom notification set in the events settings (tags can be used case insensitive!)
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
     * @param $href
     * @param $label
     * @param $title
     * @param $class
     * @param $attributes
     * @param $table
     *
     * @return string
     */
    public function buttonCbBackToEventSettings($href, $label, $title, $class, $attributes, $table)
    {
        $href = ampersand('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s&ref=%s');
        $eventId = Input::get('id');
        $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');
        $href = sprintf($href, $eventId, REQUEST_TOKEN, $refererId);

        return sprintf(' <a href="%s" class="%s" title="%s" %s>%s</a>', $href, $class, $title, $attributes, $label);
    }

    /**
     * buttons_callback.
     *
     * @param $arrButtons
     *
     * @return mixed
     */
    public function buttonsCallback($arrButtons, DataContainer $dc)
    {
        // Remove all buttons
        if ('refuseWithEmail' === Input::get('call') || 'acceptWithEmail' === Input::get('call') || 'addToWaitlist' === Input::get('call')) {
            $arrButtons = [];
        }

        if ('sendEmail' === Input::get('call')) {
            $arrButtons['saveNclose'] = '<button type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c">E-Mail absenden</button>';
            unset($arrButtons['save']);
        }

        unset($arrButtons['saveNback'], $arrButtons['saveNduplicate'], $arrButtons['saveNcreate']);

        return $arrButtons;
    }

    /**
     * Return the "toggle visibility" button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (\strlen((string) Input::get('tid'))) {
            $this->toggleVisibility(Input::get('tid'), 1 === (int) Input::get('state'), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }

        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin) {
            // Full access to admins
        } elseif (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, CURRENT_ID)) {
            // User is allowed to edit table
        } else {
            $id = Input::get('id');
            $objEvent = CalendarEventsModel::findByPk($id);

            if (null !== $objEvent) {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);

                if (!\in_array($this->User->id, $arrAuthors, false)) {
                    return '';
                }
            }
        }

        $href .= '&amp;tid='.$row['id'].'&amp;state='.$row['disable'];

        if ($row['disable']) {
            $icon = 'invisible.svg';
        }

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label, 'data-state="'.($row['disable'] ? 0 : 1).'"').'</a> ';
    }

    /**
     * Disable/enable a registration.
     *
     * @param int           $intId
     * @param bool          $blnVisible
     * @param DataContainer $dc
     *
     * @throws AccessDeniedException
     */
    public function toggleVisibility($intId, $blnVisible, DataContainer $dc = null): void
    {
        // Set the ID and action
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        if ($dc) {
            $dc->id = $intId; // see #8043
        }

        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin) {
            // Allow
        } elseif (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, CURRENT_ID)) {
            // User is allowed to edit table
        } else {
            $id = Input::get('id');
            $objEvent = CalendarEventsModel::findByPk($id);

            if (null !== $objEvent) {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);

                if (!\in_array($this->User->id, $arrAuthors, false)) {
                    throw new AccessDeniedException('Not enough permissions to activate/deactivate registration ID '.$id.'.');
                }
            }
        }

        $objVersions = new Versions('tl_calendar_events_member', $intId);
        $objVersions->initialize();

        // Reverse the logic (members have disabled=1)
        $blnVisible = !$blnVisible;

        // Trigger the save_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['disable']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['disable']['save_callback'] as $callback) {
                if (\is_array($callback)) {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, ($dc ?: $this));
                } elseif (\is_callable($callback)) {
                    $blnVisible = $callback($blnVisible, ($dc ?: $this));
                }
            }
        }

        $time = time();

        // Update the database
        $this->Database->prepare("UPDATE tl_calendar_events_member SET tstamp=$time, disable='".($blnVisible ? '1' : '')."' WHERE id=?")
            ->execute($intId)
        ;

        $objVersions->create();
    }
}
