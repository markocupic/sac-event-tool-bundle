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
use Haste\Util\Url;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use Markocupic\SacEventToolBundle\Event\EventSubscriptionEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

/**
 * Class EventRegistrationController.
 *
 * @FrontendModule(EventRegistrationController::TYPE, category="sac_event_tool_frontend_modules")
 */
class EventRegistrationController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_registration';

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
     * @var TwigEnvironment
     */
    private $twig;

    /**
     * @var TranslatorInterface
     */
    private $translator;

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
     * @var PageModel
     */
    private $pageModel;

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

    public function __construct(Security $security, ContaoFramework $framework, SessionInterface $session, EventDispatcherInterface $eventDispatcher, TwigEnvironment $twig, TranslatorInterface $translator, string $projectDir, ?LoggerInterface $logger = null)
    {
        $this->security = $security;
        $this->framework = $framework;
        $this->session = $session;
        $this->eventDispatcher = $eventDispatcher;
        $this->twig = $twig;
        $this->translator = $translator;
        $this->projectDir = $projectDir;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        // Set the module object (Contao\ModuleModel)
        $this->moduleModel = $model;
        $this->pageModel = $page;

        // Do not index nor cache page
        $this->pageModel->noSearch = true;
        $this->pageModel->cache = false;
        $this->pageModel->clientCache = false;

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

        if (null === $this->eventModel) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventNotFound', [$inputAdapter->get('events') ?: 'NULL'], 'event_default'));
        } elseif ($this->eventModel->disableOnlineRegistration) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_onlineRegDisabled', [], 'contao_default'));
        } elseif ('event_fully_booked' === $this->eventModel->eventState) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventFullyBooked', [], 'contao_default'));
        } elseif ('event_canceled' === $this->eventModel->eventState) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventCanceled', [], 'contao_default'));
        } elseif ('event_deferred' === $this->eventModel->eventState) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventDeferred', [], 'contao_default'));
        } elseif ($this->eventModel->setRegistrationPeriod && $this->eventModel->registrationStartDate > time()) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_registrationPossibleOn', [$this->eventModel->title, $dateAdapter->parse('d.m.Y H:i', $this->eventModel->registrationStartDate)], 'contao_default'));
        } elseif ($this->eventModel->setRegistrationPeriod && $this->eventModel->registrationEndDate < time()) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_registrationDeadlineExpired', [$dateAdapter->parse('d.m.Y \u\m H:i', $this->eventModel->registrationEndDate)], 'contao_default'));
        } elseif (!$this->eventModel->setRegistrationPeriod && $this->eventModel->startDate - 60 * 60 * 24 < time()) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_registrationPossible24BeforeEventStart', [], 'contao_default'));
        } elseif ($this->memberModel && true === $calendarEventsHelperAdapter->areBookingDatesOccupied($this->eventModel, $this->memberModel)) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventDateOverlapError', [], 'contao_default'));
        } elseif (null === $this->mainInstructorModel) {
            $flash->set($sessErrKey, $this->translator->trans('ERR.evt_reg_mainInstructorNotFound', [$this->eventModel->mainInstructor], 'contao_default'));
        } elseif (empty($this->mainInstructorModel->email) || !$validatorAdapter->isEmail($this->mainInstructorModel->email)) {
            $flash->set($sessErrKey, $this->translator->trans('ERR.evt_reg_mainInstructorsEmailAddrNotFound', [$this->eventModel->mainInstructor], 'contao_default'));
        } elseif (null !== $this->memberModel && (empty($this->memberModel->email) || !$validatorAdapter->isEmail($this->memberModel->email))) {
            $flash->set($sessErrKey, $this->translator->trans('ERR.evt_reg_membersEmailAddrNotFound', [], 'contao_default'));
        }

        $this->template = $template;

        // Set more template vars
        $this->setMoreTemplateVars();
        $this->template->eventModel = $this->eventModel;
        $this->template->memberModel = $this->memberModel;

        if (null !== $this->memberModel && true === $calendarEventsMemberModelAdapter->isRegistered($this->memberModel->id, $this->eventModel->id)) {
            $this->template->regInfo = $this->parseEventRegistrationConfirmTemplate();

            if ($url = $this->getRoute('confirm')) {
                return $this->redirect($url);
            }
        }

        // Add messages to the template
        elseif ($flash->has($sessInfKey) || $flash->has($sessErrKey)) {
            // Add messages to template from session flash
            if ($flash->has($sessErrKey)) {
                $this->template->hasErrorMessage = true;
                $errorMessage = $flash->get($sessErrKey)[0];
                $this->template->errorMessage = $errorMessage;

                // Log
                if ($this->logger) {
                    $strText = sprintf('Event registration error: "%s"', $errorMessage);
                    $this->logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR'))]);
                }

                if ($url = $this->getRoute('info')) {
                    return $this->redirect($url);
                }
            }

            if ($flash->has($sessInfKey)) {
                $this->template->hasInfoMessage = true;
                $infoMessage = $flash->get($sessInfKey)[0];
                $this->template->infoMessage = $infoMessage;

                if ($url = $this->getRoute('info')) {
                    return $this->redirect($url);
                }
            }
        } elseif (null === $this->memberModel) {
            if ($url = $this->getRoute('login')) {
                return $this->redirect($url);
            }
        } else {
            // All ok! Generate the registration form.
            $this->generateForm();

            if (null !== $this->objForm) {
                $this->template->form = $this->objForm->generate();
                $this->template->objForm = $this->objForm;
            }

            // Check if event is already fully booked
            if (true === $calendarEventsHelperAdapter->eventIsFullyBooked($this->eventModel)) {
                $this->template->bookingLimitReaches = true;
            }

            if ($url = $this->getRoute('register')) {
                return $this->redirect($url);
            }
        }

        $this->template->action = $inputAdapter->get('action');
        $this->template->stepIndicator = $this->parseStepIndicatorTemplate($inputAdapter->get('action'));

        return $this->template->getResponse();
    }

    private function getRoute(string $action): ?string
    {
        /** @var Url $urlAdapter */
        $urlAdapter = $this->framework->getAdapter(Url::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        if ($inputAdapter->get('action') !== $action) {
            return $urlAdapter->addQueryString('action='.$action, $environmentAdapter->get('uri'));
        }

        return null;
    }

    private function generateForm(): void
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var CalendarEventsJourneyModel $calendarEventsJourneyModelAdapter */
        $calendarEventsJourneyModelAdapter = $this->framework->getAdapter(CalendarEventsJourneyModel::class);

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
        if ($this->isMultiDayEvent()) {
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
            // Save data to tl_calendar_events_member

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
                $arrData['stateOfSubscription'] = $calendarEventsHelperAdapter->eventIsFullyBooked($this->eventModel) ? EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED : EventSubscriptionLevel::SUBSCRIPTION_NOT_CONFIRMED;
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

                // Use a subscriber to assign notification to the event registration
                $this->eventDispatcher->dispatch(new EventSubscriptionEvent($event), EventSubscriptionEvent::NAME);

                // Reload page
                $controllerAdapter->reload();
            }
        }

        $this->objForm = $objForm;
    }

    private function setMoreTemplateVars(): void
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
                'label' => $this->translator->trans('FORM.evt_reg_ticketInfo', [], 'contao_default'),
                'inputType' => 'select',
                'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'],
                'eval' => ['includeBlankOption' => false, 'mandatory' => true],
            ],
            'carInfo' => [
                'label' => $this->translator->trans('FORM.evt_reg_carInfo', [], 'contao_default'),
                'inputType' => 'select',
                'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'],
                'eval' => ['includeBlankOption' => true, 'mandatory' => true],
            ],
            'ahvNumber' => [
                'label' => $this->translator->trans('FORM.evt_reg_ahvNumber', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'maxlength' => 16, 'rgxp' => 'alnum', 'placeholder' => '756.1234.5678.97'],
            ],
            'mobile' => [
                'label' => $this->translator->trans('FORM.evt_reg_mobile', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => false, 'rgxp' => 'phone'],
            ],
            'emergencyPhone' => [
                'label' => $this->translator->trans('FORM.evt_reg_emergencyPhone', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'rgxp' => 'phone'],
            ],
            'emergencyPhoneName' => [
                'label' => $this->translator->trans('FORM.evt_reg_emergencyPhoneName', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => true],
            ],
            'notes' => [
                'label' => $this->translator->trans('FORM.evt_reg_notes', [], 'contao_default'),
                'inputType' => 'textarea',
                'eval' => ['mandatory' => true, 'rows' => 4],
                'class' => '',
            ],
            'foodHabits' => [
                'label' => $this->translator->trans('FORM.evt_reg_foodHabits', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => false],
            ],
            'agb' => [
                'label' => ['', $this->translator->trans('FORM.evt_reg_agb.1', [], 'contao_default')],
                'inputType' => 'checkbox',
                'eval' => ['mandatory' => true],
            ],
            'submit' => [
                'label' => $this->translator->trans('FORM.evt_reg_submit', [], 'contao_default'),
                'inputType' => 'submit',
            ],
        ];

        return $formFields[$field];
    }

    private function isMultiDayEvent(): bool
    {
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        $durationInDays = \count($calendarEventsHelperAdapter->getEventTimestamps($this->eventModel));
        $startDate = $calendarEventsHelperAdapter->getStartDate($this->eventModel);
        $endDate = $calendarEventsHelperAdapter->getEndDate($this->eventModel);

        if ($durationInDays > 1 && $startDate + ($durationInDays - 1) * 86400 === $endDate) {
            return true;
        }

        return false;
    }

    private function parseEventRegistrationConfirmTemplate(): string
    {
        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var Events $eventsAdapter */
        $eventsAdapter = $this->framework->getAdapter(Events::class);

        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        if (null !== ($objEventsMemberModel = $calendarEventsMemberModelAdapter->findByMemberAndEvent($this->memberModel, $this->eventModel))) {
            $arrEvent = $this->eventModel->row();
            $arrEventsMember = $objEventsMemberModel->row();
            $arrMember = $this->memberModel->row();

            $arrEventsMember['stateOfSubscriptionTrans'] = $this->translator->trans('tl_calendar_events_member.'.$arrEventsMember['stateOfSubscription'], [], 'contao_default');
            $arrEvent['eventUrl'] = $eventsAdapter->generateEventUrl($this->eventModel);

            $arrEvent = array_map('html_entity_decode', $arrEvent);
            $arrEventsMember = array_map('html_entity_decode', $arrEventsMember);
            $arrMember = array_map('html_entity_decode', $arrMember);

            return $this->twig->render(
                '@MarkocupicSacEventTool/EventRegistration/event_registration_confirm.html.twig',
                [
                    'event_model' => $arrEvent,
                    'event_member_model' => $arrEventsMember,
                    'member_model' => $arrMember,
                ]
            );
        }

        return '';
    }

    private function parseStepIndicatorTemplate(string $strStep): string
    {
        return $this->twig->render(
            '@MarkocupicSacEventTool/EventRegistration/event_registration_step_indicator.html.twig',
            [
                'step' => $strStep,
            ]
        );
    }
}
