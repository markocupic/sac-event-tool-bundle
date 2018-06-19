<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;


use Contao\BackendTemplate;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\Module;
use Contao\PageModel;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Haste\Form\Form;
use NotificationCenter\Model\Notification;
use Patchwork\Utf8;

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
    protected $strTemplate = 'mod_sac_event_tool_event_registration_form';

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
        $objMember = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND stateOfSubscription=? AND contaoMemberId IN (SELECT id FROM tl_member WHERE disable=?)')->execute($this->objEvent->id, 'subscription-accepted', '');
        $countAcceptedRegistrations = $objMember->numRows;
        $this->Template->countAcceptedRegistrations = $countAcceptedRegistrations;

        if ($this->objEvent->disableOnlineRegistration)
        {
            Message::addInfo('Eine Online-Anmeldung zu diesem Event ist nicht m&ouml;glich.', TL_MODE);
        }
        elseif (!FE_USER_LOGGED_IN)
        {
            Message::addInfo('Bitte melde dich mit deinem Benutzerkonto an, um dich f&uuml;r den Event anzumelden.', TL_MODE);
        }
        elseif (FE_USER_LOGGED_IN && true === CalendarEventsMemberModel::isRegistered($this->objUser->id, $this->objEvent->id))
        {
            Message::addInfo('Du hast dich bereits f&uuml;r diesen Event angemeldet.', TL_MODE);
        }
        elseif ($this->objEvent->eventState === 'event_fully_booked')
        {
            Message::addInfo('Dieser Anlass ist ausgebucht. Bitte erkundige dich beim Leiter, ob eine Nachmeldung m&ouml;glich ist.', TL_MODE);
        }
        elseif ($this->objEvent->eventState === 'event_canceled')
        {
            Message::addInfo('Dieser Anlass ist abgesagt worden.', TL_MODE);
        }
        elseif ($this->objEvent->eventState === 'event_deferred')
        {
            Message::addInfo('Dieser Anlass ist verschoben worden.', TL_MODE);
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
            Message::addError('Der Hauptleiter mit ID ' . $this->objEvent->mainInstructor . ' wurde nicht in der Datenbank gefunden. Bitte nimm pers&ouml;nlich Kontakt mit dem Leiter auf.', TL_MODE);
        }
        elseif ($this->objInstructor->email == '' || !Validator::isEmail($this->objInstructor->email))
        {
            Message::addError('Dem Hauptleiter mit ID ' . $this->objEvent->mainInstructor . ' ist keine g&uuml;ltige E-Mail zugewiesen. Bitte nimm pers&ouml;nlich mit dem Leiter Kontakt auf.', TL_MODE);
        }
        elseif ($this->objUser->email == '' || !Validator::isEmail($this->objUser->email))
        {
            Message::addError('Leider wurde f&uuml;r dieses Mitgliederkonto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschr&auml;nkt zur Verf&uuml;gung. Bitte hinterlege auf auf der Internetseite des Zentralverbands deine E-Mail-Adresse.');
        }
        elseif ($this->objNotification === null)
        {
            Message::addError('Systemfehler: F&uuml;r das Modul ist keine Benachrichtigung (terminal42/notification_center) eingestellt worden. Bitte melde den Fehler bei der Gesch&auml;ftsstelle der Sektion.', TL_MODE);
        }

        // Add messages to the template
        if (Message::hasMessages())
        {
            if (Message::hasError())
            {
                $this->Template->hasErrorMessage = true;
                $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
                $this->Template->errorMessage = $session[0];
                System::log(sprintf('Event registration error: "%s"', $session[0]), __FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR'));
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
            if ($this->objForm !== null)
            {
                $this->Template->form = $this->objForm->generate();
            }

            // Check if event is already fully booked
            if (CalendarSacEvents::eventIsFullyBooked($this->objEvent->id) === true)
            {
                $this->Template->bookingLimitReaches = true;
            }
        }
    }


    /**
     * @return null
     */
    protected function generateForm()
    {
        $objEvent = CalendarEventsModel::findByIdOrAlias(Input::get('events'));
        if ($objEvent === null)
        {
            return null;
        }

        $objForm = new Form('form-event-registration', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));


        // Now let's add form fields:
        $objJourney = CalendarEventsJourneyModel::findByPk($objEvent->journey);
        if ($objJourney !== null)
        {
            if ($objJourney->alias === 'public-transport')
            {
                $objForm->addFormField('ticketInfo', array(
                    'label'     => 'Ich besitze ein/eine',
                    'inputType' => 'select',
                    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'],
                    'eval'      => array('includeBlankOption' => true, 'mandatory' => true, 'rgxp' => 'phone'),
                ));
            }
        }

        $objJourney = CalendarEventsJourneyModel::findByPk($objEvent->journey);
        if ($objJourney !== null)
        {
            if ($objJourney->alias === 'car')
            {
                $objForm->addFormField('carInfo', array(
                    'label'     => 'Ich k&ouml;nnte ein Auto mit ... Pl&auml;tzen (inkl. Fahrer) mitnehmen',
                    'inputType' => 'select',
                    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'],
                    'eval'      => array('includeBlankOption' => true, 'mandatory' => true, 'rgxp' => 'phone'),
                ));
            }
        }

        $objForm->addFormField('phone', array(
            'label'     => 'Telefonnummer',
            'inputType' => 'text',
            'default'   => $this->User->phone,
            'eval'      => array('mandatory' => false, 'rgxp' => 'phone'),
        ));
        $objForm->addFormField('mobile', array(
            'label'     => 'Mobilnummer',
            'inputType' => 'text',
            'default'   => $this->User->mobile,
            'eval'      => array('mandatory' => false, 'rgxp' => 'phone'),
        ));
        $objForm->addFormField('emergencyPhone', array(
            'label'     => 'Notfalltelefonnummer/In Notf&auml;llen zu kontaktieren',
            'inputType' => 'text',
            'default'   => $this->User->emergencyPhone,
            'eval'      => array('mandatory' => true, 'rgxp' => 'phone'),
        ));
        $objForm->addFormField('emergencyPhoneName', array(
            'label'     => 'Name der angeh&ouml;rigen Person, welche im Notfall zu kontaktieren ist',
            'inputType' => 'text',
            'default'   => $this->User->emergencyPhoneName,
            'eval'      => array('mandatory' => true),
        ));
        $objForm->addFormField('notes', array(
            'label'     => 'Anmerkungen/Erfahrungen/Referenztouren',
            'inputType' => 'textarea',
            'eval'      => array('mandatory' => true, 'rows' => 4),
            'class'     => '',
        ));
        $objForm->addFormField('agb', array(
            'label'     => array('', 'Ich akzeptiere die <a href="#" data-toggle="modal" data-target="#agbModal">allg. Gesch&auml;ftsbedingungen.</a>'),
            'inputType' => 'checkbox',
            'eval'      => array('mandatory' => true),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'F&uuml;r Event anmelden',
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        // Get form presets from tl_member
        $arrFields = array('phone', 'mobile', 'emergencyPhone', 'emergencyPhoneName');
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
        $objWidget->addAttribute('placeholder', 'Bitte beschreibe in wenigen S&auml;tzen dein Leistungsniveau und/oder beantworte, die in den Anmeldebestimmungen verlangten Angaben. (z.B. bereits absolvierte Referenztouren oder Essgewohnheiten bei Events mit &Uuml;bernachtung, etc.)');

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
                $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND contaoMemberId=?')->execute(Input::get('events'), $this->objUser->id);
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
                    $arrData['eventId'] = $this->objEvent->id;
                    $arrData['addedOn'] = time();
                    $arrData['stateOfSubscription'] = 'subscription-not-confirmed';

                    if ($arrData['phone'] == '' && $this->objUser->phone != '')
                    {
                        $arrData['phone'] = $this->objUser->phone;
                    }

                    if ($arrData['mobile'] == '' && $this->objUser->mobile != '')
                    {
                        $arrData['mobile'] = $this->objUser->mobile;
                    }

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

                    $objEventRegistration = new CalendarEventsMemberModel();
                    unset($arrData['id']);
                    $arrData = array_filter($arrData);
                    $objEventRegistration->setRow($arrData);
                    $objEventRegistration->save();
                    System::log(sprintf('New Registration from "%s %s [ID: %s]" for event with ID: %s ("%s").', $objMemberModel->firstname, $objMemberModel->lastname, $objMemberModel->id, $this->objEvent->id, $this->objEvent->title), __FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_EVENT_SUBSCRIPTION'));


                    $notified = $this->notifyMember($arrData, $objMemberModel, $this->objEvent, $objEventRegistration);

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
    protected function notifyMember($arrData, $objMember, $objEvent, $objEventRegistration)
    {
        $hasError = false;

        // Switch sender/recipient if the main instructor has delegated event registrations administration work to somebody else
        $bypassRegistration = false;
        if ($objEvent->registrationGoesTo)
        {
            $strRegistrationGoesToName = '';
            $strRegistrationGoesToEmail = '';
            $userId = $objEvent->registrationGoesTo;

            $objUser = UserModel::findByPk($userId);
            if ($objUser !== null)
            {
                if ($objUser->email != '')
                {
                    $strRegistrationGoesToName = $objUser->name;
                    $strRegistrationGoesToEmail = $objUser->email;
                }
            }

            if ($strRegistrationGoesToEmail !== '' && $strRegistrationGoesToName !== '')
            {
                $bypassRegistration = true;
            }
        }


        // Use terminal42/notification_center
        if ($this->objNotification !== null)
        {

            // Get the event type
            $eventType = (strlen($GLOBALS['TL_LANG']['MSC'][$this->objEvent->eventType])) ? $GLOBALS['TL_LANG']['MSC'][$this->objEvent->eventType] . ': ' : '';

            // Check if event is already fully booked
            $eventFullyBooked = false;
            if (CalendarSacEvents::eventIsFullyBooked($objEvent->id) === true)
            {
                $eventFullyBooked = true;
                $objEventRegistration->stateOfSubscription = 'subscription-waitlisted';
                $objEventRegistration->save();
            }

            // Set token array
            $arrTokens = array(
                'event_name'                       => html_entity_decode($eventType . $this->objEvent->title),
                'event_type'                       => html_entity_decode($objEvent->eventType),
                'event_course_id'                  => $objEvent->courseId,
                'instructor_name'                  => $bypassRegistration ? html_entity_decode($strRegistrationGoesToName) : html_entity_decode($this->objInstructor->name),
                'instructor_email'                 => $bypassRegistration ? html_entity_decode($strRegistrationGoesToEmail) : html_entity_decode($this->objInstructor->email),
                'participant_name'                 => html_entity_decode($objMember->firstname . ' ' . $objMember->lastname),
                'participant_email'                => $objMember->email != $arrData['email'] ? $arrData['email'] : $objMember->email,
                'participant_emergency_phone'      => $arrData['emergencyPhone'],
                'participant_emergency_phone_name' => html_entity_decode($arrData['emergencyPhoneName']),
                'participant_street'               => html_entity_decode($objMember->street),
                'participant_postal'               => $objMember->postal,
                'participant_city'                 => html_entity_decode($objMember->city),
                'participant_contao_member_id'     => $objMember->id,
                'participant_sac_member_id'        => $objMember->sacMemberId,
                'participant_phone'                => $arrData['phone'],
                'participant_mobile'               => $arrData['mobile'],
                'participant_date_of_birth'        => $arrData['dateOfBirth'] > 0 ? Date::parse('d.m.Y', $arrData['dateOfBirth']) : '---',
                'participant_vegetarian'           => $arrData['vegetarian'] == 'true' ? 'Ja' : 'Nein',
                'participant_notes'                => html_entity_decode($arrData['notes']),
                'event_link_detail'                => 'https://' . Environment::get('host') . '/' . Events::generateEventUrl($this->objEvent),
                'event_state'                      => $eventFullyBooked === true ? 'fully-booked' : '',
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
