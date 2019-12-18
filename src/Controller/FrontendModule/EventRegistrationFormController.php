<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\EventOrganizerModel;
use Contao\Events;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Haste\Form\Form;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class EventRegistrationFormController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule(category="sac_event_tool_fe_modules", type="event_registration_form")
 */
class EventRegistrationFormController extends AbstractFrontendModuleController
{

    /**
     * @var ContaoFramework
     */
    protected $framework;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $projectDir;

    /**
     * @var Security
     */
    protected $security;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ScopeMatcher
     */
    protected $scopeMatcher;

    /**
     * @var ModuleModel
     */
    protected $module;

    /**
     * @var Template
     */
    protected $template;

    /**
     * @var CalendarEventsModel
     */
    protected $objEvent;

    /**
     * @var FrontendUser
     */
    protected $objUser;

    /**
     * @var Notification
     */
    protected $objNotification;

    /**
     * @var UserModel
     */
    protected $objInstructor;

    /**
     * @var Form
     */
    protected $objForm;

    /**
     * EventRegistrationFormController constructor.
     * @param ContaoFramework $framework
     * @param Connection $connection
     * @param string $projectDir
     * @param Security $security
     * @param RequestStack $requestStack
     * @param ScopeMatcher $scopeMatcher
     */
    public function __construct(ContaoFramework $framework, Connection $connection, string $projectDir, Security $security, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->projectDir = $projectDir;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        if ($this->isFrontend())
        {
            // Set the module object (Contao\ModuleModel)
            $this->model = $model;

            // Set adapters
            $configAdapter = $this->framework->getAdapter(Config::class);
            $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
            $notificationAdapter = $this->framework->getAdapter(Notification::class);
            $userModelAdapter = $this->framework->getAdapter(UserModel::class);

            if (($objUser = $this->security->getUser()) instanceof FrontendUser)
            {
                $this->objUser = $objUser;
            }

            // Set the item from the auto_item parameter
            if (!$request->query->get('events') && $configAdapter->get('useAutoItem') && isset($_GET['auto_item']))
            {
                $request->query->set('events', $_GET['auto_item']);
                $request->query->set('auto_item', $_GET['auto_item']);
            }

            $blnShowModule = false;

            // Get $this->objEvent
            if ($request->query->get('events') != '')
            {
                $objEvent = $calendarEventsModelAdapter->findByIdOrAlias($request->query->get('events'));
                if ($objEvent !== null && $this->objUser !== null)
                {
                    $this->objEvent = $objEvent;
                    $blnShowModule = true;
                }
            }

            if (!$blnShowModule)
            {
                // Return empty string
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            // Use terminal42/notification_center
            $this->objNotification = $notificationAdapter->findByPk($this->model->receiptEventRegistrationNotificationId);

            // Get instructor object from UserModel
            $this->objInstructor = $userModelAdapter->findByPk($this->objEvent->mainInstructor);
        }
        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->template = $template;

        $scope = $this->isFrontend() ? 'FE' : 'BE';

        // Set adapters
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        $this->template->objUser = $this->objUser;
        $this->template->objEvent = $this->objEvent;

        // Set other template vars
        $this->setTemplateVars();

        // Show errors after form submit @see $this->generateForm()
        $this->template->hasBookingError = false;
        $this->template->bookingErrorMsg = '';

        // Count accepted registrations
        $objMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND stateOfSubscription=? AND contaoMemberId IN (SELECT id FROM tl_member WHERE disable=?)')->execute($this->objEvent->id, 'subscription-accepted', '');
        $countAcceptedRegistrations = $objMember->numRows;
        $this->template->countAcceptedRegistrations = $countAcceptedRegistrations;

        if ($this->objEvent->disableOnlineRegistration)
        {
            $messageAdapter->addInfo('Eine Online-Anmeldung zu diesem Event ist nicht möglich.', $scope);
        }
        elseif (!$this->objUser)
        {
            $messageAdapter->addInfo('Bitte logge dich mit deinem Mitglieder-Konto ein, um dich für den Event anzumelden.', $scope);
            $this->template->showLoginForm = true;
        }
        elseif ($this->objUser && true === $calendarEventsMemberModelAdapter->isRegistered($this->objUser->id, $this->objEvent->id))
        {
            $messageAdapter->addInfo('Du hast dich bereits für diesen Event angemeldet.', $scope);
        }
        elseif ($this->objEvent->eventState === 'event_fully_booked')
        {
            $messageAdapter->addInfo('Dieser Anlass ist ausgebucht. Bitte erkundige dich beim Leiter, ob eine Nachmeldung möglich ist.', $scope);
        }
        elseif ($this->objEvent->eventState === 'event_canceled')
        {
            $messageAdapter->addInfo('Dieser Anlass ist abgesagt worden.', $scope);
        }
        elseif ($this->objEvent->eventState === 'event_deferred')
        {
            $messageAdapter->addInfo('Dieser Anlass ist verschoben worden.', $scope);
        }
        elseif ($this->objEvent->setRegistrationPeriod && $this->objEvent->registrationStartDate > time())
        {
            $messageAdapter->addInfo(sprintf('Anmeldungen für <strong>"%s"</strong> sind erst ab dem %s möglich.', $this->objEvent->title, $dateAdapter->parse('d.m.Y H:i', $this->objEvent->registrationStartDate)), $scope);
        }
        elseif ($this->objEvent->setRegistrationPeriod && $this->objEvent->registrationEndDate < time())
        {
            $messageAdapter->addInfo('Die Anmeldefrist für diesen Event ist abgelaufen.', $scope);
        }
        elseif ($this->objEvent->startDate - 60 * 60 * 24 < time())
        {
            $messageAdapter->addInfo($_GET['auto_item'] . $this->objEvent->startDate . 'Die Anmeldefrist für diesen Event ist abgelaufen.', $scope);
        }
        elseif ($this->objUser && true === $calendarEventsHelperAdapter->areBookingDatesOccupied($this->objEvent->id, $this->objUser->id))
        {
            $messageAdapter->addInfo('Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.', $scope);
        }
        elseif ($this->objInstructor === null)
        {
            $messageAdapter->addError('Der Hauptleiter mit ID ' . $this->objEvent->mainInstructor . ' wurde nicht in der Datenbank gefunden. Bitte nimm persönlich Kontakt mit dem Leiter auf.', $scope);
        }
        elseif ($this->objInstructor->email == '' || !$validatorAdapter->isEmail($this->objInstructor->email))
        {
            $messageAdapter->addError('Dem Hauptleiter mit ID ' . $this->objEvent->mainInstructor . ' ist keine gültige E-Mail zugewiesen. Bitte nimm persönlich mit dem Leiter Kontakt auf.', $scope);
        }
        elseif ($this->objUser->email == '' || !$validatorAdapter->isEmail($this->objUser->email))
        {
            $messageAdapter->addError('Leider wurde für dieses Mitgliederkonto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlege auf auf der Internetseite des Zentralverbands deine E-Mail-Adresse.');
        }
        elseif ($this->objNotification === null)
        {
            $messageAdapter->addError('Systemfehler: Für das Modul ist keine Benachrichtigung (terminal42/notification_center) eingestellt worden. Bitte melde den Fehler bei der Geschäftsstelle der Sektion.', $scope);
        }

        // Add messages to the template
        if ($messageAdapter->hasMessages())
        {
            if ($messageAdapter->hasError())
            {
                $this->template->hasErrorMessage = true;
                $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
                $this->template->errorMessage = $session[0];
                System::log(sprintf('Event registration error: "%s"', $session[0]), __FILE__ . ' Line: ' . __LINE__, $configAdapter->get('SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR'));
            }
            if ($messageAdapter->hasInfo())
            {
                $this->template->hasInfoMessage = true;
                $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
                $this->template->infoMessage = $session[0];
            }
        }
        else
        {
            // Generate Form
            $this->generateForm();
            if ($this->objForm !== null)
            {
                $this->template->form = $this->objForm->generate();
                $this->template->objForm = $this->objForm;
            }

            // Check if event is already fully booked
            if ($calendarEventsHelperAdapter->eventIsFullyBooked($this->objEvent->id) === true)
            {
                $this->template->bookingLimitReaches = true;
            }
        }

        return $this->template->getResponse();
    }

    /**
     * @return null
     */
    protected function generateForm()
    {
        // Set adapters
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $calendarEventsJourneyModelAdapter = $this->framework->getAdapter(CalendarEventsJourneyModel::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $configAdapter = $this->framework->getAdapter(Config::class);

        // Get the request object
        $request = $this->requestStack->getCurrentRequest();

        $objEvent = $calendarEventsModelAdapter->findByIdOrAlias($request->query->get('events'));
        if ($objEvent === null)
        {
            return null;
        }

        $objForm = new Form('form-event-registration', 'POST', function ($objHaste) {
            $request = $this->requestStack->getCurrentRequest();
            return $request->request->get('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        $objJourney = $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey);
        if ($objJourney !== null)
        {
            if ($objJourney->alias === 'public-transport')
            {
                $objForm->addFormField('ticketInfo', array(
                    'label'     => 'Ich besitze ein/eine',
                    'inputType' => 'select',
                    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'],
                    'eval'      => array('includeBlankOption' => true, 'mandatory' => true),
                ));
            }
        }

        $objJourney = $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey);
        if ($objJourney !== null)
        {
            if ($objJourney->alias === 'car')
            {
                $objForm->addFormField('carInfo', array(
                    'label'     => 'Ich könnte ein Auto mit ... Plätzen (inkl. Fahrer) mitnehmen',
                    'inputType' => 'select',
                    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'],
                    'eval'      => array('includeBlankOption' => true, 'mandatory' => true),
                ));
            }
        }

        $objForm->addFormField('mobile', array(
            'label'     => 'Mobilnummer',
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'rgxp' => 'phone'),
        ));
        $objForm->addFormField('emergencyPhone', array(
            'label'     => 'Notfalltelefonnummer/In Notfällen zu kontaktieren',
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'rgxp' => 'phone'),
        ));
        $objForm->addFormField('emergencyPhoneName', array(
            'label'     => 'Name und Bezug der angehörigen Person, welche im Notfall zu kontaktieren ist',
            'inputType' => 'text',
            'eval'      => array('mandatory' => true),
        ));
        $objForm->addFormField('notes', array(
            'label'     => 'Anmerkungen/Erfahrungen/Referenztouren',
            'inputType' => 'textarea',
            'eval'      => array('mandatory' => true, 'rows' => 4),
            'class'     => '',
        ));

        // Only show this field if it is a multi day event
        $durationInDays = count($calendarEventsHelperAdapter->getEventTimestamps($objEvent->id));
        $startDate = $calendarEventsHelperAdapter->getStartDate($objEvent->id);
        $endDate = $calendarEventsHelperAdapter->getEndDate($objEvent->id);
        if ($durationInDays > 1 && $startDate + ($durationInDays - 1) * 86400 === $endDate)
        {
            $objForm->addFormField('foodHabits', array(
                'label'     => 'Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)',
                'inputType' => 'text',
                'eval'      => array('mandatory' => false),
            ));
        }

        $objForm->addFormField('agb', array(
            'label'     => array('', 'Ich akzeptiere <a href="#" data-toggle="modal" data-target="#agbModal">das Kurs- und Tourenreglement.</a>'),
            'inputType' => 'checkbox',
            'eval'      => array('mandatory' => true),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Für Event anmelden',
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        // Get form presets from tl_member
        $arrFields = array('mobile', 'emergencyPhone', 'emergencyPhoneName', 'foodHabits');
        foreach ($arrFields as $field)
        {
            $objWidget = $objForm->getWidget($field);
            if ($objWidget->value == '')
            {
                $objWidget = $objForm->getWidget($field);
                $objWidget->value = $this->objUser->{$field};
            }
        }

        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            $hasError = false;

            // Validate sacMemberId
            $objMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_member WHERE id=? AND disable=?')->limit(1)->execute($this->objUser->id, '');
            if (!$objMember->numRows)
            {
                $this->template->bookingErrorMsg = sprintf('Der Benutzer mit ID "%s" wurde nicht in der Mitgliederdatenbank gefunden.', $this->objUser->id);
                $hasError = true;
            }
            if (!$hasError)
            {
                // Prevent duplicate entries
                $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND contaoMemberId=?')->execute($this->objEvent->id, $this->objUser->id);
                if ($objDb->numRows)
                {
                    $this->template->bookingErrorMsg = 'Für diesen Event liegt von dir bereits eine Anmeldung vor.';
                    $hasError = true;
                }
            }
            if (!$hasError)
            {
                if (true === $calendarEventsHelperAdapter->areBookingDatesOccupied($this->objEvent->id, $objMember->id))
                {
                    $this->template->bookingErrorMsg = 'Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.';
                    $hasError = true;
                }
            }

            $this->template->hasBookingError = $hasError;

            // Save data to tl_calendar_events_member
            if (!$hasError)
            {
                $objMemberModel = $memberModelAdapter->findByPk($this->objUser->id);
                if ($objMemberModel !== null)
                {
                    $arrData = $objForm->fetchAll();
                    $arrData = array_merge($objMemberModel->row(), $arrData);
                    $arrData['contaoMemberId'] = $objMemberModel->id;
                    $arrData['eventName'] = $this->objEvent->title;
                    $arrData['eventId'] = $this->objEvent->id;
                    $arrData['addedOn'] = time();
                    $arrData['stateOfSubscription'] = 'subscription-not-confirmed';
                    $arrData['bookingType'] = 'onlineForm';

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
                    System::log(sprintf('New Registration from "%s %s [ID: %s]" for event with ID: %s ("%s").', $objMemberModel->firstname, $objMemberModel->lastname, $objMemberModel->id, $this->objEvent->id, $this->objEvent->title), __FILE__ . ' Line: ' . __LINE__, $configAdapter->get('SAC_EVT_LOG_EVENT_SUBSCRIPTION'));

                    $notified = $this->notifyMember($arrData, $objMemberModel, $this->objEvent, $objEventRegistration);

                    if ($this->model->jumpTo)
                    {
                        // Redirect to jumpTo page
                        $objPageModel = $pageModelAdapter->findByPk($this->model->jumpTo);
                        if ($objPageModel !== null && $notified)
                        {
                            $controllerAdapter->redirect($objPageModel->getFrontendUrl());
                        }
                    }
                }
            }
        }

        $this->objForm = $objForm;
    }

    /**
     * @param array $arrData
     * @param MemberModel $objMember
     * @param CalendarEventsModel $objEvent
     * @param CalendarEventsMemberModel $objEventRegistration
     * @return bool
     */
    protected function notifyMember(array $arrData, MemberModel $objMember, CalendarEventsModel $objEvent, CalendarEventsMemberModel $objEventRegistration)
    {
        $hasError = false;

        // Set adapters
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $eventsAdapter = $this->framework->getAdapter(Events::class);

        // Switch sender/recipient if the main instructor has delegated event registrations administration work to somebody else
        $bypassRegistration = false;
        if ($objEvent->registrationGoesTo)
        {
            $strRegistrationGoesToName = '';
            $strRegistrationGoesToEmail = '';
            $userId = $objEvent->registrationGoesTo;

            $objUser = $userModelAdapter->findByPk($userId);
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
            if ($calendarEventsHelperAdapter->eventIsFullyBooked($objEvent->id) === true)
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
                'participant_mobile'               => $arrData['mobile'],
                'participant_date_of_birth'        => $arrData['dateOfBirth'] > 0 ? $dateAdapter->parse('d.m.Y', $arrData['dateOfBirth']) : '---',
                'participant_food_habits'          => $arrData['foodHabits'],
                'participant_notes'                => html_entity_decode($arrData['notes']),
                'event_id'                         => $objEvent->id,
                'event_link_detail'                => 'https://' . $environmentAdapter->get('host') . '/' . $eventsAdapter->generateEventUrl($this->objEvent),
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

    /**
     *
     */
    private function setTemplateVars()
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);
        $validatorAdapter = $this->framework->getAdapter(Validator::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        if ($this->objEvent->eventType === 'tour' || $this->objEvent->eventType === 'last-minute-tour' || $this->objEvent->eventType === 'course')
        {
            $objEvent = $this->objEvent;
            $arrOrganizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);
            if (isset($arrOrganizers[0]))
            {
                $objOrganizer = $eventOrganizerModelAdapter->findByPk($arrOrganizers[0]);
                if ($objOrganizer !== null)
                {
                    $prefix = '';
                    if ($this->objEvent->eventType === 'tour' || $this->objEvent->eventType === 'last-minute-tour')
                    {
                        $prefix = 'tour';
                    }
                    if ($this->objEvent->eventType === 'course')
                    {
                        $prefix = 'course';
                    }

                    if ($prefix !== '')
                    {
                        if ($objOrganizer->{$prefix . 'RegulationSRC'} !== '')
                        {
                            if ($validatorAdapter->isBinaryUuid($objOrganizer->{$prefix . 'RegulationSRC'}))
                            {
                                $objFile = $filesModelAdapter->findByUuid($objOrganizer->{$prefix . 'RegulationSRC'});
                                if ($objFile !== null && is_file($this->projectDir . '/' . $objFile->path))
                                {
                                    $this->template->objEventRegulationFile = $objFile;
                                }
                            }
                        }
                        if ($objOrganizer->{$prefix . 'RegulationExtract'} !== '')
                        {
                            $this->template->eventRegulationExtract = $objOrganizer->{$prefix . 'RegulationExtract'};
                        }
                    }
                }
            }
        }
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    protected function isFrontend()
    {
        return $this->requestStack->getCurrentRequest() !== null ? $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest()) : false;
    }

}
