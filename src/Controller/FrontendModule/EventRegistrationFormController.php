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
use Contao\Template;
use Contao\UserModel;
use Contao\Validator;
use Haste\Form\Form;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Event\EventSubscriptionEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
     * @var Security
     */
    private $security;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

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

    public function __construct(Security $security, ContaoFramework $framework, SessionInterface $session, EventDispatcherInterface $eventDispatcher, string $projectDir, ?LoggerInterface $logger = null)
    {
        $this->security = $security;
        $this->framework = $framework;
        $this->session = $session;
        $this->eventDispatcher = $eventDispatcher;
        $this->projectDir = $projectDir;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        // Set the module object (Contao\ModuleModel)
        $this->moduleModel = $model;

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        if (($objUser = $this->security->getUser()) instanceof FrontendUser) {
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

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->template = $template;

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $flash = $this->session->getFlashBag();
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
            $flash->set($sessInfKey, 'Eine Online-Anmeldung zu diesem Event ist nicht möglich.');
        } elseif (null === $this->memberModel) {
            $flash->set($sessInfKey, 'Bitte logge dich mit deinem Mitglieder-Konto ein, um dich für den Event anzumelden.');
            $this->template->showLoginForm = true;
        } elseif (null !== $this->memberModel && true === $calendarEventsMemberModelAdapter->isRegistered($this->memberModel->id, $this->eventModel->id)) {
            $flash->set($sessInfKey, 'Zu diesem Event liegt bereits eine Anmeldung von dir vor.');
        } elseif ('event_fully_booked' === $this->eventModel->eventState) {
            $flash->set($sessInfKey, 'Dieser Anlass ist ausgebucht. Bitte erkundige dich beim Leiter, ob eine Nachmeldung möglich ist.');
        } elseif ('event_canceled' === $this->eventModel->eventState) {
            $flash->set($sessInfKey, 'Dieser Anlass wurde abgesagt. Es ist keine Anmeldung möglich.');
        } elseif ('event_deferred' === $this->eventModel->eventState) {
            $flash->set($sessInfKey, 'Dieser Anlass ist verschoben worden.');
        } elseif ($this->eventModel->setRegistrationPeriod && $this->eventModel->registrationStartDate > time()) {
            $flash->set($sessInfKey, sprintf('Anmeldungen für <strong>"%s"</strong> sind erst ab dem %s möglich.', $this->eventModel->title, $dateAdapter->parse('d.m.Y H:i', $this->eventModel->registrationStartDate)));
        } elseif ($this->eventModel->setRegistrationPeriod && $this->eventModel->registrationEndDate < time()) {
            $flash->set($sessInfKey, sprintf('Die Anmeldefrist für diesen Event ist am %s abgelaufen.', $dateAdapter->parse('d.m.Y \u\m H:i', $this->eventModel->registrationEndDate)));
        } elseif (!$this->eventModel->setRegistrationPeriod && $this->eventModel->startDate - 60 * 60 * 24 < time()) {
            $flash->set($sessInfKey, 'Die Anmeldefrist für diesen Event ist abgelaufen. Du kannst dich bis 24 Stunden vor Event-Beginn anmelden. Nimm gegebenenfalls mit dem Leiter Kontakt auf.');
        } elseif ($this->memberModel && true === $calendarEventsHelperAdapter->areBookingDatesOccupied($this->eventModel, $this->memberModel)) {
            $flash->set($sessInfKey, 'Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.');
        } elseif (null === $this->mainInstructorModel) {
            $flash->set($sessErrKey, 'Der Hauptleiter mit ID '.$this->eventModel->mainInstructor.' wurde nicht in der Datenbank gefunden. Bitte nimm persönlich Kontakt mit dem Leiter auf.');
        } elseif (empty($this->mainInstructorModel->email) || !$validatorAdapter->isEmail($this->mainInstructorModel->email)) {
            $flash->set($sessErrKey, 'Dem Hauptleiter mit ID '.$this->eventModel->mainInstructor.' ist keine gültige E-Mail zugewiesen. Bitte nimm persönlich mit dem Leiter Kontakt auf.');
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
                if ($this->logger) {
                    $strText = sprintf('Event registration error: "%s"', $errorMessage);
                    $this->logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR'))]);
                }
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

    private function generateForm(): void
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var CalendarEventsJourneyModel $calendarEventsJourneyModelAdapter */
        $calendarEventsJourneyModelAdapter = $this->framework->getAdapter(CalendarEventsJourneyModel::class);

        /** @var PageModel $pageModelAdapter */
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $objForm = new Form(
            'form-event-registration',
            'POST',
            function ($objHaste) {
                /** @var Input $inputAdapter */
                $inputAdapter = $this->framework->getAdapter(Input::class);

                return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        if (null !== ($objJourney = $calendarEventsJourneyModelAdapter->findByPk($this->eventModel->journey))) {
            if ('public-transport' === $objJourney->alias) {
                $objForm->addFormField('ticketInfo', $this->getFormFieldDca('ticketInfo'));
            }

            if ('car' === $objJourney->alias) {
                $objForm->addFormField('carInfo', $this->getFormFieldDca('carInfo'));
            }
        }

        if ($this->eventModel->askForAhvNumber) {
            $objForm->addFormField('ahvNumber', $this->getFormFieldDca('ahvNumber'));
        }

        $objForm->addFormField('mobile', $this->getFormFieldDca('mobile'));

        $objForm->addFormField('emergencyPhone', $this->getFormFieldDca('emergencyPhone'));

        $objForm->addFormField('emergencyPhoneName', $this->getFormFieldDca('emergencyPhoneName'));

        $objForm->addFormField('notes', $this->getFormFieldDca('notes'));

        // Only show this field if it is a multi day event
        $durationInDays = \count($calendarEventsHelperAdapter->getEventTimestamps($this->eventModel));
        $startDate = $calendarEventsHelperAdapter->getStartDate($this->eventModel);
        $endDate = $calendarEventsHelperAdapter->getEndDate($this->eventModel);

        if ($durationInDays > 1 && $startDate + ($durationInDays - 1) * 86400 === $endDate) {
            $objForm->addFormField('foodHabits', $this->getFormFieldDca('foodHabits'));
        }

        $objForm->addFormField('agb', $this->getFormFieldDca('agb'));

        // Let's add  a submit button
        $objForm->addFormField('submit', $this->getFormFieldDca('submit'));

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
            $blnError = false;

            // Prevent duplicate entries
            $objDb = $databaseAdapter->getInstance()
                ->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND contaoMemberId=?')
                ->execute($this->eventModel->id, $this->memberModel->id)
                ;

            if ($objDb->numRows) {
                $this->template->bookingErrorMsg = 'Für diesen Event liegt von dir bereits eine Anmeldung vor.';
                $blnError = true;
            }

            if (!$blnError) {
                if (true === $calendarEventsHelperAdapter->areBookingDatesOccupied($this->eventModel, $this->memberModel)) {
                    $this->template->bookingErrorMsg = 'Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.';
                    $blnError = true;
                }
            }

            $this->template->hasBookingError = $blnError;

            // Save data to tl_calendar_events_member
            if (!$blnError) {
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

                    if (true === $calendarEventsHelperAdapter->eventIsFullyBooked($this->eventModel)) {
                        $objEventRegistration->stateOfSubscription = 'subscription-waitlisted';
                        $objEventRegistration->save();
                    }

                    // Log
                    if ($this->logger) {
                        $strText = sprintf('New Registration from "%s %s [ID: %s]" for event with ID: %s ("%s").', $this->memberModel->firstname, $this->memberModel->lastname, $this->memberModel->id, $this->eventModel->id, $this->eventModel->title);
                        $this->logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('SAC_EVT_LOG_EVENT_SUBSCRIPTION'))]);
                    }

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
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        if ('tour' === $this->eventModel->eventType || 'last-minute-tour' === $this->eventModel->eventType || 'course' === $this->eventModel->eventType) {
            $arrOrganizers = $stringUtilAdapter->deserialize($this->eventModel->organizers, true);

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

    private function getFormFieldDca(string $field): array
    {
        $formFields = [
            'ticketInfo' => [
                'label' => 'Ich besitze ein/eine',
                'inputType' => 'select',
                'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'],
                'eval' => ['includeBlankOption' => false, 'mandatory' => true],
            ],
            'carInfo' => [
                'label' => 'Ich könnte ein Auto mit ... Plätzen (inkl. Fahrer) mitnehmen',
                'inputType' => 'select',
                'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'],
                'eval' => ['includeBlankOption' => true, 'mandatory' => true],
            ],
            'ahvNumber' => [
                'label' => 'AHV-Nummer',
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'maxlength' => 16, 'rgxp' => 'alnum', 'placeholder' => '756.1234.5678.97'],
            ],
            'mobile' => [
                'label' => 'Mobilnummer',
                'inputType' => 'text',
                'eval' => ['mandatory' => false, 'rgxp' => 'phone'],
            ],
            'emergencyPhone' => [
                'label' => 'Notfalltelefonnummer/In Notfällen zu kontaktieren',
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'rgxp' => 'phone'],
            ],
            'emergencyPhoneName' => [
                'label' => 'Name und Bezug der angehörigen Person, welche im Notfall zu kontaktieren ist',
                'inputType' => 'text',
                'eval' => ['mandatory' => true],
            ],
            'notes' => [
                'label' => 'Anmerkungen/Erfahrungen/Referenztouren',
                'inputType' => 'textarea',
                'eval' => ['mandatory' => true, 'rows' => 4],
                'class' => '',
            ],
            'foodHabits' => [
                'label' => 'Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)',
                'inputType' => 'text',
                'eval' => ['mandatory' => false],
            ],
            'agb' => [
                'label' => ['', 'Ich akzeptiere <a href="#" data-bs-toggle="modal" data-bs-target="#agbModal">das Kurs- und Tourenreglement.</a>'],
                'inputType' => 'checkbox',
                'eval' => ['mandatory' => true],
            ],
            'submit' => [
                'label' => 'Für Event anmelden',
                'inputType' => 'submit',
            ],
        ];

        return $formFields[$field];
    }
}
