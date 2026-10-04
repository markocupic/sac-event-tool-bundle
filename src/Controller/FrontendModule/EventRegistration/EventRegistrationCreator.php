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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Markocupic\ContaoFrontendUserNotification\Notification\DefaultFrontendUserNotification;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationDatabaseSync\SyncEventRegistrationDatabase;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Lock\LockFactory;

/**
 * Saves a new event registration (tl_calendar_events_member).
 *
 * The lock (one per event) is only held while saving: the subscription state (waiting list
 * if the event is fully booked) and the insert must not overlap with another registration.
 */
class EventRegistrationCreator
{
    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly LockFactory $lockFactory,
        private readonly Security $security,
        private readonly SyncEventRegistrationDatabase $syncEventRegistrationDatabase,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $formData submitted form data
     */
    public function create(CalendarEventsModel $eventModel, MemberModel $memberModel, array $formData): CalendarEventsMemberModel
    {
        $lock = $this->lockFactory->createLock(resource: self::class.'-'.$eventModel->id, ttl: 30);
        $lock->acquire(true);

        try {
            // Submitted twice (e.g. double click): return the existing registration
            $existingRegistration = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findByMemberAndEvent($memberModel, $eventModel);

            if ($existingRegistration instanceof CalendarEventsMemberModel) {
                return $existingRegistration;
            }

            $registrationModel = $this->connection->transactional(
                function () use ($eventModel, $memberModel, $formData): CalendarEventsMemberModel {
                    $this->updateMemberProfile($memberModel, $formData);

                    $registrationModel = $this->createRegistrationModel();
                    $registrationModel->setRow($this->getRegistrationData($eventModel, $memberModel, $formData));
                    $registrationModel->save();

                    return $registrationModel;
                },
            );
        } finally {
            $lock->release();
        }

        $this->contaoGeneralLogger?->info(
            \sprintf(
                'New Registration from "%s %s [ID: %s]" for event with ID: %s ("%s").',
                $memberModel->firstname,
                $memberModel->lastname,
                $memberModel->id,
                $eventModel->id,
                $eventModel->title,
            ),
            ['contao' => new ContaoContext(__METHOD__, Log::EVENT_SUBSCRIPTION)],
        );

        // Update the contact data, emergency phone and food habits in all registrations of the member
        if ($this->syncEventRegistrationDatabase->syncMember((int) $memberModel->id)) {
            $user = $this->security->getUser();

            if ($user instanceof FrontendUser) {
                $this->notifyContactDataUpdated($user);
            }
        }

        return $registrationModel;
    }

    public function resolveSubscriptionState(CalendarEventsModel $eventModel): string
    {
        if ($this->calendarEventsUtil->eventIsFullyBooked($eventModel)) {
            return EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST;
        }

        if (!$eventModel->autoConfirm || $eventModel->addIban) {
            return EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED;
        }

        return EventSubscriptionState::SUBSCRIPTION_ACCEPTED;
    }

    /**
     * @param array<string, mixed> $formData
     *
     * @return array<string, mixed>
     */
    public function getRegistrationData(CalendarEventsModel $eventModel, MemberModel $memberModel, array $formData): array
    {
        $data = array_merge($memberModel->row(), $formData);

        // Do not store the AHV number if it was not asked for
        if (!isset($formData['ahvNumber'])) {
            unset($data['ahvNumber']);
        }

        unset($data['id']);

        $now = $this->getCurrentTimestamp();

        $data['contaoMemberId'] = $memberModel->id;
        $data['eventName'] = $eventModel->title;
        $data['eventId'] = $eventModel->id;
        $data['dateAdded'] = $now;
        $data['tstamp'] = $now;
        $data['uuid'] = $this->generateUuid();
        $data['stateOfSubscription'] = $this->resolveSubscriptionState($eventModel);
        $data['bookingType'] = BookingType::ONLINE_FORM;
        $data['sectionId'] = $memberModel->sectionId;

        return $data;
    }

    /**
     * Saves the emergency contact, food habits and AHV number to the member profile (saved once, only if changed).
     *
     * @param array<string, mixed> $formData
     */
    public function updateMemberProfile(MemberModel $memberModel, array $formData): void
    {
        $memberModel->emergencyPhone = $formData['emergencyPhone'] ?? $memberModel->emergencyPhone;
        $memberModel->emergencyPhoneName = $formData['emergencyPhoneName'] ?? $memberModel->emergencyPhoneName;

        if (!empty($formData['foodHabits'])) {
            $memberModel->foodHabits = $formData['foodHabits'];
        }

        if (!empty($formData['ahvNumber'])) {
            $memberModel->ahvNumber = $formData['ahvNumber'];
        }

        if ($memberModel->isModified()) {
            $memberModel->save();
        }
    }

    /**
     * Test seam: returns the current UNIX timestamp.
     */
    protected function getCurrentTimestamp(): int
    {
        return time();
    }

    /**
     * Test seam: generates a new UUID (v4) string.
     */
    protected function generateUuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Test seam: creates an empty registration model.
     */
    protected function createRegistrationModel(): CalendarEventsMemberModel
    {
        return new CalendarEventsMemberModel();
    }

    /**
     * Test seam: notifies the member that their contact data has been synced.
     */
    protected function notifyContactDataUpdated(FrontendUser $user): void
    {
        new DefaultFrontendUserNotification(
            $user,
            'event_registration_controller::update_contact_data',
            'Mitteilung',
            'All deine persönlichen Daten (Adresse, Tel.-Nr., Notfallangaben, Essgewohnheiten etc.) wurden anhand deiner Eingaben bei deinen laufenden Anmeldungen aktualisiert.',
            $this->getCurrentTimestamp() + 60,
        );
    }
}
