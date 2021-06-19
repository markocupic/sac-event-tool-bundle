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
use Markocupic\SacEventToolBundle\Event\EventSubscriptionEvent;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Security;

/**
 * Class EventRegistrationFormController.
 *
 * @FrontendModule(EventRegistrationFormController::TYPE, category="sac_event_tool_frontend_modules")
 */
class EventRegistrationFormController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_registration_form';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var ModuleModel
     */
    private $moduleModel;

    /**
     * @var CalendarEventsModel
     */
    private $eventModel;

    /**
     * @var FrontendUser
     */
    private $memberModel;

    /**
     * @var UserModel
     */
    private $mainInstructorModel;

    /**
     * @var Form
     */
    private $objForm;

    /**
     * @var Template
     */
    private $template;

    public function __construct(ContaoFramework $framework, EventDispatcherInterface $eventDispatcher)
    {
        $this->framework = $framework;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        /** @var projectDir */
        $this->projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Set the module object (Contao\ModuleModel)
        $this->moduleModel = $model;

        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);
        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->get('contao.framework')->getAdapter(UserModel::class);
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser) {
            /** @var MemberModel objUser */
            $this->memberModel = MemberModel::findByPk($objUser->id);
        }

        // Set the item from the auto_item parameter
        if (!isset($_GET['events']) && $configAdapter->get('useAutoItem') && isset($_GET['auto_item'])) {
            $inputAdapter->setGet('events', $inputAdapter->get('auto_item'));
        }

        // Get $this->eventModel from GET
        $this->eventModel = $calendarEventsModelAdapter->findByIdOrAlias($inputAdapter->get('events'));

        // Get instructor object from UserModel
        $this->mainInstructorModel = $userModelAdapter->findByPk($this->eventModel->mainInstructor);

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
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        /** @var Session $session */
        $session = System::getContainer()->get('session');
        $flash = $session->getFlashBag();
        $sessInfKey = 'contao.FE.info';
        $sessErrKey = 'contao.FE.error';

        $this->template->objUser = $this->memberModel;
        $this->template->objEvent = $this->eventModel;

        // Set other template vars
        $this->setTemplateVars();

        // Show errors after form submit @see $this->generateForm()
        $this->template->hasBookingError = false;
        $this->template->bookingErrorMsg = '';

        // Count accepted registrations
        $objMember = $databaseAdapter->getInstance()
            ->prepare('SELECT COUNT(id) AS countAcceptedRegistrations FROM tl_calendar_events_member WHERE eventId=? AND stateOfSubscription=? AND contaoMemberId IN (SELECT id FROM tl_member WHERE disable=?)')
            ->execute($this->eventModel->id, 'subscription-accepted', '')
        ;
        $this->template->countAcceptedRegistrations = $objMember->countAcceptedRegistrations;

        if (null === $this->eventModel) {
            $flash->set($sessInfKey, sprintf('Event mit ID: %s nicht gefunden.', $inputAdapter->get('events') ?: 'NULL'));
        } elseif ($this->eventModel->disableOnlineRegistration) {
            $flash->set($sessInfKey, 'Eine Online-Anmeldung zu diesem Event ist nicht möglich.', $scope);
        } elseif (null === $this->memberModel) {
            $flash->set($sessInfKey, 'Bitte logge dich mit deinem Mitglieder-Konto ein, um dich für den Event anzumelden.', $scope);
            $this->template->showLoginForm = true;
        } elseif (null !== $this->memberModel && true === $calendarEventsMemberModelAdapter->isRegistered($this->memberModel->id, $this->eventModel->id)) {
            $flash->set($sessInfKey, 'Du hast dich bereits für diesen Event angemeldet.', $scope);
        } elseif ('event_fully_booked' === $this->eventModel->eventState) {
            $flash->set($sessInfKey, 'Dieser Anlass ist ausgebucht. Bitte erkundige dich beim Leiter, ob eine Nachmeldung möglich ist.', $scope);
        } elseif ('event_canceled' === $this->eventModel->eventState) {
            $flash->set($sessInfKey, 'Dieser Anlass wurde abgesagt. Es ist keine Anmeldung möglich.', $scope);
        } elseif ('event_deferred' === $this->eventModel->eventState) {
            $flash->set($sessInfKey, 'Dieser Anlass ist verschoben worden.', $scope);
        } elseif ($this->eventModel->setRegistrationPeriod && $this->eventModel->registrationStartDate > time()) {
            $flash->set($sessInfKey, sprintf('Anmeldungen für <strong>"%s"</strong> sind erst ab dem %s möglich.', $this->eventModel->title, $dateAdapter->parse('d.m.Y H:i', $this->eventModel->registrationStartDate)), $scope);
        } elseif ($this->eventModel->setRegistrationPeriod && $this->eventModel->registrationEndDate < time()) {
            $flash->set($sessInfKey, sprintf('Die Anmeldefrist für diesen Event ist am %s abgelaufen.', $dateAdapter->parse('d.m.Y \u\m H:i', $this->eventModel->registrationEndDate)), $scope);
        } elseif (!$this->eventModel->setRegistrationPeriod && $this->eventModel->startDate - 60 * 60 * 24 < time()) {
            $flash->set($sessInfKey, 'Die Anmeldefrist für diesen Event ist abgelaufen. Du kannst dich bis 24 Stunden vor Event-Beginn anmelden. Nimm gegebenenfalls mit dem Leiter Kontakt auf.', $scope);
        } elseif ($this->memberModel && true === $calendarEventsHelperAdapter->areBookingDatesOccupied($this->eventModel, $this->memberModel)) {
            $flash->set($sessInfKey, 'Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.', $scope);
        } elseif (null === $this->mainInstructorModel) {
            $flash->set($sessErrKey, 'Der Hauptleiter mit ID '.$this->eventModel->mainInstructor.' wurde nicht in der Datenbank gefunden. Bitte nimm persönlich Kontakt mit dem Leiter auf.', $scope);
        } elseif (empty($this->mainInstructorModel->email) || !$validatorAdapter->isEmail($this->mainInstructorModel->email)) {
            $flash->set($sessErrKey, 'Dem Hauptleiter mit ID '.$this->eventModel->mainInstructor.' ist keine gültige E-Mail zugewiesen. Bitte nimm persönlich mit dem Leiter Kontakt auf.', $scope);
        } elseif (empty($this->memberModel->email) || !$validatorAdapter->isEmail($this->memberModel->email)) {
            $flash->set($sessErrKey, 'Leider wurde für dieses Mitgliederkonto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlege auf auf der Internetseite des Zentralverbands deine E-Mail-Adresse.');
        }

        // Add messages to the template
        if ($flash->has($sessInfKey) || $flash->has($sessErrKey)) {
            if ($flash->has($sessErrKey)) {
                $this->template->hasErrorMessage = true;
                $errorMessage = $flash->get($sessErrKey)[0];
                $this->template->errorMessage = $errorMessage;

                // Log
                $logger = System::getContainer()->get('monolog.logger.contao');
                $strText = sprintf('Event registration error: "%s"', $errorMessage);
                $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR'))]);
            }

            if ($flash->has($sessInfKey)) {
                $this->template->hasInfoMessage = true;
                $infoMessage = $flash->get($sessInfKey)[0];
                $this->template->infoMessage = $infoMessage;
            }
        } else {
            // All ok! Generate the registration form;
            $this->generateForm();

            if (null !== $this->objForm) {
                $this->template->form = $this->objForm->generate();
                $this->template->objForm = $this->objForm;
            }

            // Check if event is already fully booked
            if (true === $calendarEventsHelperAdapter->eventIsFullyBooked($this->eventModel)) {
                $this->template->bookingLimitReaches = true;
            }
        }

        return $this->template->getResponse();
    }

    private function generateForm()
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

        $objDb = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_member WHERE ticketInfo=?')
            ->execute('')
        ;

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
                $objForm->addFormField(
                    'ticketInfo',
                    [
                        'label' => 'Ich besitze ein/eine',
                        'inputType' => 'select',
                        'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'],
                        'eval' => ['includeBlankOption' => false, 'mandatory' => true],
                    ]
                );
            }
        }

        $objJourney = $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey);

        if (null !== $objJourney) {
            if ('car' === $objJourney->alias) {
                $objForm->addFormField(
                    'carInfo',
                    [
                        'label' => 'Ich könnte ein Auto mit ... Plätzen (inkl. Fahrer) mitnehmen',
                        'inputType' => 'select',
                        'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'],
                        'eval' => ['includeBlankOption' => true, 'mandatory' => true],
                    ]
                );
            }
        }

        if ($objEvent->askForAhvNumber) {
            $objForm->addFormField(
                'ahvNumber',
                [
                    'label' => 'AHV-Nummer',
                    'inputType' => 'text',
                    'eval' => ['mandatory' => true, 'maxlength' => 16, 'rgxp' => 'alnum', 'placeholder' => '756.1234.5678.97'],
                ]
            );
        }
        $objForm->addFormField(
            'mobile',
            [
                'label' => 'Mobilnummer',
                'inputType' => 'text',
                'eval' => ['mandatory' => false, 'rgxp' => 'phone'],
            ]
        );
        $objForm->addFormField(
            'emergencyPhone',
            [
                'label' => 'Notfalltelefonnummer/In Notfällen zu kontaktieren',
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'rgxp' => 'phone'],
            ]
        );
        $objForm->addFormField(
            'emergencyPhoneName',
            [
                'label' => 'Name und Bezug der angehörigen Person, welche im Notfall zu kontaktieren ist',
                'inputType' => 'text',
                'eval' => ['mandatory' => true],
            ]
        );
        $objForm->addFormField(
            'notes',
            [
                'label' => 'Anmerkungen/Erfahrungen/Referenztouren',
                'inputType' => 'textarea',
                'eval' => ['mandatory' => true, 'rows' => 4],
                'class' => '',
            ]
        );

        // Only show this field if it is a multi day event
        $durationInDays = \count($calendarEventsHelperAdapter->getEventTimestamps($objEvent));
        $startDate = $calendarEventsHelperAdapter->getStartDate($objEvent);
        $endDate = $calendarEventsHelperAdapter->getEndDate($objEvent);

        if ($durationInDays > 1 && $startDate + ($durationInDays - 1) * 86400 === $endDate) {
            $objForm->addFormField(
                'foodHabits',
                [
                    'label' => 'Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)',
                    'inputType' => 'text',
                    'eval' => ['mandatory' => false],
                ]
            );
        }

        $objForm->addFormField(
            'agb',
            [
                'label' => ['', 'Ich akzeptiere <a href="#" data-bs-toggle="modal" data-bs-target="#agbModal">das Kurs- und Tourenreglement.</a>'],
                'inputType' => 'checkbox',
                'eval' => ['mandatory' => true],
            ]
        );

        // Let's add  a submit button
        $objForm->addFormField(
            'submit',
            [
                'label' => 'Für Event anmelden',
                'inputType' => 'submit',
            ]
        );

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        // Get form presets from tl_member
        $arrFields = ['mobile', 'emergencyPhone', 'emergencyPhoneName', 'foodHabits', 'ahvNumber'];

        foreach ($arrFields as $field) {
            if ($objForm->hasFormField($field)) {
                $objWidget = $objForm->getWidget($field);

                if (empty($objWidget->value)) {
                    $objWidget = $objForm->getWidget($field);
                    $objWidget->value = $this->memberModel->{$field};
                }
            }
        }

        // validate() also checks whether the form has been submitted
        if ($objForm->validate()) {
            $hasError = false;

            // Validate sacMemberId
            $objMember = $databaseAdapter->getInstance()
                ->prepare('SELECT * FROM tl_member WHERE id=? AND disable=?')
                ->limit(1)
                ->execute($this->memberModel->id, '')
            ;

            if (!$objMember->numRows) {
                $this->template->bookingErrorMsg = sprintf('Der Benutzer mit ID "%s" wurde nicht in der Mitgliederdatenbank gefunden.', $this->memberModel->id);
                $hasError = true;
            }

            if (!$hasError) {
                // Prevent duplicate entries
                $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND contaoMemberId=?')->execute($this->eventModel->id, $this->memberModel->id);

                if ($objDb->numRows) {
                    $this->template->bookingErrorMsg = 'Für diesen Event liegt von dir bereits eine Anmeldung vor.';
                    $hasError = true;
                }
            }

            if (!$hasError) {
                if (true === $calendarEventsHelperAdapter->areBookingDatesOccupied($this->eventModel, $this->memberModel)) {
                    $this->template->bookingErrorMsg = 'Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.';
                    $hasError = true;
                }
            }

            $this->template->hasBookingError = $hasError;

            // Save data to tl_calendar_events_member
            if (!$hasError) {
                if (null !== $this->memberModel) {
                    $arrDataForm = $objForm->fetchAll();
                    $arrData = array_merge($this->memberModel->row(), $arrDataForm);

                    // Do not send ahv number if it is not required
                    if (!isset($arrDataForm['ahvNumber'])) {
                        unset($arrData['ahvNumber']);
                    }

                    $arrData['contaoMemberId'] = $this->memberModel->id;
                    $arrData['eventName'] = $this->eventModel->title;
                    $arrData['eventId'] = $this->eventModel->id;
                    $arrData['addedOn'] = time();
                    $arrData['stateOfSubscription'] = 'subscription-not-confirmed';
                    $arrData['bookingType'] = 'onlineForm';
                    $arrData['sectionIds'] = $this->memberModel->sectionId;

                    // Save emergency phone number to user profile
                    if (empty($this->memberModel->emergencyPhone)) {
                        $this->memberModel->emergencyPhone = $arrData['emergencyPhone'];
                        $this->memberModel->save();
                    }

                    // Save emergency phone name to user profile
                    if (empty($this->memberModel->emergencyPhoneName)) {
                        $this->memberModel->emergencyPhoneName = $arrData['emergencyPhoneName'];
                        $this->memberModel->save();
                    }

                    // Save emergency phone name to user profile
                    if (!empty($arrData['ahvNumber'])) {
                        $this->memberModel->ahvNumber = $arrData['ahvNumber'];
                        $this->memberModel->save();
                    }

                    $objEventRegistration = new CalendarEventsMemberModel();
                    unset($arrData['id']);
                    $arrData = array_filter($arrData);
                    $objEventRegistration->setRow($arrData);
                    $objEventRegistration->save();

                    if (true === $calendarEventsHelperAdapter->eventIsFullyBooked($objEvent)) {
                        $objEventRegistration->stateOfSubscription = 'subscription-waitlisted';
                        $objEventRegistration->save();
                    }

                    // Log
                    $logger = System::getContainer()->get('monolog.logger.contao');
                    $strText = sprintf('New Registration from "%s %s [ID: %s]" for event with ID: %s ("%s").', $this->memberModel->firstname, $this->memberModel->lastname, $this->memberModel->id, $this->eventModel->id, $this->eventModel->title);
                    $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('SAC_EVT_LOG_EVENT_SUBSCRIPTION'))]);

                    // Dispatch event subscription event (e.g. send notification)
                    $event = new \stdClass();
                    $event->framework = $this->framework;
                    $event->arrData = $arrData;
                    $event->memberModel = $this->memberModel;
                    $event->eventModel = $this->eventModel;
                    $event->eventMemberModel = $objEventRegistration;
                    $event->moduleModel = $this->moduleModel;
                    $this->eventDispatcher->dispatch(new EventSubscriptionEvent($event), EventSubscriptionEvent::NAME);

                    if ($this->moduleModel->jumpTo) {
                        // Redirect to jumpTo page
                        $objPageModel = $pageModelAdapter->findByPk($this->moduleModel->jumpTo);

                        if (null !== $objPageModel) {
                            $controllerAdapter->redirect($objPageModel->getFrontendUrl());
                        }
                    }
                }
            }
        }

        $this->objForm = $objForm;
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

        if ('tour' === $this->eventModel->eventType || 'last-minute-tour' === $this->eventModel->eventType || 'course' === $this->eventModel->eventType) {
            $objEvent = $this->eventModel;
            $arrOrganizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);

            if (isset($arrOrganizers[0])) {
                $objOrganizer = $eventOrganizerModelAdapter->findByPk($arrOrganizers[0]);

                if (null !== $objOrganizer) {
                    $prefix = '';

                    if ('tour' === $this->eventModel->eventType || 'last-minute-tour' === $this->eventModel->eventType) {
                        $prefix = 'tour';
                    }

                    if ('course' === $this->eventModel->eventType) {
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
