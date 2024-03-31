<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
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
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Event\EventRegistrationEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Terminal42\NotificationCenterBundle\NotificationCenter;

#[AsEventListener]
final class NotifyMemberOnEventRegistration
{
    public const PRIORITY = 10000;

    private Adapter $calendarEventsHelperAdapter;
    private Adapter $eventsAdapter;
    private Adapter $userModelAdapter;

    private array $arrData = [];
    private MemberModel|null $memberModel = null;
    private CalendarEventsModel|null $eventModel = null;
    private CalendarEventsMemberModel|null $eventMemberModel = null;
    private ModuleModel|null $moduleModel = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly NotificationCenter $notificationCenter,
        private readonly string $sacevtLocale,
    ) {
        $this->calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->eventsAdapter = $this->framework->getAdapter(Events::class);
        $this->userModelAdapter = $this->framework->getAdapter(UserModel::class);
    }

    public function __invoke(EventRegistrationEvent $event): void
    {
        $this->initialize($event);

        $notificationId = $this->moduleModel->receiptEventRegistrationNotificationId;

        /** @var UserModel $objInstructor */
        $objInstructor = $this->userModelAdapter->findByPk($this->eventModel->mainInstructor);

        $strRegistrationGoesToName = '';
        $strRegistrationGoesToEmail = '';

        // Switch sender/recipient if the main instructor has delegated
        // event registrations administration work to another person.
        $bypassRegistration = false;

        if ($this->eventModel->registrationGoesTo) {
            $objUser = $this->userModelAdapter->findByPk($this->eventModel->registrationGoesTo);

            if (null !== $objUser) {
                if (!empty($objUser->email)) {
                    $strRegistrationGoesToName = $objUser->name;
                    $strRegistrationGoesToEmail = $objUser->email;
                }
            }

            if (!empty($strRegistrationGoesToEmail) && !empty($strRegistrationGoesToName)) {
                $bypassRegistration = true;
            }
        }

        // Use terminal42/notification_center
        if ($notificationId) {
            // Get the event type
            $eventType = \strlen($GLOBALS['TL_LANG']['MSC'][$this->eventModel->eventType]) ? $GLOBALS['TL_LANG']['MSC'][$this->eventModel->eventType].': ' : '';

            // Set token array
            $arrTokens = [
                'event_leistungen' => html_entity_decode((string) $this->eventModel->leistungen),
                'event_type' => html_entity_decode((string) $this->eventModel->eventType),
                'event_add_iban' => $this->eventModel->addIban,
                'event_course_id' => $this->eventModel->courseId,
                'event_iban' => $this->eventModel->addIban ? html_entity_decode((string) $this->eventModel->iban) : '',
                'event_ibanBeneficiary' => $this->eventModel->addIban ? html_entity_decode((string) $this->eventModel->ibanBeneficiary) : '',
                'event_id' => $this->eventModel->id,
                'event_link_detail' => $this->eventsAdapter->generateEventUrl($this->eventModel, true),
                'event_name' => html_entity_decode($eventType.$this->eventModel->title),
                'instructor_email' => $bypassRegistration ? html_entity_decode((string) $strRegistrationGoesToEmail) : html_entity_decode($objInstructor->email),
                'instructor_name' => $bypassRegistration ? html_entity_decode((string) $strRegistrationGoesToName) : html_entity_decode($objInstructor->name),
                'participant_ahv_number' => html_entity_decode((string) ($this->arrData['ahvNumber'] ?? '')),
                'participant_city' => html_entity_decode($this->memberModel->city),
                'participant_contao_member_id' => $this->memberModel->id,
                'participant_date_of_birth' => ($this->arrData['dateOfBirth'] ?? 0) > 0 ? date('d.m.Y', (int) $this->arrData['dateOfBirth']) : '---',
                'participant_email' => $this->memberModel->email !== $this->arrData['email'] ? $this->arrData['email'] : $this->memberModel->email,
                'participant_emergency_phone' => $this->arrData['emergencyPhone'],
                'participant_emergency_phone_name' => html_entity_decode((string) ($this->arrData['emergencyPhoneName'] ?? '')),
                'participant_food_habits' => $this->arrData['foodHabits'] ?? '',
                'participant_has_lead_climbing_education' => $this->memberModel->hasLeadClimbingEducation,
                'participant_mobile' => $this->arrData['mobile'],
                'participant_name' => html_entity_decode($this->memberModel->firstname.' '.$this->memberModel->lastname),
                'participant_notes' => html_entity_decode((string) ($this->arrData['notes'] ?? '')),
                'participant_postal' => $this->memberModel->postal,
                'participant_sac_member_id' => $this->memberModel->sacMemberId,
                'participant_section_membership' => $this->calendarEventsHelperAdapter->getSectionMembershipAsString($this->memberModel),
                'participant_state_of_subscription' => html_entity_decode((string) $GLOBALS['TL_LANG']['MSC'][$this->eventMemberModel->stateOfSubscription]),
                'participant_street' => html_entity_decode($this->memberModel->street),
            ];

            $this->notificationCenter->sendNotification($notificationId, $arrTokens, $this->sacevtLocale);
        }
    }

    private function initialize(EventRegistrationEvent $event): void
    {
        $this->arrData = $event->getData();
        $this->memberModel = $event->getContaoMemberModel();
        $this->eventModel = $event->getEvent();
        $this->eventMemberModel = $event->getRegistration();
        $this->moduleModel = $event->getRegistrationModule();

        if (!$this->framework->isInitialized()) {
            $this->framework->initialize();
        }
    }
}
