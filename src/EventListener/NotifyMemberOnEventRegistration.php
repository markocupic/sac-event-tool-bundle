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

namespace Markocupic\SacEventToolBundle\EventListener;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Events;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Event\EventRegistrationEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Terminal42\NotificationCenterBundle\Receipt\ReceiptCollection;

#[AsEventListener]
final class NotifyMemberOnEventRegistration
{
    public const int PRIORITY = 10000;

    private Adapter $eventsAdapter;
    private Adapter $userModelAdapter;

    private array $arrData = [];
    private MemberModel|null $memberModel = null;
    private CalendarEventsModel|null $eventModel = null;
    private CalendarEventsMemberModel|null $eventMemberModel = null;
    private ModuleModel|null $moduleModel = null;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        private readonly NotificationCenter $notificationCenter,
        private readonly TranslatorInterface $translator,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoErrorLogger,
    ) {
        $this->eventsAdapter = $this->framework->getAdapter(Events::class);
        $this->userModelAdapter = $this->framework->getAdapter(UserModel::class);
    }

    public function __invoke(EventRegistrationEvent $event): void
    {
        try {
            $this->initialize($event);
            $notificationId = $this->moduleModel->receiptEventRegistrationNotificationId;

            if ($notificationId) {
                $this->sendNotification($notificationId, $this->getTokens());
            }
        } catch (\Throwable $e) {
            $this->contaoErrorLogger?->error((string) $e);
        }
    }

    private function initialize(EventRegistrationEvent $event): void
    {
        $this->arrData = $event->getData();
        $this->eventMemberModel = $event->getRegistration();
        $this->eventModel = $event->getEvent();
        $this->memberModel = $event->getContaoMemberModel();
        $this->moduleModel = $event->getRegistrationModule();

        if (!$this->framework->isInitialized()) {
            $this->framework->initialize();
        }
    }

    private function getTokens(): array
    {
        $instructor = $this->userModelAdapter->findByPk($this->eventModel->mainInstructor);
        $delegatedInstructor = $this->getDelegatedInstructor();

        $tokens = [
            'event_leistungen' => $this->eventModel->leistungen,
            'event_type' => $this->eventModel->eventType,
            'event_type_translated' => $this->getTranslatedEventType(),
            'event_add_iban' => $this->eventModel->addIban,
            'event_course_id' => $this->eventModel->courseId,
            'event_iban' => $this->eventModel->addIban ? $this->eventModel->iban : '',
            'event_ibanBeneficiary' => $this->eventModel->addIban ? $this->eventModel->ibanBeneficiary : '',
            'event_id' => $this->eventModel->id,
            'event_link_detail' => $this->eventsAdapter->generateEventUrl($this->eventModel, true),
            'event_title' => $this->eventModel->title,
            'instructor_email' => $delegatedInstructor['email'] ?? $instructor->email,
            'instructor_name' => $delegatedInstructor['name'] ?? $instructor->name,
            'participant_ahv_number' => $this->arrData['ahvNumber'] ?? '',
            'participant_city' => $this->memberModel->city,
            'participant_contao_member_id' => $this->memberModel->id,
            'participant_date_of_birth' => $this->getDateOfBirth(),
            'participant_email' => $this->arrData['email'],
            'participant_emergency_phone' => $this->arrData['emergencyPhone'],
            'participant_emergency_phone_name' => $this->arrData['emergencyPhoneName'] ?? '',
            'participant_food_habits' => $this->arrData['foodHabits'] ?? '',
            'participant_has_lead_climbing_education' => $this->memberModel->hasLeadClimbingEducation,
            'participant_phone' => $this->arrData['phone'],
            'participant_mobile' => $this->arrData['mobile'],
            'participant_name' => $this->memberModel->firstname.' '.$this->memberModel->lastname,
            'participant_notes' => $this->arrData['notes'],
            'participant_postal' => $this->memberModel->postal,
            'participant_sac_member_id' => $this->memberModel->sacMemberId,
            'participant_section_membership' => $this->getSectionMembership(),
            'participant_state_of_subscription' => $this->getSubscriptionState(),
            'participant_street' => $this->memberModel->street,
        ];

        return $this->decodeEntities($tokens);
    }

    private function getDelegatedInstructor(): array|null
    {
        if (!$this->eventModel->registrationGoesTo) {
            return null;
        }

        $user = $this->userModelAdapter->findByPk($this->eventModel->registrationGoesTo);

        if (!empty($user->email) && !empty($user->name)) {
            return [
                'name' => $user->name,
                'email' => $user->email,
            ];
        }

        return null;
    }

    private function sendNotification(int $notificationId, array $tokens): ReceiptCollection
    {
        return $this->notificationCenter->sendNotification($notificationId, $tokens, $this->sacevtLocale);
    }

    private function getTranslatedEventType(): string
    {
        return $this->translator->trans('MSC.'.$this->eventModel->eventType, [], 'contao_default');
    }

    private function getDateOfBirth(): string
    {
        return !empty($this->arrData['dateOfBirth']) ? date('d.m.Y', (int) $this->arrData['dateOfBirth']) : '---';
    }

    private function getSectionMembership(): string
    {
        return $this->calendarEventsUtil->getSectionMembershipAsString($this->memberModel);
    }

    private function getSubscriptionState(): string
    {
        return $this->translator->trans('MSC.'.$this->eventMemberModel->stateOfSubscription, [], 'contao_default');
    }

    private function decodeEntities(array $tokens): array
    {
        return array_map(static fn ($item) => \is_string($item) ? html_entity_decode($item) : $item, $tokens);
    }
}
