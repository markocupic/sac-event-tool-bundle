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
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\UserModel;
use Contao\Validator;
use Contao\Versions;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\Exception\EventDeregistrationException;
use Markocupic\SacEventToolBundle\Event\EventUnsubscribeEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Terminal42\NotificationCenterBundle\Receipt\ReceiptCollection;

#[AsFrontendModule(EventDeregistrationController::TYPE, category: 'sac_event_tool_frontend_modules')]
#[AsEventListener(event: EventUnsubscribeEvent::class, method: 'onEventDeregistration')]
class EventDeregistrationController extends AbstractFrontendModuleController
{
    public const string TYPE = 'event_deregistration';

    private FrontendUser|null $user = null;

    public function __construct(
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NotificationCenter $notificationCenter,
        private readonly RequestStack $requestStack,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        if ($this->scopeMatcher->isFrontendRequest($request)) {
            // Do not index nor cache page.
            if (null !== $page) {
                $page->noSearch = true;
                $page->cache = false;
                $page->clientCache = false;
            }

            $this->user = $this->getUserFromToken();

            if (!$this->user instanceof FrontendUser) {
                throw new \Exception('Not authorized. Please log in as frontend user.');
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    public function onEventDeregistration(EventUnsubscribeEvent $event): ReceiptCollection
    {
        // Load language file
        $this->getContaoAdapter(Controller::class)->loadLanguageFile('tl_calendar_events_member');
        $registration = $event->getRegistration();

        $calendarEvent = $event->getEvent();
        $instructor = $calendarEvent->getRelated('mainInstructor');

        $tokens = [
            'state_of_subscription' => $this->translator->trans('MSC.'.$registration->stateOfSubscription, [], 'contao_default'),
            'event_course_id' => $calendarEvent->courseId,
            'event_name' => $calendarEvent->title,
            'event_type' => $calendarEvent->eventType,
            'instructor_name' => $instructor->name,
            'instructor_email' => $instructor->email,
            'participant_name' => $registration->firstname.' '.$registration->lastname,
            'participant_email' => $registration->email,
            'event_link_detail' => $this->contentUrlGenerator->generate($calendarEvent, [], UrlGeneratorInterface::ABSOLUTE_URL),
            'sac_member_id' => !empty($registration->sacMemberId) ? $registration->sacMemberId : 'keine',
            'deregistration_cause' => $event->getData()['deregistration_cause'],
        ];

        $tokens = array_map('strval', $tokens);
        $tokens = array_map('html_entity_decode', $tokens);

        if (!empty($calendarEvent->registrationGoesTo)) {
            $backendUser = $this->getContaoAdapter(UserModel::class)->findById($calendarEvent->registrationGoesTo);

            if (!Validator::isEmail((string) $backendUser?->email)) {
                // This should not be the case, because we are testing already for a valid email
                // in self::canDeregister()
                throw new \RuntimeException('Instructor email address is not valid.');
            }

            $tokens['instructor_name'] = $backendUser->name;
            $tokens['instructor_email'] = $backendUser->email;
        }

        $this->contaoGeneralLogger?->info(
            \sprintf(
                'User with SAC-Member-ID %d has unsubscribed himself from event with ID: %d ("%s")',
                $registration->sacMemberId,
                $registration->eventId,
                $registration->eventName,
            ),
            ['contao' => new ContaoContext(__METHOD__, Log::EVENT_UNSUBSCRIPTION)],
        );

        $notificationId = $event->getFrontendModuleModel()?->eventDeregistrationNotification;

        if (empty($notificationId)) {
            throw new \RuntimeException('There is not notification set for the deregistration module.');
        }

        return $this->notificationCenter->sendNotification($notificationId, $tokens, $this->sacevtLocale);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        if (!$this->uriSigner->check($request->getRequestUri())) {
            $this->getContaoAdapter(Message::class)->addError($this->translator->trans('MSC.evt_dereg_invalidRequest', [], 'contao_default'));
            $this->addMessagesToTemplate($template, $request);

            return $template->getResponse();
        }

        $regId = (int) $request->query->get('regId');

        $registration = CalendarEventsMemberModel::findById($regId);
        $calendarEvent = CalendarEventsModel::findById($registration?->eventId);
        $user = MemberModel::findById($this->user->id);

        $template->set('user', $user->row());
        $template->set('registration', $registration?->row() ?? []); // If the registration has been deleted...
        $template->set('event', $calendarEvent?->row() ?? []); // If the registration has been deleted...
        $template->set('event_model', $calendarEvent); // If the registration has been deleted...

        $showForm = false;

        try {
            // Will throw an EventDeregistrationException if deregistration is not possible.
            if ($this->canDeregister($regId)) {
                $showForm = true;
            }
        } catch (EventDeregistrationException $e) {
            // Get the cause/message from the exception
            $message = $this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default');
            $this->getContaoAdapter(Message::class)->add($message, EventDeregistrationException::TYPE_MAP[$e->getType()]);
        }

        if ($showForm) {
            $form = $this->getForm();

            if ($form->validate()) {
                if (true === $this->deregister((int) $request->query->get('regId'), $form->fetchAll(), $model)) {
                    // Reload page only if the deregistration was successful
                    $this->getContaoAdapter(Controller::class)->reload();
                }
            }

            $template->set('form', $form->generate());
            $template->set('event_booking_conditions', $calendarEvent->bookingDetails);
        }

        $this->addMessagesToTemplate($template, $request);

        return $template->getResponse();
    }

    private function getUserFromToken(): FrontendUser|null
    {
        $user = $this->tokenStorage
            ->getToken()
            ?->getUser()
        ;

        if ($user instanceof FrontendUser) {
            return $user;
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    private function getForm(): Form
    {
        $form = new Form(
            'event_deregistration_form',
            'POST',
        );

        $form->addContaoHiddenFields();

        $form->addFormField('deregistration_cause', [
            'label' => $this->translator->trans('LBL.evt_dereg_registrationCause', [], 'contao_default'),
            'inputType' => 'textarea',
            'eval' => ['rows' => 10, 'cols' => 80, 'mandatory' => true],
        ]);

        $form->addSubmitFormField($this->translator->trans('LBL.evt_dereg_submitBtn', [], 'contao_default'));

        if ($form->isSubmitted()) {
            $form->getWidget('deregistration_cause')->value = $this->requestStack->getCurrentRequest()->request->get('deregistration_cause', '');
        }

        return $form;
    }

    /**
     * @throws \Exception
     */
    private function canDeregister(int $regId): bool
    {
        $registration = $this->getContaoAdapter(CalendarEventsMemberModel::class)->findById($regId);

        // If the registration for an event has just been canceled by the participant, a
        // token is inserted into the session containing information about the event and
        // the registration.
        $deregSuccessToken = $this->requestStack->getCurrentRequest()->getSession()->get('evt_dereg_success_'.$regId);

        if (isset($deregSuccessToken['regId']) && $deregSuccessToken['regId'] === $regId && $deregSuccessToken['expiration'] > time()) {
            throw new EventDeregistrationException('User just has successfully unsubscribed from event.', 'info', 'MSC.evt_dereg_success', [$this->user->firstname, $deregSuccessToken['event']['title']]);
        }

        $calendarEvent = $registration?->getRelated('eventId');

        if (null === $registration) {
            throw new EventDeregistrationException(\sprintf('Could not find a registration with ID %d.', $regId), 'error', 'MSC.evt_dereg_regNotFound', [$this->user->firstname, $regId]);
        }

        if (null === $calendarEvent) {
            throw new EventDeregistrationException('Could not find a related event to the registration.', 'error', 'MSC.evt_dereg_eventNotFound', [$this->user->firstname, $regId]);
        }

        if ((int) $registration->sacMemberId !== (int) $this->user->sacMemberId) {
            throw new EventDeregistrationException('Access not allowed. User is not the registration owner.', 'error', 'MSC.evt_dereg_accessNotAllowed', [$this->user->firstname, $calendarEvent->title]);
        }

        if (empty($this->user->email) || !Validator::isEmail($this->user->email)) {
            throw new EventDeregistrationException('User has no valid email address.', 'error', 'MSC.evt_dereg_userHasInvalidEmail', [$this->user->firstname, $calendarEvent->title]);
        }

        if (null === $calendarEvent->getRelated('mainInstructor') || !Validator::isEmail(UserModel::findById($calendarEvent->getRelated('mainInstructor')->id)->email)) {
            throw new EventDeregistrationException('Main instructor not found or the main instructor has no valid email address.', 'error', 'MSC.evt_dereg_mainInstructorNotFoundOrNotAvailableByEmail', [$this->user->firstname, $calendarEvent->title]);
        }

        if (!empty($calendarEvent->registrationGoesTo)) {
            $backendUser = $this->getContaoAdapter(UserModel::class)->findById($calendarEvent->registrationGoesTo);

            if (null === $backendUser || !Validator::isEmail($backendUser->email)) {
                throw new EventDeregistrationException('Main instructor not found or the main instructor has no valid email address.', 'error', 'MSC.evt_dereg_mainInstructorNotFoundOrNotAvailableByEmail', [$this->user->firstname, $calendarEvent->title]);
            }
        }

        if (EventSubscriptionState::SUBSCRIPTION_REFUSED === $registration->stateOfSubscription) {
            return true;
        }

        if (EventSubscriptionState::USER_HAS_UNSUBSCRIBED === $registration->stateOfSubscription) {
            throw new EventDeregistrationException('User has already unsubscribed.', 'info', 'MSC.evt_dereg_alreadyDeregistered', [$this->user->firstname, $calendarEvent->title]);
        }

        if (EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED === $registration->stateOfSubscription) {
            // Allow deregistration if the member is not confirmed on the event
            return true;
        }

        if (EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST === $registration->stateOfSubscription) {
            // Allow deregistration if the member is on the waiting list for this event
            return true;
        }

        if (!$calendarEvent->allowDeregistration) {
            throw new EventDeregistrationException('Online deregistration not allowed in the event settings.', 'error', 'MSC.evt_dereg_onlineDeregNotPossible', [$this->user->firstname, $calendarEvent->title]);
        }

        if ($calendarEvent->startDate <= strtotime('today midnight')) {
            throw new EventDeregistrationException('Deregistration is no more possible. Event is already over.', 'error', 'MSC.evt_dereg_deregNotPossibleEventAlreadyOver', [$this->user->firstname, $calendarEvent->title]);
        }

        if ($calendarEvent->allowDeregistration && ($calendarEvent->startDate < (strtotime('today midnight') + $calendarEvent->deregistrationLimit * 24 * 3600))) {
            $dateLimit = $this->getContaoAdapter(Date::class)->parse('d.m.Y', $calendarEvent->startDate - $calendarEvent->deregistrationLimit * 24 * 3600);

            throw new EventDeregistrationException('Deregistration is no more possible. Deadline expired.', 'error', 'MSC.evt_dereg_deregNotPossibleDeadlineExpired', [$this->user->firstname, $calendarEvent->title, $dateLimit]);
        }

        return true;
    }

    private function deregister(int $regId, array $dataSubmit, ModuleModel $moduleModel): bool
    {
        $registration = $this->getContaoAdapter(CalendarEventsMemberModel::class)->findById($regId);

        $shouldDelete = false;

        // If the state of subscription is set "refused" the record will be deleted @todo
        // should we extend this behaviour to other subscription states?
        if (EventSubscriptionState::SUBSCRIPTION_REFUSED === $registration->stateOfSubscription) {
            $shouldDelete = true;
        }

        $calendarEvent = $registration->getRelated('eventId');

        // Unregister from the event
        $registration->stateOfSubscription = EventSubscriptionState::USER_HAS_UNSUBSCRIBED;

        $memberModel = $this->getContaoAdapter(MemberModel::class)->findById($this->user->id);

        $registration->deregistrationCause = Input::post('deregistration_cause', null); // Input encoding!

        try {
            // Deregistration can be canceled via event listener. In the event listener you
            // have to throw an EventDeregistrationException.
            $event = new EventUnsubscribeEvent($this->requestStack->getCurrentRequest(), $registration, $calendarEvent, $memberModel, $moduleModel, $shouldDelete, $dataSubmit);
            $this->eventDispatcher->dispatch($event);
        } catch (EventDeregistrationException $e) {
            // Get the message from the exception
            $message = $this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default');
            $this->getContaoAdapter(Message::class)->add($message, EventDeregistrationException::TYPE_MAP[$e->getType()]);

            return false;
        }

        if ($registration->isModified()) {
            $registration->tstamp = time();
            $registration->save();

            // Create a new version
            $versions = new Versions($registration->getTable(), $registration->id);
            $versions->initialize();
            $versions->create();
        }

        $this->contaoGeneralLogger?->info(
            \sprintf(
                'User with SAC-User-ID %d has unsubscribed himself from event with ID: %d ("%s").%s',
                $registration->sacMemberId,
                $registration->eventId,
                $registration->eventName,
                $event->shouldDelete() ? ' The data record was deleted.' : '',
            ),
            ['contao' => new ContaoContext(__METHOD__, Log::EVENT_UNSUBSCRIPTION)],
        );

        if ($event->shouldDelete()) {
            $registration->delete();
        }

        $tokens = [
            'regId' => $regId,
            'shouldDelete' => $event->shouldDelete(),
            'event' => $calendarEvent->row(),
            'expiration' => time() + 180,
        ];

        $this->requestStack->getCurrentRequest()->getSession()->set('evt_dereg_success_'.$regId, $tokens);

        return true;
    }

    /**
     * Add messages from session flash to the template.
     */
    private function addMessagesToTemplate(FragmentTemplate $template, Request $request): void
    {
        $messageAdapter = $this->getContaoAdapter(Message::class);

        $template->set('hasInfoMessage', false);
        $template->set('hasErrorMessage', false);

        if ($messageAdapter->hasInfo()) {
            $session = $request->getSession()->getFlashBag()->get('contao.FE.info');
            $template->set('hasInfoMessage', true);
            $template->set('infoMessage', $session[0]);
        }

        if ($messageAdapter->hasError()) {
            $session = $request->getSession()->getFlashBag()->get('contao.FE.error');
            $template->set('hasErrorMessage', true);
            $template->set('errorMessage', $session[0]);
            $template->set('errorMessages', $session);
        }

        $messageAdapter->reset();
    }
}
