<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

use Contao\Config;
use NotificationCenter\Model\Notification;


/**
 * Class tl_calendar_events_member
 */
class tl_calendar_events_member extends Backend
{


    /**
     * Import the back end user object
     */
    public function __construct()
    {
        $this->import('BackendUser', 'User');

        parent::__construct();

        // Set correct referer
        if (Input::get('do') === 'sac_calendar_events_tool' && Input::get('ref') != '')
        {
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicsaceventtool/js/tl_calendar_events_member.js';
        }

        // Set correct referer
        if (Input::get('do') === 'sac_calendar_events_tool' && Input::get('ref') != '')
        {
            $objSession = static::getContainer()->get('session');
            $ref = Input::get('ref');
            $session = $objSession->get('referer');
            if (isset($session[$ref]['tl_calendar_container']))
            {
                $session[$ref]['tl_calendar_container'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_container']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar']))
            {
                $session[$ref]['tl_calendar'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar_events']))
            {
                $session[$ref]['tl_calendar_events'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar_events_instructor_invoice']))
            {
                $session[$ref]['tl_calendar_events_instructor_invoice'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events_instructor_invoice']);
                $objSession->set('referer', $session);
            }
        }


        $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=?')->limit(1)->execute(Input::get('id'));
        if ($objDb->numRows)
        {
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']['href'] = str_replace('sendEmail', 'sendEmail&id=' . $objDb->id . '&eventId=' . Input::get('id'), $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']['href']);
        }
        else
        {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']);
        }

        if (Input::get('call') === 'sendEmail')
        {
            // Delete E-Mail fields
            $opt = array(
                'emailRecipients'    => '',
                'emailSubject'       => '',
                'emailText'          => '',
                'emailSendCopy'      => '',
                'addEmailAttachment' => '',
                'emailAttachment'    => '',
            );
            $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($opt)->execute(Input::get('id'));
        }

    }


    /**
     * OnLoad Callback
     * @param DC_Table $dc
     * @throws Exception
     */
    public function onloadCallback(DC_Table $dc)
    {
        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin)
        {
            // Allow
        }
        elseif (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, CURRENT_ID))
        {
            // User is allowed to edit table
        }
        else
        {
            if (!Input::get('act') && Input::get('id'))
            {
                $objEvent = CalendarEventsModel::findByPk(Input::get('id'));
                if ($objEvent !== null)
                {
                    $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                    $arrRegistrationGoesTo = StringUtil::deserialize($objEvent->registrationGoesTo, true);
                    if (!in_array($this->User->id, $arrAuthors) && !in_array($this->User->id, $arrRegistrationGoesTo))
                    {
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'];
                        unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['all']);
                        unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['downloadEventMemberList']);
                        unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']);
                        unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['edit']);
                        unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['delete']);
                        unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['toggleStateOfParticipation']);
                    }
                }
            }

            if (Input::get('act') === 'delete' || Input::get('act') === 'toggle' || Input::get('act') === 'edit' || Input::get('act') === 'select')
            {
                $id = strlen(Input::get('id')) ? Input::get('id') : CURRENT_ID;
                if (Input::get('act') === 'select')
                {
                    $objEvent = CalendarEventsModel::findByPk($id);
                }
                else
                {
                    $objEvent = CalendarEventsMemberModel::findByPk($id)->getRelated('eventId');
                }


                if ($objEvent !== null)
                {
                    $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                    $arrRegistrationGoesTo = StringUtil::deserialize($objEvent->registrationGoesTo, true);
                    if (!in_array($this->User->id, $arrAuthors) && !in_array($this->User->id, $arrRegistrationGoesTo))
                    {
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'];
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'];
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notSortable'];
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCopyable'];
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'];

                        Message::addError('Du hast nicht die nötigen Berechtigungen um Datensätze zu bearbeiten, zu löschen oder hinzuzufügen.');
                        $this->redirect('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=' . $objEvent->id);
                    }
                }
            }
        }


        // Download the registration list as a docx file
        if (Input::get('act') === 'downloadEventMemberList')
        {
            $objMemberList = new Markocupic\SacEventToolBundle\EventRapport();
            $objMemberList->generateMemberList(Input::get('id'), 'docx');
            exit;
        }


        if (Input::get('call') === 'sendEmail')
        {

            // Set Recipient Array for the checkbox list
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['sendEmail'];
            $options = array();

            // First get instructors
            $objEvent = \Contao\CalendarEventsModel::findByPk(Input::get('eventId'));
            if ($objEvent !== null)
            {
                $arrGuideIDS = \Markocupic\SacEventToolBundle\CalendarSacEvents::getInstructorsAsArray($objEvent->id);
                foreach ($arrGuideIDS as $userId)
                {
                    $objInstructor = \Contao\UserModel::findByPk($userId);
                    if ($objInstructor !== null)
                    {
                        if ($objInstructor->email !== '')
                        {
                            if (\Contao\Validator::isEmail($objInstructor->email))
                            {
                                $options['tl_user-' . $objInstructor->id] = $objInstructor->firstname . ' ' . $objInstructor->lastname . ' (Leiter)';
                            }
                        }
                    }
                }
            }

            // Then get event participants
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? ORDER BY stateOfSubscription, firstname')->execute(Input::get('eventId'));
            while ($objDb->next())
            {
                if (\Contao\Validator::isEmail($objDb->email))
                {
                    if ($objDb->stateOfSubscription === 'subscription-not-confirmed')
                    {
                        $options['tl_calendar_events_member-' . $objDb->id] = $objDb->firstname . ' ' . $objDb->lastname . ' (unbest&auml;tigt)';

                    }
                    elseif ($objDb->stateOfSubscription === 'subscription-refused')
                    {
                        $options['tl_calendar_events_member-' . $objDb->id] = $objDb->firstname . ' ' . $objDb->lastname . ' (Teilnahme abgelehnt)';
                    }
                    else
                    {
                        $options['tl_calendar_events_member-' . $objDb->id] = $objDb->firstname . ' ' . $objDb->lastname . ' (Teilnahme best&auml;tigt)';
                    }
                }
            }

            $GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['emailRecipients']['options'] = $options;

            // Send E-Mail
            if (Input::post('FORM_SUBMIT') === 'tl_calendar_events_member' && isset($_POST['saveNclose']))
            {
                $arrRecipients = array();
                foreach (Input::post('emailRecipients') as $key)
                {
                    if (strpos($key, 'tl_user-') !== false)
                    {
                        $id = str_replace('tl_user-', '', $key);
                        $objInstructor = \Contao\UserModel::findByPk($id);
                        if ($objInstructor !== null)
                        {
                            if (\Contao\Validator::isEmail($objInstructor->email))
                            {
                                $arrRecipients[] = $objInstructor->email;
                            }
                        }
                    }
                    elseif (strpos($key, 'tl_calendar_events_member-') !== false)
                    {
                        $id = str_replace('tl_calendar_events_member-', '', $key);
                        $objEventMember = \Contao\CalendarEventsMemberModel::findByPk($id);
                        if ($objEventMember !== null)
                        {
                            if (\Contao\Validator::isEmail($objEventMember->email))
                            {
                                $arrRecipients[] = $objEventMember->email;
                            }
                        }
                    }
                }

                // Send e-mail
                if (!\Contao\Validator::isEmail(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL')))
                {
                    throw new \Exception('Please set a valid SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL Address in the Contao Backend Settings. Error in ' . __METHOD__ . ' LINE: ' . __LINE__);
                }
                $objEmail = new Email();
                $objEmail->from = Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL');
                $objEmail->fromName = html_entity_decode(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME'));
                $objEmail->replyTo($this->User->email);
                $objEmail->subject = Input::post('emailSubject');
                $objEmail->text = Input::post('emailText');
                if (Input::post('emailSendCopy'))
                {
                    $objEmail->sendBcc($this->User->email);
                }

                // Add attachment
                if (Input::post('addEmailAttachment'))
                {
                    if (Input::post('emailAttachment') !== '')
                    {
                        $arrUUID = explode(',', Input::post('emailAttachment'));
                        if (is_array($arrUUID) && !empty($arrUUID))
                        {
                            foreach ($arrUUID as $uuid)
                            {
                                $objFile = \Contao\FilesModel::findByUuid($uuid);
                                if ($objFile !== null)
                                {
                                    if (is_file(TL_ROOT . '/' . $objFile->path))
                                    {
                                        $objEmail->attachFile(TL_ROOT . '/' . $objFile->path);
                                    }
                                }
                            }
                        }
                    }
                }
                $arrRecipients = array_unique($arrRecipients);
                if (count($arrRecipients) > 0)
                {
                    $objEmail->sendTo($arrRecipients);
                }
            }
        }
    }

    /**
     * onload_callback
     * Delete orphaned records
     */
    public function reviseTable()
    {
        $reload = false;

        // Delete orphaned records
        $objStmt = $this->Database->prepare('DELETE FROM tl_calendar_events_member WHERE tl_calendar_events_member.sacMemberId > ? AND tstamp > ? AND NOT EXISTS (SELECT * FROM tl_member WHERE tl_calendar_events_member.sacMemberId = tl_member.sacMemberId)')->execute(0, 0);
        if ($objStmt->affectedRows > 0)
        {
            $reload = true;
        }

        // Delete event members without sacMemberId that are not related to an event
        $objStmt = $this->Database->prepare('DELETE FROM tl_calendar_events_member WHERE (tl_calendar_events_member.sacMemberId < ? OR tl_calendar_events_member.sacMemberId = ?) AND tstamp > ? AND NOT EXISTS (SELECT * FROM tl_calendar_events WHERE tl_calendar_events_member.eventId = tl_calendar_events.id)')->execute(1, '', 0);
        if ($objStmt->affectedRows > 0)
        {
            $reload = true;
        }

        if ($reload)
        {
            $this->reload();
        }
    }

    /**
     * This method is used when an instructor signs in a member manualy
     * @param DC_Table $dc
     */
    public function setContaoMemberIdFromSacMemberId(DC_Table $dc)
    {
        $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE tl_calendar_events_member.contaoMemberId < ? AND tl_calendar_events_member.sacMemberId > ? AND tl_calendar_events_member.sacMemberId IN (SELECT sacMemberId FROM tl_member)')->execute(1, 0);
        while ($objDb->next())
        {
            $objMemberModel = MemberModel::findBySacMemberId($objDb->sacMemberId);
            if ($objMemberModel !== null)
            {
                $set = array(
                    'contaoMemberId' => $objMemberModel->id,
                    'gender'         => $objMemberModel->gender,
                    'firstname'      => $objMemberModel->firstname,
                    'lastname'       => $objMemberModel->lastname,
                    'street'         => $objMemberModel->street,
                    'email'          => $objMemberModel->email,
                    'postal'         => $objMemberModel->postal,
                    'city'           => $objMemberModel->city,
                    'phone'          => $objMemberModel->phone,
                );
                $objEventMember = $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($objDb->id);
            }
        }
    }

    /**
     * @param $varValue
     * @param DC_Table $dc
     */
    public function saveCallbackStateOfSubscription($varValue, DC_Table $dc)
    {
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);
        if ($objEventMemberModel !== null)
        {
            $objEvent = CalendarEventsModel::findByPk($objEventMemberModel->eventId);
            if ($objEvent !== null && $objEventMemberModel->stateOfSubscription != $varValue)
            {
                if (Validator::isEmail($objEventMemberModel->email))
                {
                    // Use terminal42/notification_center
                    $objNotification = Notification::findOneByType('onchange_state_of_subscription');
                    if ($objNotification !== null)
                    {
                        $arrTokens = array(
                            'participant_state_of_subscription' => html_entity_decode($GLOBALS['TL_LANG']['tl_calendar_events_member'][$varValue]),
                            'event_name'                        => html_entity_decode($objEvent->title),
                            'participant_name'                  => html_entity_decode($objEventMemberModel->firstname . ' ' . $objEventMemberModel->lastname),
                            'participant_email'                 => $objEventMemberModel->email,
                            'event_link_detail'                 => 'https://' . Environment::get('host') . '/' . Events::generateEventUrl($objEvent),
                        );
                        $objNotification->send($arrTokens, 'de');
                        //Message::addInfo(sprintf('Der Teilnehmer "%s %s" wurde per E-Mail über die &Auml;nderung seines Teilnehmestatus benachrichtigt.', $objEventMemberModel->firstname, $objEventMemberModel->lastname));
                    }
                }
            }
        }

        return $varValue;
    }

    /**
     * @param DC_Table $dc
     */
    public function onsubmitCallback(DC_Table $dc)
    {
        // Set correct contaoMemberId if there is a sacMemberId
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);
        if ($objEventMemberModel !== null)
        {

            // Set correct addedOn timestamp
            if (!$objEventMemberModel->addedOn)
            {
                $objEventMemberModel->addedOn = time();
                $objEventMemberModel->save();
            }

            $eventId = $objEventMemberModel->eventId > 0 ? $objEventMemberModel->eventId : CURRENT_ID;

            $objEventModel = CalendarEventsModel::findByPk($eventId);
            if ($objEventModel !== null)
            {
                // Set correct event title and eventId
                $objEventMemberModel->eventName = $objEventModel->title;
                $objEventMemberModel->eventId = $eventId;


                if ($objEventMemberModel->sacMemberId != '')
                {
                    $objMemberModel = MemberModel::findBySacMemberId($objEventMemberModel->sacMemberId);
                    if ($objMemberModel !== null)
                    {
                        $objEventMemberModel->contaoMemberId = $objMemberModel->id;
                    }
                    else
                    {
                        $objEventMemberModel->sacMemberId = '';
                    }
                }
                $objEventMemberModel->save();
            }
            else
            {
                //$objEventMemberModel->delete();
            }
        }
    }

    /**
     * @param DC $dc
     */
    public function setStateOfSubscription(DC_Table $dc)
    {

        // start session
        session_start();

        if (Input::get('call') === 'refuseWithEmail')
        {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['refuseWithEmail'];
            return;
        }

        if (Input::get('call') === 'acceptWithEmail')
        {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['acceptWithEmail'];
            return;
        }

        if (Input::get('call') === 'addToWaitlist')
        {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['addToWaitlist'];
            return;
        }

        if (isset($_SESSION['addInfo']))
        {
            Message::addConfirmation($_SESSION['addInfo']);
            unset($_SESSION['addInfo']);
        }

        if (isset($_POST['refuseWithEmail']))
        {
            // Show another palette
            $this->redirect($this->addToUrl('call=refuseWithEmail'));
        }

        if (isset($_POST['acceptWithEmail']))
        {
            // Show another palette
            $this->redirect($this->addToUrl('call=acceptWithEmail'));
        }

        if (isset($_POST['addToWaitlist']))
        {
            // Show another palette
            $this->redirect($this->addToUrl('call=addToWaitlist'));
        }

        if (isset($_POST['refuseWithoutEmail']))
        {
            $objRegistration = CalendarEventsMemberModel::findByPk(Input::get('id'));
            if ($objRegistration !== null)
            {
                $set = array('stateOfSubscription' => 'subscription-refused');
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                $_SESSION['addInfo'] = 'Der Benutzer wurde ohne E-Mail von der Event-Teilnahme abgelehnt und muss noch darüber informiert werden.';
            }

            $this->reload();
        }

        if (isset($_POST['acceptWithoutEmail']))
        {
            $objRegistration = CalendarEventsMemberModel::findByPk(Input::get('id'));
            if ($objRegistration !== null)
            {
                $set = array('stateOfSubscription' => 'subscription-accepted');
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                $_SESSION['addInfo'] = 'Der Benutzer wurde ohne E-Mail zum Event zugelassen und muss noch darüber informiert werden.';
            }

            $this->reload();
        }

        if (isset($_POST['addToWaitlist']))
        {
            $objRegistration = CalendarEventsMemberModel::findByPk(Input::get('id'));
            if ($objRegistration !== null)
            {
                $set = array('stateOfSubscription' => 'subscription-waitlisted');
                $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                $_SESSION['addInfo'] = 'Der Benutzer wurde ohne E-Mail auf die Warteliste gesetzt und muss noch darüber informiert werden.';
            }

            $this->reload();
        }
    }

    /**
     * @param DC_Table $dc
     */
    public function setGlobalOperations(DC_Table $dc)
    {
        // Remove edit_all (mehrere bearbeiten) button
        if (!$this->User->admin)
        {
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
        $objEvent = \Contao\CalendarEventsModel::findByPk($eventId);
        if ($objEvent !== null)
        {
            // Check if backend user is allowed
            if (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objEvent->id) || $objEvent->registrationGoesTo === $this->User->id)
            {
                if ($objEvent->eventType === 'tour' || $objEvent->eventType === 'lastMinuteTour')
                {

                    $url = sprintf('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&call=writeTourReport&rt=%s&ref=%s', $eventId, REQUEST_TOKEN, $refererId);
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']['href'] = $url;
                    $blnAllowTourReportButton = true;

                    $url = sprintf('contao?do=%s&table=tl_calendar_events_instructor_invoice&id=%s&rt=%s&ref=%s', $module, Input::get('id'), REQUEST_TOKEN, $refererId);
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']['href'] = $url;
                    $blnAllowInstructorInvoiceButton = true;
                }
            }
        }

        if (!$blnAllowTourReportButton)
        {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']);
        }
        if (!$blnAllowInstructorInvoiceButton)
        {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']);
        }
    }

    /**
     * Add an image to each record
     * @param array $row
     * @param string $label
     * @param DataContainer $dc
     * @param array $args
     *
     * @return array
     */
    public function addIcon($row, $label, DataContainer $dc, $args)
    {
        $icon = 'icons/' . $row['stateOfSubscription'] . '.svg';
        $args[0] = sprintf('<div><img src="%s/%s" alt="%s" width="16" height=16"></div>', Config::get('SAC_EVT_ASSETS_DIR'), $icon, $row['stateOfSubscription']);
        return $args;
    }


    /**
     * @param DC_Table $dc
     * @return string
     */
    public function inputFieldCallbackDashboard(DC_Table $dc)
    {
        $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);
        if ($objRegistration !== null)
        {
            $objTemplate = new BackendTemplate('be_calendar_events_registration_dashboard');
            $objTemplate->stateOfSubscription = $objRegistration->stateOfSubscription;

            if (!$objRegistration->hasParticipated && $objRegistration->email != '')
            {
                if (\Contao\Validator::isEmail($objRegistration->email))
                {
                    $objTemplate->showEmailButtons = true;
                }
            }

            return $objTemplate->parse();
        }

    }


    /**
     * @param DC_Table $dc
     * @return string
     */
    public function inputFieldCallbackRefuseWithEmail(DC_Table $dc)
    {
        // Start session
        session_start();

        if (Input::post('FORM_SUBMIT') === 'tl_calendar_events_member')
        {
            if (Input::post('subject') != '' && Input::post('text') != '')
            {
                $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);
                if ($objRegistration !== null)
                {
                    if (!\Contao\Validator::isEmail(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL')))
                    {
                        throw new \Exception('Please set a valid SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL Address in the Contao Backend Settings. Error in ' . __METHOD__ . ' LINE: ' . __LINE__);
                    }
                    $objEmail = new Email();
                    $objEmail->from = Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL');
                    $objEmail->fromName = html_entity_decode(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME'));
                    $objEmail->replyTo($this->User->email);
                    $objEmail->subject = Input::post('subject');
                    $objEmail->text = strip_tags(Input::post('text'));

                    // Send copy to instructor
                    // $objEmail->sendBcc($this->User->email);

                    // Send email
                    if (Validator::isEmail($objRegistration->email))
                    {
                        $objEmail->sendTo($objRegistration->email);
                        $set = array('stateOfSubscription' => 'subscription-refused');
                        $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                        $_SESSION['addInfo'] = 'Dem Benutzer wurde mit einer E-Mail eine Absage versandt.';
                    }
                    else
                    {
                        $_SESSION['addInfo'] = 'Es ist ein Fehler aufgetreten. Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.';
                    }
                }
                $this->redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=' . Input::get('id') . '&act=edit&rt=' . Input::get('rt'));

            }
            else
            {
                // Prefill form
                $objTemplate = new BackendTemplate('be_calendar_events_registration_refuse_with_email');
                $objTemplate->emailSubject = Input::post('subject');
                $objTemplate->emailText = strip_tags(Input::post('text'));
                return $objTemplate->parse();
            }
        }

        $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);
        if ($objRegistration !== null)
        {
            $objEvent = $objRegistration->getRelated('eventId');

            // Build email text from template
            $objEmailTemplate = new BackendTemplate('be_email_templ_refuse_registration');
            $objEmailTemplate->firstname = $objRegistration->firstname;
            $objEmailTemplate->lastname = $objRegistration->lastname;
            $objEmailTemplate->eventname = $objEvent->title;
            $objEmailTemplate->courseId = $objEvent->courseId;
            $objEmailTemplate->eventType = $objEvent->eventType;
            $objEmailTemplate->nameGuide = $this->User->name;

            // Prefill form
            $objTemplate = new BackendTemplate('be_calendar_events_registration_refuse_with_email');

            // Get event type
            $eventType = (strlen($GLOBALS['TL_LANG']['MSC'][$objEvent->eventType])) ? $GLOBALS['TL_LANG']['MSC'][$objEvent->eventType] . ': ' : 'Event: ';

            $objTemplate->emailSubject = 'Absage für ' . $eventType . $objEvent->title;
            $objTemplate->emailText = strip_tags($objEmailTemplate->parse());
            return $objTemplate->parse();
        }

        return '';

    }


    /**
     * @param DC_Table $dc
     * @return string
     * @throws Exception
     */
    public function inputFieldCallbackAcceptWithEmail(DC_Table $dc)
    {

        // Start session
        session_start();

        if (Input::post('FORM_SUBMIT') === 'tl_calendar_events_member')
        {
            if (Input::post('subject') != '' && Input::post('text') != '')
            {


                $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);
                if ($objRegistration !== null)
                {
                    if (!\Contao\Validator::isEmail(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL')))
                    {
                        throw new \Exception('Please set a valid SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL Address in the Contao Backend Settings. Error in ' . __METHOD__ . ' LINE: ' . __LINE__);
                    }
                    $objEmail = new Email();
                    $objEmail->from = Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL');
                    $objEmail->fromName = html_entity_decode(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME'));
                    $objEmail->replyTo($this->User->email);
                    $objEmail->subject = Input::post('subject');
                    $objEmail->text = strip_tags(Input::post('text'));

                    // Send copy to instructor
                    // $objEmail->sendBcc($this->User->email);

                    // Send email
                    if (Validator::isEmail($objRegistration->email))
                    {
                        $objEmail->sendTo($objRegistration->email);

                        $set = array('stateOfSubscription' => 'subscription-accepted');
                        $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                        $_SESSION['addInfo'] = 'Dem Benutzer wurde mit einer E-Mail eine Zusage für diesen Event versandt.';
                    }
                    else
                    {
                        $_SESSION['addInfo'] = 'Es ist ein Fehler aufgetreten. Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.';
                    }
                }
                $this->redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=' . Input::get('id') . '&act=edit&rt=' . Input::get('rt'));

            }
            else
            {
                // Prefill form
                $objTemplate = new BackendTemplate('be_calendar_events_registration_accept_with_email');
                $objTemplate->emailSubject = Input::post('subject');
                $objTemplate->emailText = strip_tags(Input::post('text'));
                return $objTemplate->parse();
            }
        }

        $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);
        if ($objRegistration !== null)
        {
            $objEvent = $objRegistration->getRelated('eventId');

            // Build email text from template
            $objEmailTemplate = new BackendTemplate('be_email_templ_accept_registration');
            $objEmailTemplate->firstname = $objRegistration->firstname;
            $objEmailTemplate->lastname = $objRegistration->lastname;
            $objEmailTemplate->eventname = $objEvent->title;
            $objEmailTemplate->courseId = $objEvent->courseId;
            $objEmailTemplate->eventType = $objEvent->eventType;
            $objEmailTemplate->nameGuide = $this->User->name;

            // Prefill form
            $objTemplate = new BackendTemplate('be_calendar_events_registration_accept_with_email');

            // Get event type
            $eventType = (strlen($GLOBALS['TL_LANG']['MSC'][$objEvent->eventType])) ? $GLOBALS['TL_LANG']['MSC'][$objEvent->eventType] . ': ' : 'Event: ';

            $objTemplate->emailSubject = 'Zusage für ' . $eventType . $objEvent->title;
            $objTemplate->emailText = strip_tags($objEmailTemplate->parse());
            return $objTemplate->parse();
        }

        return '';

    }

    /**
     * @param DC_Table $dc
     * @return string
     * @throws Exception
     */
    public function inputFieldCallbackAddToWaitlist(DC_Table $dc)
    {

        // Start session
        session_start();

        if (Input::post('FORM_SUBMIT') === 'tl_calendar_events_member')
        {
            if (Input::post('subject') != '' && Input::post('text') != '')
            {
                $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);
                if ($objRegistration !== null)
                {
                    if (!\Contao\Validator::isEmail(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL')))
                    {
                        throw new \Exception('Please set a valid SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL Address in the Contao Backend Settings. Error in ' . __METHOD__ . ' LINE: ' . __LINE__);
                    }
                    $objEmail = new Email();
                    $objEmail->from = Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL');
                    $objEmail->fromName = html_entity_decode(Config::get('SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME'));
                    $objEmail->replyTo($this->User->email);
                    $objEmail->subject = Input::post('subject');
                    $objEmail->text = strip_tags(Input::post('text'));

                    // Send copy to instructor
                    // $objEmail->sendBcc($this->User->email);

                    // Send email
                    if (Validator::isEmail($objRegistration->email))
                    {
                        $objEmail->sendTo($objRegistration->email);
                        $set = array('stateOfSubscription' => 'subscription-waitlisted');
                        $this->Database->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                        $_SESSION['addInfo'] = 'Dem Benutzer wurde auf die Warteliste gesetzt und mit einer E-Mail dar&uuml;ber informiert.';
                    }
                    else
                    {
                        $_SESSION['addInfo'] = 'Es ist ein Fehler aufgetreten. Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.';
                    }
                }
                $this->redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=' . Input::get('id') . '&act=edit&rt=' . Input::get('rt'));

            }
            else
            {
                // Prefill form
                $objTemplate = new BackendTemplate('be_calendar_events_registration_added_to_waitlist');
                $objTemplate->emailSubject = Input::post('subject');
                $objTemplate->emailText = strip_tags(Input::post('text'));
                return $objTemplate->parse();
            }
        }

        $objRegistration = CalendarEventsMemberModel::findByPk($dc->id);
        if ($objRegistration !== null)
        {
            $objEvent = $objRegistration->getRelated('eventId');

            // Build email text from template
            $objEmailTemplate = new BackendTemplate('be_email_templ_added_to_waitlist');
            $objEmailTemplate->firstname = $objRegistration->firstname;
            $objEmailTemplate->lastname = $objRegistration->lastname;
            $objEmailTemplate->eventname = $objEvent->title;
            $objEmailTemplate->courseId = $objEvent->courseId;
            $objEmailTemplate->eventType = $objEvent->eventType;
            $objEmailTemplate->nameGuide = $this->User->name;

            // Prefill form
            $objTemplate = new BackendTemplate('be_calendar_events_registration_added_to_waitlist');

            // Get event type
            $eventType = (strlen($GLOBALS['TL_LANG']['MSC'][$objEvent->eventType])) ? $GLOBALS['TL_LANG']['MSC'][$objEvent->eventType] . ': ' : 'Event: ';

            $objTemplate->emailSubject = 'Auf Warteliste für ' . $eventType . $objEvent->title;
            $objTemplate->emailText = strip_tags($objEmailTemplate->parse());
            return $objTemplate->parse();
        }

        return '';

    }

    /**
     * @param $href
     * @param $label
     * @param $title
     * @param $class
     * @param $attributes
     * @param $table
     * @return string
     */
    public function buttonCbBackToEventSettings($href, $label, $title, $class, $attributes, $table)
    {
        $label = 'Zum Event';
        $href = ampersand('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s&ref=%s');
        $eventId = Input::get('id');
        $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');
        $href = sprintf($href, $eventId, REQUEST_TOKEN, $refererId);

        return ' <a href="' . $href . '" title="' . StringUtil::specialchars('Zur&uuml;ck zum Event') . '"' . $attributes . '>' . Image::getHtml('back.svg', $label) . ' ' . $label . '</a> ';
    }

    /**
     * buttons_callback
     * @param $arrButtons
     * @param DC_Table $dc
     * @return mixed
     */
    public function buttonsCallback($arrButtons, DC_Table $dc)
    {

        // Remove all buttons
        if (Input::get('call') === 'refuseWithEmail' || Input::get('call') === 'acceptWithEmail' || Input::get('call') === 'addToWaitlist')
        {
            $arrButtons = array();
        }

        if (Input::get('call') === 'sendEmail')
        {
            $arrButtons['saveNclose'] = '<button type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c">E-Mail absenden</button>';
            unset($arrButtons['save']);
        }

        unset($arrButtons['saveNback']);
        unset($arrButtons['saveNduplicate']);
        unset($arrButtons['saveNcreate']);

        return $arrButtons;
    }


    /**
     * Return the "toggle visibility" button
     *
     * @param array $row
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
        if (strlen(Input::get('tid')))
        {
            $this->toggleVisibility(Input::get('tid'), (Input::get('state') == 1), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }

        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin)
        {
            // Full access to admins
        }
        elseif (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, CURRENT_ID))
        {
            // User is allowed to edit table
        }
        else
        {
            $id = Input::get('id');
            $objEvent = \CalendarEventsModel::findByPk($id);
            if ($objEvent !== null)
            {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                if (!in_array($this->User->id, $arrAuthors))
                {
                    return '';
                }
            }
        }


        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . $row['disable'];

        if ($row['disable'])
        {
            $icon = 'invisible.svg';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label, 'data-state="' . ($row['disable'] ? 0 : 1) . '"') . '</a> ';
    }


    /**
     * Disable/enable a registration
     *
     * @param integer $intId
     * @param boolean $blnVisible
     * @param DataContainer $dc
     *
     * @throws Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function toggleVisibility($intId, $blnVisible, DataContainer $dc = null)
    {
        // Set the ID and action
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        if ($dc)
        {
            $dc->id = $intId; // see #8043
        }


        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin)
        {
            // Allow
        }
        elseif (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, CURRENT_ID))
        {
            // User is allowed to edit table
        }
        else
        {
            $id = Input::get('id');
            $objEvent = \CalendarEventsModel::findByPk($id);
            if ($objEvent !== null)
            {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                if (!in_array($this->User->id, $arrAuthors))
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to activate/deactivate registration ID ' . $id . '.');
                }
            }
        }


        $objVersions = new Versions('tl_calendar_events_member', $intId);
        $objVersions->initialize();

        // Reverse the logic (members have disabled=1)
        $blnVisible = !$blnVisible;

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['disable']['save_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['disable']['save_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, ($dc ?: $this));
                }
                elseif (is_callable($callback))
                {
                    $blnVisible = $callback($blnVisible, ($dc ?: $this));
                }
            }
        }

        $time = time();

        // Update the database
        $this->Database->prepare("UPDATE tl_calendar_events_member SET tstamp=$time, disable='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
            ->execute($intId);

        $objVersions->create();


    }
}
