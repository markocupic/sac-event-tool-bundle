<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;


use Contao\CalendarEventsMemberModel;
use Contao\Validator;
use Patchwork\Utf8;
use Contao\System;
use Contao\StringUtil;
use Contao\Module;
use Contao\BackendTemplate;
use Contao\Input;
use Contao\UserModel;
use Contao\MemberModel;
use Contao\Date;
use Contao\Database;
use Contao\Controller;
use Contao\FrontendUser;
use Contao\CalendarEventsModel;
use Contao\Events;
use Contao\PageModel;
use Contao\Environment;
use Contao\Message;
use Contao\Config;
use NotificationCenter\Model\Notification;
use Haste\Form\Form;

/**
 * Class ModuleSacEventToolEventRegistrationForm
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolEventRegistrationForm extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_sacpilatus_event_registration_form';

    /**
     * @var
     */
    protected $objEvent;

    /**
     * @var
     */
    protected $objUser;

    /**
     * @var
     */
    protected $objNotification;

    /**
     * @var
     */
    protected $objInstructor;

    /**
     * @var
     */
    protected $objForm;


    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolEventRegistrationForm'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        if (FE_USER_LOGGED_IN)
        {
            $this->objUser = FrontendUser::getInstance();
        }


        // Set the item from the auto_item parameter
        if (!isset($_GET['events']) && Config::get('useAutoItem') && isset($_GET['auto_item']))
        {
            Input::setGet('events', Input::get('auto_item'));
        }

        // Get $objEvent
        if (Input::get('events') != '')
        {
            $objEvent = CalendarEventsModel::findByIdOrAlias(Input::get('events'));
            if ($objEvent !== null)
            {
                $this->objEvent = $objEvent;
            }
            else
            {
                return '';
            }
        }

        // Use terminal42/notification_center
        $this->objNotification = Notification::findByPk($this->receiptEventRegistrationNotificationId);


        // Get instructor object from UserModel
        $this->objInstructor = UserModel::findByPk($this->objEvent->mainInstructor);


        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {

        $this->Template->objUser = $this->objUser;
        $this->Template->objEvent = $this->objEvent;

        // Show errors after form submit @see $this->generateForm()
        $this->Template->hasBookingError = false;
        $this->Template->bookingErrorMsg = '';

        // Count accepted registrations
        $objMember = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE pid=? AND stateOfSubscription=? AND contaoMemberId IN (SELECT id FROM tl_member WHERE disable=?)')->execute($this->objEvent->id, 'subscription-accepted', '');
        $countAcceptedRegistrations = $objMember->numRows;
        $this->Template->countAcceptedRegistrations = $countAcceptedRegistrations;

        if ($this->objEvent->disableOnlineRegistration)
        {
            Message::addInfo('Online Anmeldung zu diesem Event nicht m&ouml;glich.', TL_MODE);
        }
        elseif (!FE_USER_LOGGED_IN)
        {
            Message::addInfo('Bitte melden Sie sich mit Ihrem Benutzerkonto an, um sich f&uuml;r den Event anzumelden.', TL_MODE);
        }
        elseif (FE_USER_LOGGED_IN && true === CalendarEventsMemberModel::isRegistered($this->objUser->id, $this->objEvent->id))
        {
            Message::addInfo('Sie haben sich bereits f&uuml;r diesen Event angemeldet.', TL_MODE);
        }
        elseif ($this->objEvent->setRegistrationPeriod && $this->objEvent->registrationStartDate > time())
        {
            Message::addInfo(sprintf('Anmeldungen f&uuml;r <strong>"%s"</strong> sind erst ab dem %s m&ouml;glich.', $this->objEvent->title, Date::parse('d.m.Y', $this->objEvent->registrationStartDate)), TL_MODE);
        }
        elseif ($this->objEvent->setRegistrationPeriod && $this->objEvent->registrationEndDate < time())
        {
            Message::addInfo('Die Anmeldefrist f&uuml;r diesen Event ist abgelaufen.', TL_MODE);
        }
        elseif ($this->objEvent->startDate - 60 * 60 * 24 < time())
        {
            Message::addInfo('Die Anmeldefrist f&uuml;r diesen Event ist abgelaufen.', TL_MODE);
        }
        elseif (FE_USER_LOGGED_IN && true === CalendarSacEvents::areBookingDatesOccupied($this->objEvent->id, $this->objUser->id))
        {
            Message::addInfo('Die Anmeldung zu diesem Event ist nicht m&ouml;glich, da die Event-Daten sich mit den Daten eines anderen Events &uuml;berschneiden, wo Ihre Teilnahme bereits best&auml;tigt ist.', TL_MODE);
        }
        elseif ($this->objInstructor === null)
        {
            Message::addError('Der Hauptleiter mit ID ' . $this->objEvent->mainInstructor . ' wurde nicht in der Datenbank gefunden. Bitte nehmen Sie pers&ouml;nlich Kontakt mit dem Leiter auf.', TL_MODE);
        }
        elseif ($this->objInstructor->email == '' || !Validator::isEmail($this->objInstructor->email))
        {
            Message::addError('Dem Hauptleiter mit ID ' . $this->objEvent->mainInstructor . ' ist keine g&uuml;ltige E-Mail zugewiesen. Bitte nehmen Sie pers&ouml;nlich mit dem Leiter Kontakt auf.', TL_MODE);
        }
        elseif ($this->objUser->email == '' || !Validator::isEmail($this->objUser->email))
        {
            Message::addError('Leider wurde f&uuml;r dieses Mitgliederkonto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschr&auml;nkt zur Verf&uuml;gung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }
        elseif ($this->objNotification === null)
        {
            Message::addError('Systemfehler: F&uuml;r das Modul ist keine Benachrichtigung (terminal42/notification_center) eingestellt worden. Bitte melden Sie sich bei der Gesch&auml;ftsstelle der Sektion.', TL_MODE);
        }

        // Add messages to the template
        if (Message::hasMessages())
        {
            if (Message::hasError())
            {
                $this->Template->hasErrorMessage = true;
                $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
                $this->Template->errorMessage = $session[0];
                System::log(sprintf('Event registration error: "%s"', $session[0]), __FILE__ . ' Line: ' . __LINE__, SACP_LOG_EVENT_SUBSCRIPTION_ERROR);
            }
            if (Message::hasInfo())
            {
                $this->Template->hasInfoMessage = true;
                $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
                $this->Template->infoMessage = $session[0];
            }
        }
        else
        {
            // Generate Form
            $this->generateForm();
            $this->Template->form = $this->objForm->generate();

            // Check if event is already fully booked
            if ($this->objEvent->maxMembers > 0 && $countAcceptedRegistrations >= $this->objEvent->maxMembers)
            {
                $this->Template->eventFullyBooked = true;
            }

        }

    }


    /**
     * @return Form
     */
    protected function generateForm()
    {

        $objForm = new Form('form-course-registration', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('emergencyPhone', array(
            'label' => 'Notfalltelefonnummer/In Notf&auml;llen zu kontaktieren',
            'inputType' => 'text',
            'default' => $this->User->emergencyPhone,
            'eval' => array('mandatory' => true, 'rgxp' => 'phone')
        ));
        $objForm->addFormField('emergencyPhoneName', array(
            'label' => 'Name der angeh&ouml;rigen Person, welche im Notfall zu kontaktieren ist',
            'inputType' => 'text',
            'default' => $this->User->emergencyPhoneName,
            'eval' => array('mandatory' => true)
        ));
        $objForm->addFormField('notes', array(
            'label' => 'Anmerkungen/Erfahrungen/Referenztouren',
            'inputType' => 'textarea',
            'eval' => array('mandatory' => true, 'rows' => 10)
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label' => 'F&uuml;r Event anmelden',
            'inputType' => 'submit'
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        // Get form presets from tl_member
        $arrFields = array('emergencyPhone', 'emergencyPhoneName');
        foreach ($arrFields as $field)
        {
            $objWidget = $objForm->getWidget($field);
            if ($objWidget->value == '')
            {
                $objWidget = $objForm->getWidget($field);
                $objWidget->value = $this->objUser->{$field};
            }
        }


        $objWidget = $objForm->getWidget('notes');
        $objWidget->addAttribute('placeholder', 'Bitte geben Sie in ein paar S&auml;tzen Ihr Leistungsniveau an oder machen Sie Angaben &uuml;ber bereits absolvierte Referenztouren.');


        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            $hasError = false;

            // Validate sacMemberId
            $objMember = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE id=? AND disable=?')->limit(1)->execute($this->objUser->id, '');
            if (!$objMember->numRows)
            {
                $this->Template->bookingErrorMsg = sprintf('Der Benutzer mit ID "%s" wurde nicht in der Mitgliederdatenbank gefunden.', $this->objUser->id);
                $hasError = true;
            }
            if (!$hasError)
            {
                // Prevent duplicate entries
                $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE pid=? AND contaoMemberId=?')->execute(Input::get('events'), $this->objUser->id);
                if ($objDb->numRows)
                {
                    $this->Template->bookingErrorMsg = 'F&uuml;r diesen Event liegt von dir bereits eine Anmeldung vor.';
                    $hasError = true;
                }
            }
            if (!$hasError)
            {
                if (true === CalendarSacEvents::areBookingDatesOccupied($this->objEvent->id, $objMember->id))
                {
                    $this->Template->bookingErrorMsg = 'Die Anmeldung zu diesem Event ist nicht m&ouml;glich, da die Event-Daten sich mit den Daten eines anderen Events &uuml;berschneiden, wo Ihre Teilnahme bereits best&auml;tigt ist.';
                    $hasError = true;
                }
            }

            $this->Template->hasBookingError = $hasError;


            // Save data to tl_calendar_events_member
            if (!$hasError)
            {
                $objMemberModel = MemberModel::findByPk($this->objUser->id);
                if ($objMemberModel !== null)
                {
                    $arrData = $objForm->fetchAll();
                    $arrData = array_merge($objMemberModel->row(), $arrData);
                    $arrData['contaoMemberId'] = $objMemberModel->id;
                    $arrData['eventName'] = $this->objEvent->title;
                    $arrData['pid'] = $this->objEvent->id;
                    $arrData['addedOn'] = time();
                    $arrData['stateOfSubscription'] = 'subscription-not-confirmed';

                    // Save emergency phone number to user profile
                    if ($objMemberModel->emergencyPhone == '')
                    {
                        $objMemberModel->emergencyPhone = $arrData['emergencyPhone'];
                        $objMemberModel->save();
                    }
                    if ($objMemberModel->emergencyPhoneName == '')
                    {
                        $objMemberModel->emergencyPhoneName = $arrData['emergencyPhoneName'];
                        $objMemberModel->save();
                    }


                    $arrRegistrationGoesTo = \StringUtil::deserialize($this->objEvent->registrationGoesTo, true);
                    $arrRegistrationGoesTo = array_map(function ($userId) {
                        $objUser = UserModel::findByPk($userId);
                        if ($objUser !== null)
                        {
                            if ($objUser->email != '')
                            {
                                return $objUser->email;
                            }
                        }
                    }, $arrRegistrationGoesTo);
                    $strRegistrationGoesTo = implode(',', $arrRegistrationGoesTo);

                    $objEventRegistration = new CalendarEventsMemberModel();
                    $objEventRegistration->setRow($arrData);
                    $objEventRegistration->save();
                    System::log(sprintf('New Registration from "%s %s [ID: %s]" for event with ID: %s ("%s").', $objMemberModel->firstname, $objMemberModel->lastname, $objMemberModel->id, $this->objEvent->id, $this->objEvent->title), __FILE__ . ' Line: ' . __LINE__, SACP_LOG_EVENT_SUBSCRIPTION);


                    $notified = $this->notifyMember($arrData, $objMemberModel, $strRegistrationGoesTo);

                    if ($this->jumpTo)
                    {
                        // Redirect to jumpTo page
                        $objPageModel = PageModel::findByPk($this->jumpTo);
                        if ($objPageModel !== null && $notified)
                        {
                            Controller::redirect($objPageModel->getFrontendUrl());
                        }
                    }
                }
            }
        }

        $this->objForm = $objForm;
    }


    /**
     * @param $arrData
     * @param $objMember
     * @return bool
     */
    protected function notifyMember($arrData, $objMember, $strRegistrationGoesTo = '')
    {
        $hasError = false;
        // Use terminal42/notification_center
        if ($this->objNotification !== null)
        {
            $arrTokens = array(
                'event_name' => html_entity_decode($this->objEvent->title),
                'instructor_name' => html_entity_decode($this->objInstructor->name),
                'instructor_email' => html_entity_decode($this->objInstructor->email),
                'registration_goes_to' => $strRegistrationGoesTo,
                'participant_name' => html_entity_decode($objMember->firstname . ' ' . $objMember->lastname),
                'participant_email' => $objMember->email != $arrData['email'] ? $arrData['email'] : $objMember->email,
                'participant_emergency_phone' => $arrData['emergencyPhone'],
                'participant_emergency_phone_name' => html_entity_decode($arrData['emergencyPhoneName']),
                'participant_street' => html_entity_decode($objMember->street),
                'participant_postal' => $objMember->postal,
                'participant_city' => html_entity_decode($objMember->city),
                'participant_contao_member_id' => $objMember->id,
                'participant_sac_member_id' => $objMember->sacMemberId,
                'participant_phone' => $arrData['phone'],
                'participant_date_of_birth' => $arrData['dateOfBirth'],
                'participant_vegetarian' => $arrData['vegetarian'] == 'true' ? 'Ja' : 'Nein',
                'participant_notes' => html_entity_decode($arrData['notes']),
                'event_link_detail' => 'https://' . Environment::get('host') . '/' . Events::generateEventUrl($this->objEvent),
            );


            if ($hasError)
            {
                return false;
            }

            $this->objNotification->send($arrTokens, 'de');

            return true;

        }
    }


}
