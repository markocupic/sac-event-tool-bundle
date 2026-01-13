<?php

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler;

use Codefog\HasteBundle\Form\Form;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Markocupic\ContaoFrontendUserNotification\Notification\DefaultFrontendUserNotification;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Markocupic\SacEventToolBundle\Config\CarSeatInfo;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Config\TicketInfo;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\Exception\EventRegistrationException;
use Markocupic\SacEventToolBundle\Database\SyncEventRegistrationDatabase;
use Markocupic\SacEventToolBundle\Event\EventRegistrationEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('sacevt.event_registration.step_handler')]
class RegisterStep implements StepHandlerInterface, ValidationStepInterface
{
    private const string STEP = 'register';

    private const string TEMPLATE = '@Contao_MarkocupicSacEventToolBundle/frontend_module/partials/event_registration/step/register.html.twig';

    private const int PRIORITY = 200;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly CarSeatInfo $carSeatInfo,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LockFactory $lockFactory,
        private readonly Security $security,
        private readonly SyncEventRegistrationDatabase $syncEventRegistrationDatabase,
        private readonly TicketInfo $ticketInfo,
        private readonly TranslatorInterface $translator,
        #[Autowire('%sacevt.event_registration.config.reg_start_time_offset%')]
        private readonly int $regStartTimeOffset,
        private readonly LoggerInterface|null $contaoErrorLogger,
        private readonly LoggerInterface|null $contaoGeneralLogger,
    ) {
    }

    public static function getName(): string
    {
        return self::STEP;
    }

    public static function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getTemplateName(): string
    {
        return self::TEMPLATE;
    }

    public function doAutoForward(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool
    {
        return true;
    }

    public function validate(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return false;
        }

        $memberModel = $this->framework->getAdapter(MemberModel::class)->findById($user->id);

        if ($this->framework->getAdapter(CalendarEventsMemberModel::class)->isRegistered($memberModel->id, $eventModel->id)) {
            return true;
        }

        return false;
    }

    public function prepareStep(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): array
    {
        $template = [];
        $user = $this->security->getUser();
        $memberModel = $this->framework->getAdapter(MemberModel::class)->findById($user->id);
        $mainInstructorModel = $this->framework->getAdapter(UserModel::class)->findById($eventModel->mainInstructor);

        $lock = $this->lockFactory->createLock(resource: self::class.'-'.$eventModel->id, ttl: 30);
        $lock->acquire(true);

        $this->connection->beginTransaction();

        try {
            $options = [
                'regStartTimeOffset' => $this->regStartTimeOffset,
            ];

            // Run numerous test to check if the event can be registered. If one of the tests
            // fails, an EventRegistrationException exception is thrown.
            $this->validateEventRegistrationEligibility($eventModel, $memberModel, $mainInstructorModel, $options);

            // Check if the event is already fully booked.
            if ($this->calendarEventsUtil->eventIsFullyBooked($eventModel)) {
                $template['eventFullyBooked'] = true;
            }

            $template['form'] = $this->generateForm($eventModel, $memberModel, $moduleModel, $request);

            $this->connection->commit();
        } catch (RedirectResponseException $e) {
            $this->connection->commit();

            throw $e;
        } catch (EventRegistrationException $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            // Display a message to the user.
            $this->framework->getAdapter(Message::class)->add($this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default'), $e->getErrorLevel());

            $this->addErrorMessageToTemplate($template, $request);

            if (EventRegistrationException::LEVEL_ERROR === $e->getErrorLevel()) {
                $this->contaoErrorLogger?->error($e->getMessage(), ['contao' => new ContaoContext(__METHOD__, Log::EVENT_SUBSCRIPTION_ERROR)]);
            }
        } catch (\Throwable $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            // Display a message to the user. Display a message to the user.
            $this->framework->getAdapter(Message::class)->addError($this->translator->trans('ERR.evt_reg_unknownError', [], 'contao_default'));

            $this->addErrorMessageToTemplate($template, $request);

            if (method_exists($e, 'getMessage')) {
                $this->contaoErrorLogger?->error($e->getMessage(), ['contao' => new ContaoContext(__METHOD__, Log::EVENT_SUBSCRIPTION_ERROR)]);
            }
        } finally {
            $lock->release();
        }

        $template['event_model'] = $eventModel->current();

        return $template;
    }

    private function validateEventRegistrationEligibility(CalendarEventsModel $eventModel, MemberModel $memberModel, UserModel|null $mainInstructorModel = null, $options = []): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'regStartTimeOffset' => 0,
        ]);
        $resolver->setAllowedTypes('regStartTimeOffset', 'int');
        $options = $resolver->resolve($options);

        if (!$eventModel->published) {
            throw new EventRegistrationException('You can not subscribe to the current event because it is not published.', EventRegistrationException::LEVEL_ERROR, 'ERR.evt_reg_eventNotPublishedYet', []);
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

        if (true === $this->calendarEventsUtil->areBookingDatesOccupied($eventModel, $memberModel)) {
            throw new EventRegistrationException('You can not subscribe because you are already registered for another event at the same time.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventDateOverlapError', []);
        }

        if (null === $mainInstructorModel) {
            throw new EventRegistrationException('You can not register for this event because there is no main instructor assigned to the event.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_mainInstructorNotFound', [$eventModel->mainInstructor]);
        }

        if (empty($mainInstructorModel->email) || !Validator::isEmail($mainInstructorModel->email)) {
            throw new EventRegistrationException('You can not register for the event because the main instructor has an invalid email address.', EventRegistrationException::LEVEL_ERROR, 'ERR.evt_reg_mainInstructorsEmailAddrNotFound', [$eventModel->mainInstructor]);
        }

        if (!Validator::isEmail($memberModel->email)) {
            throw new EventRegistrationException('You can not subscribe to this event because of an invalid or not existent email address.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_membersEmailAddrNotFound', []);
        }
    }

    private function generateForm(CalendarEventsModel $eventModel, MemberModel $memberModel, ModuleModel $moduleModel, Request $request): Form
    {
        $objForm = new Form(
            'form-event-registration',
            'POST',
        );

        $objForm->setAction($request->getUri());

        if (null !== ($objJourney = $this->framework->getAdapter(CalendarEventsJourneyModel::class)->findById($eventModel->journey))) {
            if ('public-transport' === $objJourney->alias) {
                $objForm->addFormField('ticketInfo', $this->getFormFieldConfig('ticketInfo'));
            }

            if ('car' === $objJourney->alias) {
                $objForm->addFormField('carInfo', $this->getFormFieldConfig('carInfo'));
            }
        }

        if ($eventModel->askForAhvNumber) {
            $objForm->addFormField('ahvNumber', $this->getFormFieldConfig('ahvNumber'));
        }

        $objForm->addFormField('mobile', $this->getFormFieldConfig('mobile'));
        $objForm->addFormField('emergencyPhone', $this->getFormFieldConfig('emergencyPhone'));
        $objForm->addFormField('emergencyPhoneName', $this->getFormFieldConfig('emergencyPhoneName'));
        $objForm->addFormField('notes', $this->getFormFieldConfig('notes'));

        // Do only ask for food habits if we have a multi-day event.
        if ($this->hasMultiDaySpan($eventModel) || 'generalEvent' === $eventModel->eventType) {
            $objForm->addFormField('foodHabits', $this->getFormFieldConfig('foodHabits'));
        }

        $objForm->addFormField('agb', $this->getFormFieldConfig('agb'));
        $objForm->addFormField('hasAcceptedPrivacyRules', $this->getFormFieldConfig('hasAcceptedPrivacyRules'));

        $objForm->addFormField('submit', $this->getFormFieldConfig('submit'));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields. DO NOT use this
        // method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();

        // Get form presets from tl_member.
        $arrFields = ['phone', 'mobile', 'emergencyPhone', 'emergencyPhoneName', 'foodHabits', 'ahvNumber'];

        foreach ($arrFields as $field) {
            if ($objForm->hasFormField($field)) {
                $objWidget = $objForm->getWidget($field);

                if (empty($objWidget->value)) {
                    $objWidget = $objForm->getWidget($field);
                    $objWidget->value = $memberModel->{$field};
                }
            }
        }

        // validate() also checks whether the form has been submitted.
        if ($objForm->validate()) {
            // Save data to tl_calendar_events_member
            $arrDataForm = $objForm->fetchAll();

            $registrationModel = $this->createNewEventRegistration($eventModel, $memberModel, $arrDataForm);

            // Contao system log
            $strText = \sprintf(
                'New Registration from "%s %s [ID: %s]" for event with ID: %s ("%s").',
                $memberModel->firstname,
                $memberModel->lastname,
                $memberModel->id,
                $eventModel->id,
                $eventModel->title,
            );

            $this->contaoGeneralLogger?->info($strText, ['contao' => new ContaoContext(__METHOD__, Log::EVENT_SUBSCRIPTION)]);

            $event = new EventRegistrationEvent(
                $request,
                $registrationModel,
                $eventModel,
                $memberModel,
                $moduleModel,
                $registrationModel->row(),
            );

            // Dispatch event registration event (e.g., notify user upon event registration).
            $this->eventDispatcher->dispatch($event);

            // Reload the page.
            $this->framework->getAdapter(Controller::class)->reload();
        }

        return $objForm;
    }

    private function getFormFieldConfig(string $field): array
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

    private function createNewEventRegistration(CalendarEventsModel $eventModel, MemberModel $memberModel, array $arrFormData): CalendarEventsMemberModel
    {
        $arrData = array_merge($memberModel->row(), $arrFormData);

        // Do not send ahv number if it is not required.
        if (!isset($arrFormData['ahvNumber'])) {
            unset($arrData['ahvNumber']);
        }

        $arrData['contaoMemberId'] = $memberModel->id;
        $arrData['eventName'] = $eventModel->title;
        $arrData['eventId'] = $eventModel->id;
        $arrData['dateAdded'] = strtotime('now');
        $arrData['tstamp'] = strtotime('now');
        $arrData['uuid'] = Uuid::uuid4()->toString();
        $arrData['stateOfSubscription'] = $this->resolveSubscriptionsState($eventModel);
        $arrData['bookingType'] = BookingType::ONLINE_FORM;
        $arrData['sectionId'] = $memberModel->sectionId;

        // Save emergency phone number to user profile.
        $memberModel->emergencyPhone = $arrData['emergencyPhone'];

        // Save emergency phone-name to user profile.
        $memberModel->emergencyPhoneName = $arrData['emergencyPhoneName'];

        if (!empty($arrData['foodHabits'])) {
            $memberModel->foodHabits = $arrData['foodHabits'];
        }

        // Save AHV number to users profile.
        if (!empty($arrData['ahvNumber'])) {
            $memberModel->ahvNumber = $arrData['ahvNumber'];
            $memberModel->save();
        }

        if ($memberModel->isModified()) {
            $memberModel->save();
        }

        unset($arrData['id']);

        $registrationModel = new CalendarEventsMemberModel();
        $registrationModel->setRow($arrData);
        $registrationModel->save();

        // Update contactData & emergencyPhone, emergencyPhoneName and foodHabits in all
        // event registrations of the user
        if ($this->syncEventRegistrationDatabase->syncMember($memberModel->id)) {
            /** @var FrontendUser $user */
            $user = $this->security->getUser();
            new DefaultFrontendUserNotification(
                $user,
                'event_registration_controller::update_contact_data',
                'Mitteilung',
                'All deine persönlichen Daten (Adresse, Tel.-Nr., Notfallangaben, Essgewohnheiten etc.) wurden anhand deiner Eingaben bei deinen laufenden Anmeldungen aktualisiert.',
                time() + 60,
            );
        }

        return $registrationModel;
    }

    private function resolveSubscriptionsState(CalendarEventsModel $eventModel): string
    {
        $state = $this->calendarEventsUtil->eventIsFullyBooked($eventModel) ? EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST : EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED;

        if (EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST === $state) {
            return $state;
        }

        if (!$eventModel->autoConfirm) {
            return $state;
        }

        if ($eventModel->addIban) {
            return $state;
        }

        return EventSubscriptionState::SUBSCRIPTION_ACCEPTED;
    }

    private function addErrorMessageToTemplate(array &$template, Request $request): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);

        if ($messageAdapter->hasError()) {
            $errorMessage = $this->getFirstErrorMessage($request);
            $template['errorMessage'] = $errorMessage;
        }

        if ($messageAdapter->hasInfo()) {
            $infoMessage = $this->getFirstInfoMessage($request);
            $template['infoMessage'] = $infoMessage;
        }
    }

    private function hasMultiDaySpan(CalendarEventsModel $eventModel): bool
    {
        $durationInDays = \count($this->calendarEventsUtil->getEventTimestamps($eventModel));
        $startDate = $this->calendarEventsUtil->getStartTstamp($eventModel);
        $endDate = $this->calendarEventsUtil->getEndTstamp($eventModel);

        if ($durationInDays > 1 && $startDate + ($durationInDays - 1) * 86400 === $endDate) {
            return true;
        }

        return false;
    }

    private function getFirstErrorMessage(Request $request): string|null
    {
        return $this->getFirstMessage('error', $request);
    }

    private function getFirstInfoMessage(Request $request): string|null
    {
        return $this->getFirstMessage('info', $request);
    }

    private function getFirstMessage(string $type, Request $request): string|null
    {
        if (!\in_array($type, ['error', 'info'], true)) {
            throw new \InvalidArgumentException('Invalid message type. Allowed message types: "error", "info"');
        }

        $session = $request->getSession();
        $flash = $session->getFlashBag();

        return $flash->get('contao.FE.'.$type)[0] ?? null;
    }
}
