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
use Contao\CoreBundle\Framework\ContaoFramework;
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
use Markocupic\SacEventToolBundle\Event\EventDeregistrationEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Terminal42\NotificationCenterBundle\Receipt\ReceiptCollection;

#[AsFrontendModule(EventDeregistrationController::TYPE, category: 'sac_event_tool_frontend_modules', template: 'mod_event_deregistration')]
#[AsEventListener(event: EventDeregistrationEvent::class, method: 'onEventDeregistration')]
class EventDeregistrationController extends AbstractFrontendModuleController
{
    public const string TYPE = 'event_deregistration';

    private ModuleModel|null $moduleModel = null;

    private FrontendUser|null $user = null;

    private FragmentTemplate|null $template = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NotificationCenter $notificationCenter,
        private readonly RequestStack $requestStack,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        $this->moduleModel = $model;

        if ($this->scopeMatcher->isFrontendRequest($request)) {
            // Do not index nor cache page.
            if (null !== $page) {
                $page->noSearch = true;
                $page->cache = false;
                $page->clientCache = false;
            }

            if (!$this->security->getUser() instanceof FrontendUser) {
                throw new \Exception('Not authorized. Please log in as frontend user.');
            }

            $this->user = $this->security->getUser();
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    public function onEventDeregistration(EventDeregistrationEvent $event): ReceiptCollection
    {
        // Load language file
        $this->framework->getAdapter(Controller::class)->loadLanguageFile('tl_calendar_events_member');
        $objRegistration = $event->getRegistration();

        $objEvent = $event->getEvent();
        $objInstructor = $objEvent->getRelated('mainInstructor');

        $arrTokens = [
            'state_of_subscription' => $this->translator->trans('MSC.'.$objRegistration->stateOfSubscription, [], 'contao_default'),
            'event_course_id' => $objEvent->courseId,
            'event_name' => $objEvent->title,
            'event_type' => $objEvent->eventType,
            'instructor_name' => $objInstructor->name,
            'instructor_email' => $objInstructor->email,
            'participant_name' => $objRegistration->firstname.' '.$objRegistration->lastname,
            'participant_email' => $objRegistration->email,
            'event_link_detail' => $this->contentUrlGenerator->generate($objEvent, [], UrlGeneratorInterface::ABSOLUTE_URL),
            'sac_member_id' => !empty($objRegistration->sacMemberId) ? $objRegistration->sacMemberId : 'keine',
            'deregistration_cause' => $event->getData()['deregistration_cause'],
        ];

        $arrTokens = array_map('strval', $arrTokens);
        $arrTokens = array_map('html_entity_decode', $arrTokens);

        if (!empty($objEvent->registrationGoesTo)) {
            $objUser = $this->framework->getAdapter(UserModel::class)->findById($objEvent->registrationGoesTo);

            if (!Validator::isEmail((string) $objUser?->email)) {
                // This should not be the case, because we are testing already for a valid email
                // in self::canDeregister()
                throw new \RuntimeException('Instructor email address is not valid.');
            }

            $arrTokens['instructor_name'] = $objUser->name;
            $arrTokens['instructor_email'] = $objUser->email;
        }

        $this->contaoGeneralLogger?->info(
            \sprintf(
                'User with SAC-Member-ID %d has unsubscribed himself from event with ID: %d ("%s")',
                $objRegistration->sacMemberId,
                $objRegistration->eventId,
                $objRegistration->eventName,
            ),
            ['contao' => new ContaoContext(__METHOD__, Log::EVENT_UNSUBSCRIPTION)],
        );

        $notificationId = $event->getFrontendModuleModel()?->eventDeregistrationNotification;

        if (empty($notificationId)) {
            throw new \RuntimeException('There is not notification set for the deregistration module.');
        }

        return $this->notificationCenter->sendNotification($notificationId, $arrTokens, $this->sacevtLocale);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $this->template = $template;

        if (!$this->uriSigner->check($request->getRequestUri())) {
            $this->framework->getAdapter(Message::class)->addError($this->translator->trans('MSC.evt_dereg_invalidRequest', [], 'contao_default'));
            $this->addMessagesToTemplate($request);

            return $template->getResponse();
        }

        $regId = (int) $request->query->get('regId');

        $registration = CalendarEventsMemberModel::findById($regId);
        $event = CalendarEventsModel::findById($registration?->eventId);
        $user = MemberModel::findById($this->user->id);

        $this->template = $template;
        $this->template->set('user', $user->row());
        $this->template->set('registration', $registration?->row() ?? []); // If the registration has been deleted...
        $this->template->set('event', $event?->row() ?? []); // If the registration has been deleted...
        $this->template->set('event_model', $event); // If the registration has been deleted...

        $showForm = false;

        try {
            // Will throw an EventDeregistrationException if deregistration is not possible.
            if ($this->canDeregister($regId)) {
                $showForm = true;
            }
        } catch (EventDeregistrationException $e) {
            // Get the cause/message from the exception
            $message = $this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default');
            $this->framework->getAdapter(Message::class)->add($message, EventDeregistrationException::TYPE_MAP[$e->getType()]);
        }

        if ($showForm) {
            $form = $this->getForm();

            if ($form->validate()) {
                if (true === $this->deregister((int) $request->query->get('regId'), $form->fetchAll())) {
                    // Reload page only if the deregistration was successful
                    $this->framework->getAdapter(Controller::class)->reload();
                }
            }

            $template->set('form', $form->generate());
            $template->set('event_booking_conditions', $event->bookingEvent);
        }

        $this->addMessagesToTemplate($request);

        return $template->getResponse();
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
        $objRegistration = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findById($regId);

        // If the registration for an event has just been canceled by the participant, a
        // token is inserted into the session containing information about the event and
        // the registration.
        $deregSuccessToken = $this->requestStack->getCurrentRequest()->getSession()->get('evt_dereg_success_'.$regId);

        if (isset($deregSuccessToken['regId']) && $deregSuccessToken['regId'] === $regId && $deregSuccessToken['expiration'] > time()) {
            throw new EventDeregistrationException('User just has successfully unsubscribed from event.', 'info', 'MSC.evt_dereg_success', [$this->user->firstname, $deregSuccessToken['event']['title']]);
        }

        $objEvent = $objRegistration?->getRelated('eventId');

        if (null === $objRegistration) {
            throw new EventDeregistrationException(\sprintf('Could not find a registration with ID %d.', $regId), 'error', 'MSC.evt_dereg_regNotFound', [$this->user->firstname, $regId]);
        }

        if (null === $objEvent) {
            throw new EventDeregistrationException('Could not find a related event to the registration.', 'error', 'MSC.evt_dereg_eventNotFound', [$this->user->firstname, $regId]);
        }

        if ((int) $objRegistration->sacMemberId !== (int) $this->user->sacMemberId) {
            throw new EventDeregistrationException('Access not allowed. User is not the registration owner.', 'error', 'MSC.evt_dereg_accessNotAllowed', [$this->user->firstname, $objEvent->title]);
        }

        if (empty($this->user->email) || !Validator::isEmail($this->user->email)) {
            throw new EventDeregistrationException('User has no valid email address.', 'error', 'MSC.evt_dereg_userHasInvalidEmail', [$this->user->firstname, $objEvent->title]);
        }

        if (null === $objEvent->getRelated('mainInstructor') || !Validator::isEmail(UserModel::findById($objEvent->getRelated('mainInstructor')->id)->email)) {
            throw new EventDeregistrationException('Main instructor not found or the main instructor has no valid email address.', 'error', 'MSC.evt_dereg_mainInstructorNotFoundOrNotAvailableByEmail', [$this->user->firstname, $objEvent->title]);
        }

        if (!empty($objEvent->registrationGoesTo)) {
            $objUser = $this->framework->getAdapter(UserModel::class)->findById($objEvent->registrationGoesTo);

            if (null === $objUser || !Validator::isEmail($objUser->email)) {
                throw new EventDeregistrationException('Main instructor not found or the main instructor has no valid email address.', 'error', 'MSC.evt_dereg_mainInstructorNotFoundOrNotAvailableByEmail', [$this->user->firstname, $objEvent->title]);
            }
        }

        if (EventSubscriptionState::SUBSCRIPTION_REFUSED === $objRegistration->stateOfSubscription) {
            return true;
        }

        if (EventSubscriptionState::USER_HAS_UNSUBSCRIBED === $objRegistration->stateOfSubscription) {
            throw new EventDeregistrationException('User has already unsubscribed.', 'info', 'MSC.evt_dereg_alreadyDeregistered', [$this->user->firstname, $objEvent->title]);
        }

        if (EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED === $objRegistration->stateOfSubscription) {
            // Allow deregistration if member is not confirmed on the event
            return true;
        }

        if (EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST === $objRegistration->stateOfSubscription) {
            // Allow deregistration if member is on the waiting list for this event
            return true;
        }

        if (!$objEvent->allowDeregistration) {
            throw new EventDeregistrationException('Online deregistration not allowed in the event settings.', 'error', 'MSC.evt_dereg_onlineDeregNotPossible', [$this->user->firstname, $objEvent->title]);
        }

        if ($objEvent->startDate <= strtotime('today midnight')) {
            throw new EventDeregistrationException('Deregistration is no more possible. Event is already over.', 'error', 'MSC.evt_dereg_deregNotPossibleEventAlreadyOver', [$this->user->firstname, $objEvent->title]);
        }

        if ($objEvent->allowDeregistration && ($objEvent->startDate < (strtotime('today midnight') + $objEvent->deregistrationLimit * 24 * 3600))) {
            $dateLimit = Date::parse('d.m.Y', $objEvent->startDate - $objEvent->deregistrationLimit * 24 * 3600);

            throw new EventDeregistrationException('Deregistration is no more possible. Deadline expired.', 'error', 'MSC.evt_dereg_deregNotPossibleDeadlineExpired', [$this->user->firstname, $objEvent->title, $dateLimit]);
        }

        return true;
    }

    private function deregister(int $regId, array $arrDataSubmit): bool
    {
        $objRegistration = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findById($regId);

        $shouldDelete = false;

        // If the state of subscription is set "refused" the record will be deleted @todo
        // should we extend this behaviour to other subscription states?
        if (EventSubscriptionState::SUBSCRIPTION_REFUSED === $objRegistration->stateOfSubscription) {
            $shouldDelete = true;
        }

        $objEvent = $objRegistration->getRelated('eventId');

        // Unregister from event
        $objRegistration->stateOfSubscription = EventSubscriptionState::USER_HAS_UNSUBSCRIBED;

        $memberModel = $this->framework->getAdapter(MemberModel::class)->findById($this->user->id);

        $objRegistration->deregistrationCause = Input::post('deregistration_cause', null); // Input encoding!

        try {
            // Deregistration can be canceled via event listener. In the event listener you
            // have to throw a EventDeregistrationException.
            $event = new EventDeregistrationEvent($this->requestStack->getCurrentRequest(), $objRegistration, $objEvent, $memberModel, $this->moduleModel, $shouldDelete, $arrDataSubmit);
            $this->eventDispatcher->dispatch($event);
        } catch (EventDeregistrationException $e) {
            // Get the message from the exception
            $message = $this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default');
            $this->framework->getAdapter(Message::class)->add($message, EventDeregistrationException::TYPE_MAP[$e->getType()]);

            return false;
        }

        if ($objRegistration->isModified()) {
            $objRegistration->tstamp = time();
            $objRegistration->save();

            // Create new version
            $objVersions = new Versions($objRegistration->getTable(), $objRegistration->id);
            $objVersions->initialize();
            $objVersions->create();
        }

        $this->contaoGeneralLogger?->info(
            \sprintf(
                'User with SAC-User-ID %d has unsubscribed himself from event with ID: %d ("%s").%s',
                $objRegistration->sacMemberId,
                $objRegistration->eventId,
                $objRegistration->eventName,
                $event->shouldDelete() ? ' The data record was deleted.' : '',
            ),
            ['contao' => new ContaoContext(__METHOD__, Log::EVENT_UNSUBSCRIPTION)],
        );

        if ($event->shouldDelete()) {
            $objRegistration->delete();
        }

        $arrTokens = [
            'regId' => $regId,
            'shouldDelete' => $event->shouldDelete(),
            'event' => $objEvent->row(),
            'expiration' => time() + 180,
        ];

        $this->requestStack->getCurrentRequest()->getSession()->set('evt_dereg_success_'.$regId, $arrTokens);

        return true;
    }

    /**
     * Add messages from session flash to template.
     */
    private function addMessagesToTemplate(Request $request): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $this->template->set('hasInfoMessage', false);
        $this->template->set('hasErrorMessage', false);

        if ($messageAdapter->hasInfo()) {
            $session = $request->getSession()->getFlashBag()->get('contao.FE.info');
            $this->template->set('hasInfoMessage', true);
            $this->template->set('infoMessage', $session[0]);
        }

        if ($messageAdapter->hasError()) {
            $session = $request->getSession()->getFlashBag()->get('contao.FE.error');
            $this->template->set('hasErrorMessage', true);
            $this->template->set('errorMessage', $session[0]);
            $this->template->set('errorMessages', $session);
        }

        $messageAdapter->reset();
    }
}
