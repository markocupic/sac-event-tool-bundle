<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Codefog\HasteBundle\Form\Form;
use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Contao\UserModel;
use Contao\Validator;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Markocupic\SacEventToolBundle\Config\CarSeatInfo;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Config\TicketInfo;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\Exception\EventRegistrationException;
use Markocupic\SacEventToolBundle\Event\EventRegistrationEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

#[AsFrontendModule(EventRegistrationController::TYPE, category: 'sac_event_tool_frontend_modules', template: 'mod_event_registration')]
class EventRegistrationController extends AbstractFrontendModuleController
{
    public const string TYPE = 'event_registration';
    public const string CHECKOUT_STEP_LOGIN = 'login';
    public const string CHECKOUT_STEP_REGISTER = 'register';
    public const string CHECKOUT_STEP_CONFIRM = 'confirm';
    public const string CHECKOUT_STEP_REGISTRATION_INTERRUPTED = 'registration_interrupted';

    // Class properties that are initialized after class instantiation
    private CalendarEventsModel|null $eventModel = null;
    private MemberModel|null $memberModel = null;
    private ModuleModel|null $moduleModel = null;
    private UserModel|null $mainInstructorModel = null;
    private bool $validationFailed = false;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly CarSeatInfo $carSeatInfo,
        private readonly ContaoFramework $framework,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Security $security,
        private readonly TicketInfo $ticketInfo,
        private readonly TranslatorInterface $translator,
        private readonly TwigEnvironment $twig,
        #[Autowire('%sacevt.event_registration.config.reg_start_time_offset%')]
        private readonly int $regStartTimeOffset,
        private readonly UrlParser $urlParser,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        if ($this->scopeMatcher->isFrontendRequest($request)) {
            // Set the module object (Contao\ModuleModel).
            $this->moduleModel = $model;

            // Do not index nor cache page.
            if (null !== $page) {
                $page->noSearch = true;
                $page->cache = false;
                $page->clientCache = false;
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Do not show the registration module in the event preview mode.
        if ('true' === $request->query->get('event_preview')) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if (($objUser = $this->security->getUser()) instanceof FrontendUser) {
            $this->setMemberModel(MemberModel::findByPk($objUser->id));
        }

        $eventIdOrAlias = (string) $this->framework->getAdapter(Input::class)->get('auto_item');

        // Get the event model from url query.
        try {
            $this->setEventModel($this->framework->getAdapter(CalendarEventsModel::class)->findByIdOrAlias($eventIdOrAlias));
        } catch (\Throwable $e) {
            throw new PageNotFoundException('No valid event id/alias could be found in the url parameters.');
        }

        // Get the main instructor object.
        $this->setMainInstructorModel($this->framework->getAdapter(UserModel::class)->findByPk($this->eventModel->mainInstructor));

        $messageAdapter = $this->framework->getAdapter(Message::class);

        // Do numerous checks to be sure that the event is bookable.
        // If validation fails write an info/error message to the session flash bag.
        try {
            $options = [
                'regStartTimeOffset' => $this->regStartTimeOffset,
            ];

            $this->validateRegistrationRequest($this->eventModel, $this->memberModel, $this->mainInstructorModel, $options);
        } catch (\Exception $e) {
            match (true) {
                $e instanceof EventRegistrationException && EventRegistrationException::LEVEL_INFO === $e->getErrorLevel() => $messageAdapter->addInfo($this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default')),
                $e instanceof EventRegistrationException && EventRegistrationException::LEVEL_ERROR === $e->getErrorLevel() => $messageAdapter->addError($this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default')),
                default => $messageAdapter->addError($this->translator->trans('ERR.evt_reg_unknownError', [], 'contao_default')),
            };

            if (($e instanceof EventRegistrationException && EventRegistrationException::LEVEL_ERROR === $e->getErrorLevel()) || (!$e instanceof EventRegistrationException)) {
                $this->contaoErrorLogger?->error($e->getMessage(), ['contao' => new ContaoContext(__METHOD__, Log::EVENT_SUBSCRIPTION_ERROR)]);
            }

            $this->validationFailed = true;
        }

        if (null !== $this->memberModel && $this->framework->getAdapter(CalendarEventsMemberModel::class)->isRegistered($this->memberModel->id, $this->eventModel->id)) {
            if ($url = $this->getRoute(self::CHECKOUT_STEP_CONFIRM)) {
                return $this->redirect($url);
            }
        } elseif ($this->validationFailed) {
            if ($url = $this->getRoute(self::CHECKOUT_STEP_REGISTRATION_INTERRUPTED)) {
                return $this->redirect($url);
            }
        } elseif (null === $this->memberModel) {
            if ($url = $this->getRoute(self::CHECKOUT_STEP_LOGIN)) {
                return $this->redirect($url);
            }
        } else {
            if ($url = $this->getRoute(self::CHECKOUT_STEP_REGISTER)) {
                return $this->redirect($url);
            }
        }

        $currentStep = $request->query->get('action');

        switch ($currentStep) {
            case self::CHECKOUT_STEP_LOGIN:
                break;
            case self::CHECKOUT_STEP_REGISTER:
                // All ok! Booking request has passed all checks. So let's generate the registration form now.
                $template->set('form', $this->generateForm($request));

                // Check if event is already fully booked.
                if ($this->calendarEventsUtil->eventIsFullyBooked($this->eventModel)) {
                    $template->set('eventFullyBooked', true);
                }

                break;
            case self::CHECKOUT_STEP_CONFIRM:
                $template->set('regInfo', $this->renderEventRegistrationConfirmTemplate());
                break;
            case self::CHECKOUT_STEP_REGISTRATION_INTERRUPTED:
                if ($messageAdapter->hasError()) {
                    $errorMessage = $this->getFirstErrorMessage($request);
                    $template->set('errorMessage', $errorMessage);
                }

                if ($messageAdapter->hasInfo()) {
                    $infoMessage = $this->getFirstInfoMessage($request);
                    $template->set('infoMessage', $infoMessage);
                }

                break;

            default:
                throw new \LogicException('This place in the code should not be reachable.');
        }

        // Add more data to the template.
        $template = $this->addTemplateVars($template);
        $template->set('currentStep', $currentStep);
        $template->set('stepIndicator', $this->renderStepIndicatorTemplate($request->query->get('action')));

        return $template->getResponse();
    }

    private function setEventModel(CalendarEventsModel $eventModel): void
    {
        $this->eventModel = $eventModel;
    }

    private function setMemberModel(MemberModel|null $memberModel): void
    {
        $this->memberModel = $memberModel;
    }

    private function setMainInstructorModel(UserModel|null $mainInstructorModel): void
    {
        $this->mainInstructorModel = $mainInstructorModel;
    }

    private function validateRegistrationRequest(CalendarEventsModel $eventModel, MemberModel|null $memberModel = null, UserModel|null $mainInstructorModel = null, $options = []): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'regStartTimeOffset' => 0,
        ]);
        $resolver->setAllowedTypes('regStartTimeOffset', 'int');
        $options = $resolver->resolve($options);

        if (!$eventModel->published) {
            throw new EventRegistrationException('You can not subscribe to the current event because it is not published.', EventRegistrationException::LEVEL_ERROR, 'ERR.evt_reg_eventNotPublishedYet', [$eventModel->title]);
        }

        if (null === ($adapter = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class)->findOneByEventId($eventModel->id)) || !$adapter->findOneByEventId($eventModel->id)->allowRegistration) {
            throw new EventRegistrationException('The event release level policy does not allow you to register for this event.', EventRegistrationException::LEVEL_ERROR, 'ERR.evt_reg_eventReleaseLevelPolicyDoesNotAllowRegistrations', [$eventModel->title]);
        }

        if ($eventModel->disableOnlineRegistration) {
            throw new EventRegistrationException('Online registration has been disabled for this event.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_onlineRegDisabled', []);
        }

        if (EventState::STATE_FULLY_BOOKED === $eventModel->eventState) {
            throw new EventRegistrationException('The event you are trying to register for is already fully booked.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventFullyBooked', []);
        }

        if (EventState::STATE_CANCELED === $eventModel->eventState) {
            throw new EventRegistrationException('The event you are trying to register for has been canceled.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventCanceled', []);
        }

        if (EventState::STATE_RESCHEDULED === $eventModel->eventState) {
            throw new EventRegistrationException('The event you are trying to register for has been deferred.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventDeferred', []);
        }

        if ($eventModel->setRegistrationPeriod && $eventModel->registrationStartDate + $options['regStartTimeOffset'] > strtotime('now')) {
            throw new EventRegistrationException('Subscribing for the event is not possible yet.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_registrationPossibleOn', [$eventModel->title, date('d.m.Y H:i', (int) $eventModel->registrationStartDate + $options['regStartTimeOffset'])]);
        }

        if ($eventModel->setRegistrationPeriod && $eventModel->registrationEndDate < strtotime('now')) {
            $strEndDate = date('d.m.Y', (int) $eventModel->registrationEndDate);
            $strEndTime = date('H:i', (int) $eventModel->registrationEndDate);

            throw new EventRegistrationException('The registration deadline for this event has expired.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_registrationDeadlineExpired', [$strEndDate, $strEndTime]);
        }

        if (!$eventModel->setRegistrationPeriod && strtotime('+1 day') > $eventModel->startDate) {
            throw new EventRegistrationException('If no registration time has been set, online registration is only possible up to 24 h before the event start date.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_registrationPossible24HoursBeforeEventStart', []);
        }

        if ($memberModel && true === $this->calendarEventsUtil->areBookingDatesOccupied($eventModel, $memberModel)) {
            throw new EventRegistrationException('You can not subscribe because you are already registered for another event at the same time.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventDateOverlapError', []);
        }

        if (null === $mainInstructorModel) {
            throw new EventRegistrationException('You can not register for this event because there is no main instructor assigned to the event.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_mainInstructorNotFound', [$eventModel->mainInstructor]);
        }

        if (empty($mainInstructorModel->email) || !Validator::isEmail($mainInstructorModel->email)) {
            throw new EventRegistrationException('You can not register for the event because the main instructor has an invalid email address.', EventRegistrationException::LEVEL_ERROR, 'ERR.evt_reg_mainInstructorsEmailAddrNotFound', [$eventModel->mainInstructor]);
        }

        if (null !== $memberModel && !Validator::isEmail($memberModel->email)) {
            throw new EventRegistrationException('You can not subscribe to this event because of an invalid or not existent email address.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_membersEmailAddrNotFound', []);
        }
    }

    private function getRoute(string $action): string|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->get('action') !== $action) {
            return $this->urlParser->addQueryString('action='.$action, $request->getUri());
        }

        return null;
    }

    private function generateForm(Request $request): Form
    {
        $objForm = new Form(
            'form-event-registration',
            'POST',
        );

        $objForm->setAction($request->getUri());

        if (null !== ($objJourney = $this->framework->getAdapter(CalendarEventsJourneyModel::class)->findByPk($this->eventModel->journey))) {
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

        // Do only ask for food habits if we have a multi day event.
        if ($this->isMultiDayEvent()) {
            $objForm->addFormField('foodHabits', $this->getFormFieldDca('foodHabits'));
        }

        $objForm->addFormField('agb', $this->getFormFieldDca('agb'));
        $objForm->addFormField('hasAcceptedPrivacyRules', $this->getFormFieldDca('hasAcceptedPrivacyRules'));

        $objForm->addFormField('submit', $this->getFormFieldDca('submit'));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        // Get form presets from tl_member.
        $arrFields = ['phone', 'mobile', 'emergencyPhone', 'emergencyPhoneName', 'foodHabits', 'ahvNumber'];

        foreach ($arrFields as $field) {
            if ($objForm->hasFormField($field)) {
                $objWidget = $objForm->getWidget($field);

                if (empty($objWidget->value)) {
                    $objWidget = $objForm->getWidget($field);
                    $objWidget->value = $this->memberModel->{$field};
                }
            }
        }

        // validate() also checks whether the form has been submitted.
        if ($objForm->validate()) {
            if (null !== $this->memberModel) {
                // Save data to tl_calendar_events_member
                $arrDataForm = $objForm->fetchAll();

                $registrationModel = $this->createNewEventRegistration($this->memberModel, $arrDataForm);

                // Contao system log
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

                $event = new EventRegistrationEvent(
                    $request,
                    $registrationModel,
                    $this->eventModel,
                    $this->memberModel,
                    $this->moduleModel,
                    $registrationModel->row(),
                );

                // Dispatch event registration event (e.g. notify user upon event registration).
                $this->eventDispatcher->dispatch($event);

                // Reload page.
                $this->framework->getAdapter(Controller::class)->reload();
            }
        }

        return $objForm;
    }

    private function createNewEventRegistration(MemberModel $memberModel, array $arrFormData): CalendarEventsMemberModel
    {
        $arrData = array_merge($this->memberModel->row(), $arrFormData);

        // Do not send ahv number if it is not required.
        if (!isset($arrFormData['ahvNumber'])) {
            unset($arrData['ahvNumber']);
        }

        $arrData['contaoMemberId'] = $memberModel->id;
        $arrData['eventName'] = $this->eventModel->title;
        $arrData['eventId'] = $this->eventModel->id;
        $arrData['dateAdded'] = strtotime('now');
        $arrData['tstamp'] = strtotime('now');
        $arrData['uuid'] = Uuid::uuid4()->toString();
        $arrData['stateOfSubscription'] = $this->calendarEventsUtil->eventIsFullyBooked($this->eventModel) ? EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST : EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED;
        $arrData['bookingType'] = BookingType::ONLINE_FORM;
        $arrData['sectionId'] = $memberModel->sectionId;

        // Save emergency phone number to users profile.
        if (empty($memberModel->emergencyPhone)) {
            $memberModel->emergencyPhone = $arrData['emergencyPhone'];
            $memberModel->save();
        }

        // Save emergency phone name to users profile.
        if (empty($memberModel->emergencyPhoneName)) {
            $memberModel->emergencyPhoneName = $arrData['emergencyPhoneName'];
            $memberModel->save();
        }

        // Save AHV number to users profile.
        if (!empty($arrData['ahvNumber'])) {
            $memberModel->ahvNumber = $arrData['ahvNumber'];
            $memberModel->save();
        }

        unset($arrData['id']);

        $registrationModel = new CalendarEventsMemberModel();
        $registrationModel->setRow($arrData);
        $registrationModel->save();

        return $registrationModel;
    }

    private function addTemplateVars(FragmentTemplate $template): Template
    {
        $template->set('controller', $this);
        $template->set('eventModel', $this->eventModel);
        $template->set('memberModel', $this->memberModel);
        $template->set('moduleModel', $this->moduleModel);

        return $template;
    }

    private function getFormFieldDca(string $field): array
    {
        $formFields = [
            'ticketInfo' => [
                'label' => $this->translator->trans('FORM.evt_reg_ticketInfo', [], 'contao_default'),
                'inputType' => 'select',
                'options' => $this->ticketInfo->getAll(),
                'eval' => ['includeBlankOption' => true, 'blankOptionLabel' => $this->translator->trans('FORM.evt_reg_blankLabelTicketInfo', [], 'contao_default'), 'mandatory' => true],
            ],
            'carInfo' => [
                'label' => $this->translator->trans('FORM.evt_reg_carInfo', [], 'contao_default'),
                'inputType' => 'select',
                'options' => $this->carSeatInfo->getAll(),
                'eval' => ['includeBlankOption' => true, 'blankOptionLabel' => $this->translator->trans('FORM.evt_reg_blankLabelCarInfo', [], 'contao_default'), 'mandatory' => true],
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
            'hasAcceptedPrivacyRules' => [
                'label' => ['', $this->translator->trans('FORM.evt_reg_hasAcceptedPrivacyRules', [], 'contao_default')],
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
        $durationInDays = \count($this->calendarEventsUtil->getEventTimestamps($this->eventModel));
        $startDate = $this->calendarEventsUtil->getStartTstamp($this->eventModel);
        $endDate = $this->calendarEventsUtil->getEndTstamp($this->eventModel);

        if ($durationInDays > 1 && $startDate + ($durationInDays - 1) * 86400 === $endDate) {
            return true;
        }

        return false;
    }

    private function renderEventRegistrationConfirmTemplate(): string
    {
        $this->framework->getAdapter(System::class)->loadLanguageFile('tl_calendar_events_member');

        if (null !== ($objEventsMemberModel = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findByMemberAndEvent($this->memberModel, $this->eventModel))) {
            $arrEvent = $this->eventModel->row();
            $arrEventsMember = $objEventsMemberModel->row();
            $arrMember = $this->memberModel->row();

            $arrEventsMember['stateOfSubscriptionTrans'] = $this->translator->trans('MSC.'.$arrEventsMember['stateOfSubscription'], [], 'contao_default');
            $arrEvent['eventUrl'] = $this->contentUrlGenerator->generate($this->eventModel);

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

    private function renderStepIndicatorTemplate(string $strStep): string
    {
        return $this->twig->render(
            '@MarkocupicSacEventTool/EventRegistration/event_registration_step_indicator.html.twig',
            [
                'controller' => $this,
                'current_step' => $strStep,
            ]
        );
    }

    private function getFirstErrorMessage(Request $request): string|null
    {
        $session = $request->getSession();
        $flash = $session->getFlashBag();

        return $flash->get('contao.FE.error')[0] ?? null;
    }

    private function getFirstInfoMessage(Request $request): string|null
    {
        $session = $request->getSession();
        $flash = $session->getFlashBag();

        return $flash->get('contao.FE.info')[0] ?? null;
    }
}
