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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\EventOrganizerModel;
use Contao\Events;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\Input;
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
use Markocupic\SacEventToolBundle\ContaoMode\ContaoMode;
use NotificationCenter\Model\Notification;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

/**
 * Class EventRegistrationFormController.
 *
 * @FrontendModule("event_registration_form", category="sac_event_tool_frontend_modules")
 */
class EventRegistrationFormController extends AbstractFrontendModuleController
{
    /**
     * @var string
     */
    protected $projectDir;

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

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        /** @var projectDir */
        $this->projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Set the module object (Contao\ModuleModel)
        $this->model = $model;

        // Set adapters
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);
        $notificationAdapter = $this->get('contao.framework')->getAdapter(Notification::class);
        $userModelAdapter = $this->get('contao.framework')->getAdapter(UserModel::class);
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser) {
            /** @var MemberModel objUser */
            $this->objUser = MemberModel::findByPk($objUser->id);
        }

        // Set the item from the auto_item parameter
        if (!isset($_GET['events']) && $configAdapter->get('useAutoItem') && isset($_GET['auto_item'])) {
            $inputAdapter->setGet('events', $inputAdapter->get('auto_item'));
        }

        $blnShowModule = false;

        // Get $this->objEvent
        if ('' !== $inputAdapter->get('events')) {
            $objEvent = $calendarEventsModelAdapter->findByIdOrAlias($inputAdapter->get('events'));

            if (null !== $objEvent) {
                $this->objEvent = $objEvent;
                $blnShowModule = true;
            }
        }

        /** @var ContaoMode $scope */
        $scope = System::getContainer()->get('Markocupic\SacEventToolBundle\ContaoMode\ContaoMode');

        if ($scope->isFrontend() && !$blnShowModule) {
            // Return empty string
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Use terminal42/notification_center
        $this->objNotification = $notificationAdapter->findByPk($this->model->receiptEventRegistrationNotificationId);

        // Get instructor object from UserModel
        $this->objInstructor = $userModelAdapter->findByPk($this->objEvent->mainInstructor);

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes, $page);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['database_connection'] = Connection::class;
        $services['security.helper'] = Security::class;
        $services['request_stack'] = RequestStack::class;

        return $services;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->template = $template;

        $scope = 'FE';

        // Set adapters
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);
        /** @var Message $messageAdapter */
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsMemberModel::class);
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);
        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);

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

        if ($this->objEvent->disableOnlineRegistration) {
            $messageAdapter->addInfo('Eine Online-Anmeldung zu diesem Event ist nicht möglich.', $scope);
        } elseif (null === $this->objUser) {
            $messageAdapter->addInfo('Bitte logge dich mit deinem Mitglieder-Konto ein, um dich für den Event anzumelden.', $scope);
            $this->template->showLoginForm = true;
        } elseif (null !== $this->objUser && true === $calendarEventsMemberModelAdapter->isRegistered($this->objUser->id, $this->objEvent->id)) {
            $messageAdapter->addInfo('Du hast dich bereits für diesen Event angemeldet.', $scope);
        } elseif ('event_fully_booked' === $this->objEvent->eventState) {
            $messageAdapter->addInfo('Dieser Anlass ist ausgebucht. Bitte erkundige dich beim Leiter, ob eine Nachmeldung möglich ist.', $scope);
        } elseif ('event_canceled' === $this->objEvent->eventState) {
            $messageAdapter->addInfo('Dieser Anlass wurde abgesagt. Es ist keine Anmeldung möglich.', $scope);
        } elseif ('event_deferred' === $this->objEvent->eventState) {
            $messageAdapter->addInfo('Dieser Anlass ist verschoben worden.', $scope);
        } elseif ($this->objEvent->setRegistrationPeriod && $this->objEvent->registrationStartDate > time()) {
            $messageAdapter->addInfo(sprintf('Anmeldungen für <strong>"%s"</strong> sind erst ab dem %s möglich.', $this->objEvent->title, $dateAdapter->parse('d.m.Y H:i', $this->objEvent->registrationStartDate)), $scope);
        } elseif ($this->objEvent->setRegistrationPeriod && $this->objEvent->registrationEndDate < time()) {
            $messageAdapter->addInfo(sprintf('Die Anmeldefrist für diesen Event ist am %s abgelaufen.', $dateAdapter->parse('d.m.Y \u\m H:i', $this->objEvent->registrationEndDate)), $scope);
        } elseif (!$this->objEvent->setRegistrationPeriod && $this->objEvent->startDate - 60 * 60 * 24 < time()) {
            $messageAdapter->addInfo('Die Anmeldefrist für diesen Event ist abgelaufen. Du kannst dich bis 24 Stunden vor Event-Beginn anmelden. Nimm gegebenenfalls mit dem Leiter Kontakt auf.', $scope);
        } elseif ($this->objUser && true === $calendarEventsHelperAdapter->areBookingDatesOccupied($this->objEvent, $this->objUser)) {
            $messageAdapter->addInfo('Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.', $scope);
        } elseif (null === $this->objInstructor) {
            $messageAdapter->addError('Der Hauptleiter mit ID '.$this->objEvent->mainInstructor.' wurde nicht in der Datenbank gefunden. Bitte nimm persönlich Kontakt mit dem Leiter auf.', $scope);
        } elseif (empty($this->objInstructor->email) || !$validatorAdapter->isEmail($this->objInstructor->email)) {
            $messageAdapter->addError('Dem Hauptleiter mit ID '.$this->objEvent->mainInstructor.' ist keine gültige E-Mail zugewiesen. Bitte nimm persönlich mit dem Leiter Kontakt auf.', $scope);
        } elseif (empty($this->objUser->email) || !$validatorAdapter->isEmail($this->objUser->email)) {
            $messageAdapter->addError('Leider wurde für dieses Mitgliederkonto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlege auf auf der Internetseite des Zentralverbands deine E-Mail-Adresse.');
        } elseif (null === $this->objNotification) {
            $messageAdapter->addError('Systemfehler: Für das Modul ist keine Benachrichtigung (terminal42/notification_center) eingestellt worden. Bitte melde den Fehler bei der Geschäftsstelle der Sektion.', $scope);
        }

        // All ok! Show the registration form;

        // Add messages to the template
        if ($messageAdapter->hasMessages()) {
            if ($messageAdapter->hasError()) {
                $this->template->hasErrorMessage = true;
                $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
                $this->template->errorMessage = $session[0];

                // Log
                $logger = System::getContainer()->get('monolog.logger.contao');
                $strText = sprintf('Event registration error: "%s"', $session[0]);
                $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR'))]);
            }

            if ($messageAdapter->hasInfo()) {
                $this->template->hasInfoMessage = true;
                $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
                $this->template->infoMessage = $session[0];
            }
        } else {
            // Generate Form
            $this->generateForm();

            if (null !== $this->objForm) {
                $this->template->form = $this->objForm->generate();
                $this->template->objForm = $this->objForm;
            }

            // Check if event is already fully booked
            if (true === $calendarEventsHelperAdapter->eventIsFullyBooked($this->objEvent)) {
                $this->template->bookingLimitReaches = true;
            }
        }

        return $this->template->getResponse();
    }

    protected function generateForm()
    {
        // Set adapters
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        /** @var CalendarEventsJourneyModel $calendarEventsJourneyModelAdapter */
        $calendarEventsJourneyModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsJourneyModel::class);
        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);
        /** @var PageModel $pageModelAdapter */
        $pageModelAdapter = $this->get('contao.framework')->getAdapter(PageModel::class);
        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);
        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        $objEvent = $calendarEventsModelAdapter->findByIdOrAlias($inputAdapter->get('events'));

        if (null === $objEvent) {
            return null;
        }

        $objForm = new Form(
            'form-event-registration',
            'POST',
            function ($objHaste) {
                /** @var Input $inputAdapter */
                $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

                return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        $objJourney = $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey);

        if (null !== $objJourney) {
            if ('public-transport' === $objJourney->alias) {
                $objForm->addFormField('ticketInfo', [
                    'label' => 'Ich besitze ein/eine',
                    'inputType' => 'select',
                    'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'],
                    'eval' => ['includeBlankOption' => false, 'mandatory' => true],
                ]);
            }
        }

        $objJourney = $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey);

        if (null !== $objJourney) {
            if ('car' === $objJourney->alias) {
                $objForm->addFormField('carInfo', [
                    'label' => 'Ich könnte ein Auto mit ... Plätzen (inkl. Fahrer) mitnehmen',
                    'inputType' => 'select',
                    'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'],
                    'eval' => ['includeBlankOption' => true, 'mandatory' => true],
                ]);
            }
        }

        $objForm->addFormField('mobile', [
            'label' => 'Mobilnummer',
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'rgxp' => 'phone'],
        ]);
        $objForm->addFormField('emergencyPhone', [
            'label' => 'Notfalltelefonnummer/In Notfällen zu kontaktieren',
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'rgxp' => 'phone'],
        ]);
        $objForm->addFormField('emergencyPhoneName', [
            'label' => 'Name und Bezug der angehörigen Person, welche im Notfall zu kontaktieren ist',
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
        ]);
        $objForm->addFormField('notes', [
            'label' => 'Anmerkungen/Erfahrungen/Referenztouren',
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'rows' => 4],
            'class' => '',
        ]);

        // Only show this field if it is a multi day event
        $durationInDays = \count($calendarEventsHelperAdapter->getEventTimestamps($objEvent));
        $startDate = $calendarEventsHelperAdapter->getStartDate($objEvent);
        $endDate = $calendarEventsHelperAdapter->getEndDate($objEvent);

        if ($durationInDays > 1 && $startDate + ($durationInDays - 1) * 86400 === $endDate) {
            $objForm->addFormField('foodHabits', [
                'label' => 'Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)',
                'inputType' => 'text',
                'eval' => ['mandatory' => false],
            ]);
        }

        $objForm->addFormField('agb', [
            'label' => ['', 'Ich akzeptiere <a href="#" data-toggle="modal" data-target="#agbModal">das Kurs- und Tourenreglement.</a>'],
            'inputType' => 'checkbox',
            'eval' => ['mandatory' => true],
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => 'Für Event anmelden',
            'inputType' => 'submit',
        ]);

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        // Get form presets from tl_member
        $arrFields = ['mobile', 'emergencyPhone', 'emergencyPhoneName', 'foodHabits'];

        foreach ($arrFields as $field) {
            if ($objForm->hasFormField($field)) {
                $objWidget = $objForm->getWidget($field);

                if (empty($objWidget->value)) {
                    $objWidget = $objForm->getWidget($field);
                    $objWidget->value = $this->objUser->{$field};
                }
            }
        }

        // validate() also checks whether the form has been submitted
        if ($objForm->validate()) {
            $hasError = false;

            // Validate sacMemberId
            $objMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_member WHERE id=? AND disable=?')->limit(1)->execute($this->objUser->id, '');

            if (!$objMember->numRows) {
                $this->template->bookingErrorMsg = sprintf('Der Benutzer mit ID "%s" wurde nicht in der Mitgliederdatenbank gefunden.', $this->objUser->id);
                $hasError = true;
            }

            if (!$hasError) {
                // Prevent duplicate entries
                $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND contaoMemberId=?')->execute($this->objEvent->id, $this->objUser->id);

                if ($objDb->numRows) {
                    $this->template->bookingErrorMsg = 'Für diesen Event liegt von dir bereits eine Anmeldung vor.';
                    $hasError = true;
                }
            }

            if (!$hasError) {
                if (true === $calendarEventsHelperAdapter->areBookingDatesOccupied($this->objEvent, $this->objUser)) {
                    $this->template->bookingErrorMsg = 'Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.';
                    $hasError = true;
                }
            }

            $this->template->hasBookingError = $hasError;

            // Save data to tl_calendar_events_member
            if (!$hasError) {
                $objMemberModel = $memberModelAdapter->findByPk($this->objUser->id);

                if (null !== $objMemberModel) {
                    $arrData = $objForm->fetchAll();
                    $arrData = array_merge($objMemberModel->row(), $arrData);
                    $arrData['contaoMemberId'] = $objMemberModel->id;
                    $arrData['eventName'] = $this->objEvent->title;
                    $arrData['eventId'] = $this->objEvent->id;
                    $arrData['addedOn'] = time();
                    $arrData['stateOfSubscription'] = 'subscription-not-confirmed';
                    $arrData['bookingType'] = 'onlineForm';
                    $arrData['sectionIds'] = $objMemberModel->sectionId;

                    // Save emergency phone number to user profile
                    if (empty($objMemberModel->emergencyPhone)) {
                        $objMemberModel->emergencyPhone = $arrData['emergencyPhone'];
                        $objMemberModel->save();
                    }

                    if (empty($objMemberModel->emergencyPhoneName)) {
                        $objMemberModel->emergencyPhoneName = $arrData['emergencyPhoneName'];
                        $objMemberModel->save();
                    }

                    $objEventRegistration = new CalendarEventsMemberModel();
                    unset($arrData['id']);
                    $arrData = array_filter($arrData);
                    $objEventRegistration->setRow($arrData);
                    $objEventRegistration->save();

                    // Log
                    $logger = System::getContainer()->get('monolog.logger.contao');
                    $strText = sprintf('New Registration from "%s %s [ID: %s]" for event with ID: %s ("%s").', $objMemberModel->firstname, $objMemberModel->lastname, $objMemberModel->id, $this->objEvent->id, $this->objEvent->title);
                    $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('SAC_EVT_LOG_EVENT_SUBSCRIPTION'))]);

                    $notified = $this->notifyMember($arrData, $objMemberModel, $this->objEvent, $objEventRegistration);

                    if ($this->model->jumpTo) {
                        // Redirect to jumpTo page
                        $objPageModel = $pageModelAdapter->findByPk($this->model->jumpTo);

                        if (null !== $objPageModel && $notified) {
                            $controllerAdapter->redirect($objPageModel->getFrontendUrl());
                        }
                    }
                }
            }
        }

        $this->objForm = $objForm;
    }

    /**
     * @return bool
     */
    protected function notifyMember(array $arrData, MemberModel $objMember, CalendarEventsModel $objEvent, CalendarEventsMemberModel $objEventRegistration)
    {
        $hasError = false;

        // Set adapters
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->get('contao.framework')->getAdapter(UserModel::class);
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        /** @var Events $eventsAdapter */
        $eventsAdapter = $this->get('contao.framework')->getAdapter(Events::class);

        // Switch sender/recipient if the main instructor has delegated event registrations administration work to somebody else
        $bypassRegistration = false;

        if ($objEvent->registrationGoesTo) {
            $strRegistrationGoesToName = '';
            $strRegistrationGoesToEmail = '';
            $userId = $objEvent->registrationGoesTo;

            $objUser = $userModelAdapter->findByPk($userId);

            if (null !== $objUser) {
                if ('' !== $objUser->email) {
                    $strRegistrationGoesToName = $objUser->name;
                    $strRegistrationGoesToEmail = $objUser->email;
                }
            }

            if ('' !== $strRegistrationGoesToEmail && '' !== $strRegistrationGoesToName) {
                $bypassRegistration = true;
            }
        }

        // Use terminal42/notification_center
        if (null !== $this->objNotification) {
            // Get the event type
            $eventType = \strlen($GLOBALS['TL_LANG']['MSC'][$this->objEvent->eventType]) ? $GLOBALS['TL_LANG']['MSC'][$this->objEvent->eventType].': ' : '';

            // Check if event is already fully booked
            $eventFullyBooked = false;

            if (true === $calendarEventsHelperAdapter->eventIsFullyBooked($objEvent)) {
                $eventFullyBooked = true;
                $objEventRegistration->stateOfSubscription = 'subscription-waitlisted';
                $objEventRegistration->save();
            }



            // Set token array
            $arrTokens = [
                'event_name' => html_entity_decode((string) $eventType.$this->objEvent->title),
                'event_type' => html_entity_decode((string) $objEvent->eventType),
                'event_course_id' => $objEvent->courseId,
                'instructor_name' => $bypassRegistration ? html_entity_decode((string) $strRegistrationGoesToName) : html_entity_decode((string) $this->objInstructor->name),
                'instructor_email' => $bypassRegistration ? html_entity_decode((string) $strRegistrationGoesToEmail) : html_entity_decode((string) $this->objInstructor->email),
                'participant_name' => html_entity_decode($objMember->firstname.' '.$objMember->lastname),
                'participant_email' => $objMember->email !== $arrData['email'] ? $arrData['email'] : $objMember->email,
                'participant_emergency_phone' => $arrData['emergencyPhone'],
                'participant_emergency_phone_name' => html_entity_decode((string) $arrData['emergencyPhoneName']),
                'participant_street' => html_entity_decode((string) $objMember->street),
                'participant_postal' => $objMember->postal,
                'participant_city' => html_entity_decode((string) $objMember->city),
                'participant_contao_member_id' => $objMember->id,
                'participant_sac_member_id' => $objMember->sacMemberId,
                'participant_section_membership' => $calendarEventsHelperAdapter->getSectionMembershipAsString($objMember),
                'participant_mobile' => $arrData['mobile'],
                'participant_date_of_birth' => $arrData['dateOfBirth'] > 0 ? $dateAdapter->parse('d.m.Y', $arrData['dateOfBirth']) : '---',
                'participant_food_habits' => $arrData['foodHabits'],
                'participant_notes' => html_entity_decode((string) $arrData['notes']),
                'event_id' => $objEvent->id,
                'event_link_detail' => 'https://'.$environmentAdapter->get('host').'/'.$eventsAdapter->generateEventUrl($this->objEvent),
                'event_state' => true === $eventFullyBooked ? 'fully-booked' : '',
            ];

            if ($hasError) {
                return false;
            }

            $this->objNotification->send($arrTokens, 'de');

            return true;
        }
    }

    private function setTemplateVars(): void
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);
        /** @var EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->get('contao.framework')->getAdapter(EventOrganizerModel::class);
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);
        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        if ('tour' === $this->objEvent->eventType || 'last-minute-tour' === $this->objEvent->eventType || 'course' === $this->objEvent->eventType) {
            $objEvent = $this->objEvent;
            $arrOrganizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);

            if (isset($arrOrganizers[0])) {
                $objOrganizer = $eventOrganizerModelAdapter->findByPk($arrOrganizers[0]);

                if (null !== $objOrganizer) {
                    $prefix = '';

                    if ('tour' === $this->objEvent->eventType || 'last-minute-tour' === $this->objEvent->eventType) {
                        $prefix = 'tour';
                    }

                    if ('course' === $this->objEvent->eventType) {
                        $prefix = 'course';
                    }

                    if ('' !== $prefix) {
                        if ('' !== $objOrganizer->{$prefix.'RegulationSRC'}) {
                            if ($validatorAdapter->isBinaryUuid($objOrganizer->{$prefix.'RegulationSRC'})) {
                                $objFile = $filesModelAdapter->findByUuid($objOrganizer->{$prefix.'RegulationSRC'});

                                if (null !== $objFile && is_file($this->projectDir.'/'.$objFile->path)) {
                                    $this->template->objEventRegulationFile = $objFile;
                                }
                            }
                        }

                        if ('' !== $objOrganizer->{$prefix.'RegulationExtract'}) {
                            $this->template->eventRegulationExtract = $objOrganizer->{$prefix.'RegulationExtract'};
                        }
                    }
                }
            }
        }
    }
}
