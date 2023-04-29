<?php

/** @noinspection PhpUndefinedFieldInspection */

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Codefog\HasteBundle\Form\Form;
use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\Environment;
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
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Event\EventRegistrationEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsFrontendModule(EventRegistrationController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_event_registration')]
class EventRegistrationController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_registration';
    public const CHECKOUT_STEP_LOGIN = 'login';
    public const CHECKOUT_STEP_REGISTER = 'register';
    public const CHECKOUT_STEP_CONFIRM = 'confirm';

    // Adapters
    private Adapter $calendarEventsHelperAdapter;
    private Adapter $calendarEventsJourneyModelAdapter;
    private Adapter $calendarEventsMemberModelAdapter;
    private Adapter $calendarEventsModelAdapter;
    private Adapter $configAdapter;
    private Adapter $controllerAdapter;
    private Adapter $dateAdapter;
    private Adapter $environmentAdapter;
    private Adapter $eventOrganizerModelAdapter;
    private Adapter $eventsAdapter;
    private Adapter $filesModelAdapter;
    private Adapter $inputAdapter;
    private Adapter $stringUtilAdapter;
    private Adapter $userModelAdapter;
    private Adapter $validatorAdapter;
    private Adapter $eventReleaseLevelPolicyModelAdapter;

    // Class properties that are initialized after class instantiation
    private ModuleModel|null $moduleModel = null;
    private CalendarEventsModel|null $eventModel = null;
    private MemberModel|null $memberModel = null;
    private UserModel|null $mainInstructorModel = null;
    private Form|null $objForm = null;
    private Template|null $template = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TwigEnvironment $twig,
        private readonly TranslatorInterface $translator,
        private readonly UrlParser $urlParser,
        private readonly string $projectDir,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        // Adapters
        $this->calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsJourneyModelAdapter = $this->framework->getAdapter(CalendarEventsJourneyModel::class);
        $this->calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->controllerAdapter = $this->framework->getAdapter(Controller::class);
        $this->dateAdapter = $this->framework->getAdapter(Date::class);
        $this->environmentAdapter = $this->framework->getAdapter(Environment::class);
        $this->eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);
        $this->eventsAdapter = $this->framework->getAdapter(Events::class);
        $this->filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $this->inputAdapter = $this->framework->getAdapter(Input::class);
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $this->userModelAdapter = $this->framework->getAdapter(UserModel::class);
        $this->validatorAdapter = $this->framework->getAdapter(Validator::class);
        $this->eventReleaseLevelPolicyModelAdapter = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Set the module object (Contao\ModuleModel)
        $this->moduleModel = $model;

        // Do not index nor cache page
        $page->noSearch = true;
        $page->cache = false;
        $page->clientCache = false;

        if (($objUser = $this->security->getUser()) instanceof FrontendUser) {
            $this->memberModel = MemberModel::findByPk($objUser->id);
        }

        // Set the item from the auto_item parameter
        if (!isset($_GET['events']) && $this->configAdapter->get('useAutoItem') && isset($_GET['auto_item'])) {
            $this->inputAdapter->setGet('events', $this->inputAdapter->get('auto_item'));
        }

        // Get $this->eventModel from GET
        $this->eventModel = $this->calendarEventsModelAdapter->findByIdOrAlias($this->inputAdapter->get('events'));

        // Get instructor object from UserModel
        $this->mainInstructorModel = $this->userModelAdapter->findByPk($this->eventModel->mainInstructor);

        if ('true' === $request->query->get('event_preview')) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();

        $flash = $session->getFlashBag();
        $sessInfKey = 'contao.FE.info';
        $sessErrKey = 'contao.FE.error';

        if (null === $this->eventModel) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventNotFound', [$this->inputAdapter->get('events') ?? 'NULL'], 'contao_default'));
        } elseif (!$this->eventModel->published) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventNotActivatedYet', [$this->eventModel->title], 'contao_default'));
        } elseif (null === $this->eventReleaseLevelPolicyModelAdapter->findOneByEventId($this->eventModel->id) || !$this->eventReleaseLevelPolicyModelAdapter->findOneByEventId($this->eventModel->id)->allowRegistration) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventReleaseLevelPolicyDoesNotAllowRegistrations', [$this->eventModel->title], 'contao_default'));
        } elseif ($this->eventModel->disableOnlineRegistration) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_onlineRegDisabled', [], 'contao_default'));
        } elseif (EventState::STATE_FULLY_BOOKED === $this->eventModel->eventState) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventFullyBooked', [], 'contao_default'));
        } elseif (EventState::STATE_CANCELED === $this->eventModel->eventState) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventCanceled', [], 'contao_default'));
        } elseif (EventState::STATE_DEFERRED === $this->eventModel->eventState) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventDeferred', [], 'contao_default'));
        } elseif ($this->eventModel->setRegistrationPeriod && $this->eventModel->registrationStartDate > time()) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_registrationPossibleOn', [$this->eventModel->title, $this->dateAdapter->parse('d.m.Y H:i', $this->eventModel->registrationStartDate)], 'contao_default'));
        } elseif ($this->eventModel->setRegistrationPeriod && $this->eventModel->registrationEndDate < time()) {
            $strDate = $this->dateAdapter->parse('d.m.Y', $this->eventModel->registrationEndDate);
            $strTime = $this->dateAdapter->parse('H:i', $this->eventModel->registrationEndDate);
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_registrationDeadlineExpired', [$strDate, $strTime], 'contao_default'));
        } elseif (!$this->eventModel->setRegistrationPeriod && $this->eventModel->startDate - 60 * 60 * 24 < time()) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_registrationPossible24HoursBeforeEventStart', [], 'contao_default'));
        } elseif ($this->memberModel && true === $this->calendarEventsHelperAdapter->areBookingDatesOccupied($this->eventModel, $this->memberModel)) {
            $flash->set($sessInfKey, $this->translator->trans('ERR.evt_reg_eventDateOverlapError', [], 'contao_default'));
        } elseif (null === $this->mainInstructorModel) {
            $flash->set($sessErrKey, $this->translator->trans('ERR.evt_reg_mainInstructorNotFound', [$this->eventModel->mainInstructor], 'contao_default'));
        } elseif (empty($this->mainInstructorModel->email) || !$this->validatorAdapter->isEmail($this->mainInstructorModel->email)) {
            $flash->set($sessErrKey, $this->translator->trans('ERR.evt_reg_mainInstructorsEmailAddrNotFound', [$this->eventModel->mainInstructor], 'contao_default'));
        } elseif (null !== $this->memberModel && (empty($this->memberModel->email) || !$this->validatorAdapter->isEmail($this->memberModel->email))) {
            $flash->set($sessErrKey, $this->translator->trans('ERR.evt_reg_membersEmailAddrNotFound', [], 'contao_default'));
        }

        $this->template = $template;
        $this->template->controller = $this;
        $this->template->eventModel = $this->eventModel;
        $this->template->memberModel = $this->memberModel;
        $this->template->moduleModel = $this->moduleModel;

        // Set more template vars
        $this->setMoreTemplateVars();

        if (null !== $this->memberModel && true === $this->calendarEventsMemberModelAdapter->isRegistered($this->memberModel->id, $this->eventModel->id)) {
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
                if ($this->contaoGeneralLogger) {
                    $strText = sprintf('Event registration error: "%s"', $errorMessage);
                    $this->contaoGeneralLogger->info($strText, ['contao' => new ContaoContext(__METHOD__, Log::EVENT_SUBSCRIPTION_ERROR)]);
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
            $this->buildForm();

            if (null !== $this->objForm) {
                //$this->template->form = $this->objForm->generate();
                $this->template->objForm = $this->objForm;
            }

            // Check if event is already fully booked
            if (true === $this->calendarEventsHelperAdapter->eventIsFullyBooked($this->eventModel)) {
                $this->template->bookingLimitReaches = true;
            }

            if ($url = $this->getRoute('register')) {
                return $this->redirect($url);
            }
        }

        $this->template->action = $request->query->get('action');
        $this->template->stepIndicator = $this->parseStepIndicatorTemplate($request->query->get('action'));

        return $this->template->getResponse();
    }

    private function getRoute(string $action): string|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->get('action') !== $action) {
            return $this->urlParser->addQueryString('action='.$action, $this->environmentAdapter->get('uri'));
        }

        return null;
    }

    private function buildForm(): void
    {
        $objForm = new Form(
            'form-event-registration',
            'POST',
        );

        $objForm->setAction($this->environmentAdapter->get('uri'));

        if (null !== ($objJourney = $this->calendarEventsJourneyModelAdapter->findByPk($this->eventModel->journey))) {
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
                $arrData['dateAdded'] = time();
                $arrData['uuid'] = Uuid::uuid4()->toString();
                $arrData['stateOfSubscription'] = $this->calendarEventsHelperAdapter->eventIsFullyBooked($this->eventModel) ? EventSubscriptionState::SUBSCRIPTION_ON_WAITINGLIST : EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED;
                $arrData['bookingType'] = 'onlineForm';
                $arrData['sectionId'] = $this->memberModel->sectionId;

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
                if ($this->contaoGeneralLogger) {
                    $strText = sprintf(
                        'New Registration from "%s %s [ID: %s]" for event with ID: %s ("%s").',
                        $this->memberModel->firstname,
                        $this->memberModel->lastname,
                        $this->memberModel->id,
                        $this->eventModel->id,
                        $this->eventModel->title
                    );

                    $this->contaoGeneralLogger->info($strText, ['contao' => new ContaoContext(__METHOD__, Log::EVENT_SUBSCRIPTION)]);
                }

                // Dispatch event registration event (e.g. send notification)
                $event = new \stdClass();
                $event->framework = $this->framework;
                $event->arrData = $arrData;
                $event->memberModel = $this->memberModel;
                $event->eventModel = $this->eventModel;
                $event->eventMemberModel = $objEventRegistration;
                $event->moduleModel = $this->moduleModel;

                // Use an event subscriber to notify member
                $this->eventDispatcher->dispatch(new EventRegistrationEvent($event), EventRegistrationEvent::NAME);

                // Reload page
                $this->controllerAdapter->reload();
            }
        }

        $this->objForm = $objForm;
    }

    private function setMoreTemplateVars(): void
    {
        if ('tour' === $this->eventModel->eventType || 'last-minute-tour' === $this->eventModel->eventType || 'course' === $this->eventModel->eventType) {
            $arrOrganizers = $this->stringUtilAdapter->deserialize($this->eventModel->organizers, true);

            if (isset($arrOrganizers[0])) {
                $objOrganizer = $this->eventOrganizerModelAdapter->findByPk($arrOrganizers[0]);

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
                            if ($this->validatorAdapter->isBinaryUuid($objOrganizer->{$prefix.'RegulationSRC'})) {
                                $objFile = $this->filesModelAdapter->findByUuid($objOrganizer->{$prefix.'RegulationSRC'});

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
                'eval' => ['mandatory' => false, 'maxlength' => 64, 'rgxp' => 'phone'],
            ],
            'emergencyPhone' => [
                'label' => $this->translator->trans('FORM.evt_reg_emergencyPhone', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'maxlength' => 64, 'rgxp' => 'phone'],
            ],
            'emergencyPhoneName' => [
                'label' => $this->translator->trans('FORM.evt_reg_emergencyPhoneName', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => true, 'maxlength' => 250],
            ],
            'notes' => [
                'label' => $this->translator->trans('FORM.evt_reg_notes', [], 'contao_default'),
                'inputType' => 'textarea',
                'eval' => ['mandatory' => true, 'maxlength' => 2000, 'rows' => 4],
                'class' => '',
            ],
            'foodHabits' => [
                'label' => $this->translator->trans('FORM.evt_reg_foodHabits', [], 'contao_default'),
                'inputType' => 'text',
                'eval' => ['mandatory' => false, 'maxlength' => 5000],
            ],
            'agb' => [
                'label' => ['', $this->translator->trans('FORM.evt_reg_agb', [], 'contao_default')],
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
        $durationInDays = \count($this->calendarEventsHelperAdapter->getEventTimestamps($this->eventModel));
        $startDate = $this->calendarEventsHelperAdapter->getStartDate($this->eventModel);
        $endDate = $this->calendarEventsHelperAdapter->getEndDate($this->eventModel);

        if ($durationInDays > 1 && $startDate + ($durationInDays - 1) * 86400 === $endDate) {
            return true;
        }

        return false;
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function parseEventRegistrationConfirmTemplate(): string
    {
        $this->controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        if (null !== ($objEventsMemberModel = $this->calendarEventsMemberModelAdapter->findByMemberAndEvent($this->memberModel, $this->eventModel))) {
            $arrEvent = $this->eventModel->row();
            $arrEventsMember = $objEventsMemberModel->row();
            $arrMember = $this->memberModel->row();

            $arrEventsMember['stateOfSubscriptionTrans'] = $this->translator->trans('MSC.'.$arrEventsMember['stateOfSubscription'], [], 'contao_default');
            $arrEvent['eventUrl'] = $this->eventsAdapter->generateEventUrl($this->eventModel);

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

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function parseStepIndicatorTemplate(string $strStep): string
    {
        return $this->twig->render(
            '@MarkocupicSacEventTool/EventRegistration/event_registration_step_indicator.html.twig',
            [
                'controller' => $this,
                'step' => $strStep,
            ]
        );
    }
}
