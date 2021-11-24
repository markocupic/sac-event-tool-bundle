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

namespace Markocupic\SacEventToolBundle\Subscriber;

use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Event\EventSubscriptionEvent;
use NotificationCenter\Model\Notification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class NotifyOnEventSubscription implements EventSubscriberInterface
{
    public const PRIORITY = 10000;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var array
     */
    private $arrData;

    /**
     * @var MemberModel
     */
    private $memberModel;

    /**
     * @var CalendarEventsModel
     */
    private $eventModel;

    /**
     * @var CalendarEventsMemberModel
     */
    private $eventMemberModel;

    /**
     * @var ModuleModel
     */
    private $moduleModel;

    public static function getSubscribedEvents(): array
    {
        return [
            EventSubscriptionEvent::NAME => ['notifyUserOnEventSubscription', self::PRIORITY],
        ];
    }

    public function notifyUserOnEventSubscription(EventSubscriptionEvent $event): void
    {
        $this->initialize($event);

        // Set adapters
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var Events $eventsAdapter */
        $eventsAdapter = $this->framework->getAdapter(Events::class);

        /** @var Notification $notificationAdapter */
        $notificationAdapter = $this->framework->getAdapter(Notification::class);

        /** @var Notification $objNotification */
        $objNotification = $notificationAdapter->findByPk($this->moduleModel->receiptEventRegistrationNotificationId);

        // Switch sender/recipient if the main instructor has delegated event registrations administration work to somebody else
        $bypassRegistration = false;

        /** @var UserModel $objInstructor */
        $objInstructor = $userModelAdapter->findByPk($this->eventModel->mainInstructor);

        if ($this->eventModel->registrationGoesTo) {
            $strRegistrationGoesToName = '';
            $strRegistrationGoesToEmail = '';

            $objUser = $userModelAdapter->findByPk($this->eventModel->registrationGoesTo);

            if (null !== $objUser) {
                if ('' !== $objUser->email) {
                    $strRegistrationGoesToName = $objUser->name;
                    $strRegistrationGoesToEmail = $objUser->email;
                }
            }

            if ('' !== $strRegistrationGoesToEmail && '' !== $strRegistrationGoesToName) {
                $bypassRegistration = true;
            }
        }

        // Use terminal42/notification_center
        if (null !== $objNotification) {
            // Get the event type
            $eventType = \strlen($GLOBALS['TL_LANG']['MSC'][$this->eventModel->eventType]) ? $GLOBALS['TL_LANG']['MSC'][$this->eventModel->eventType].': ' : '';

            // Set token array
            $arrTokens = [
                'event_name' => html_entity_decode((string) $eventType.$this->eventModel->title),
                'event_add_iban' => $this->eventModel->addIban,
                'event_iban' => $this->eventModel->addIban ? html_entity_decode($this->eventModel->iban) : '',
                'event_type' => html_entity_decode((string) $this->eventModel->eventType),
                'event_course_id' => $this->eventModel->courseId,
                'instructor_name' => $bypassRegistration ? html_entity_decode((string) $strRegistrationGoesToName) : html_entity_decode((string) $objInstructor->name),
                'instructor_email' => $bypassRegistration ? html_entity_decode((string) $strRegistrationGoesToEmail) : html_entity_decode((string) $objInstructor->email),
                'participant_name' => html_entity_decode($this->memberModel->firstname.' '.$this->memberModel->lastname),
                'participant_email' => $this->memberModel->email !== $this->arrData['email'] ? $this->arrData['email'] : $this->memberModel->email,
                'participant_emergency_phone' => $this->arrData['emergencyPhone'],
                'participant_emergency_phone_name' => html_entity_decode((string) $this->arrData['emergencyPhoneName']),
                'participant_street' => html_entity_decode((string) $this->memberModel->street),
                'participant_postal' => $this->memberModel->postal,
                'participant_city' => html_entity_decode((string) $this->memberModel->city),
                'participant_contao_member_id' => $this->memberModel->id,
                'participant_sac_member_id' => $this->memberModel->sacMemberId,
                'participant_ahv_number' => html_entity_decode((string) $this->arrData['ahvNumber']),
                'participant_section_membership' => $calendarEventsHelperAdapter->getSectionMembershipAsString($this->memberModel),
                'participant_mobile' => $this->arrData['mobile'],
                'participant_date_of_birth' => $this->arrData['dateOfBirth'] > 0 ? $dateAdapter->parse('d.m.Y', $this->arrData['dateOfBirth']) : '---',
                'participant_food_habits' => $this->arrData['foodHabits'],
                'participant_notes' => html_entity_decode((string) $this->arrData['notes']),
                'participant_state_of_subscription' => html_entity_decode((string) $this->eventMemberModel->stateOfSubscription),
                'participant_has_lead_climbing_education' => $this->memberModel->hasLeadClimbingEducation,
                'event_id' => $this->eventModel->id,
                'event_link_detail' => 'https://'.$environmentAdapter->get('host').'/'.$eventsAdapter->generateEventUrl($this->eventModel),
            ];

            $objNotification->send($arrTokens, 'de');
        }
    }

    private function initialize(EventSubscriptionEvent $event): void
    {
        $this->framework = $event->framework;
        $this->arrData = $event->arrData;
        $this->memberModel = $event->memberModel;
        $this->eventModel = $event->eventModel;
        $this->eventMemberModel = $event->eventMemberModel;
        $this->moduleModel = $event->moduleModel;

        if (!$this->framework->isInitialized()) {
            $this->framework->initialize(true);
        }
    }
}
