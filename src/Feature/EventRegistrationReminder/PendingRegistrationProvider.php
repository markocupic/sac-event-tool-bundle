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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;

/**
 * Finds the unconfirmed registrations (stateOfSubscription = subscription-not-confirmed)
 * of upcoming events and assigns them to the responsible user:
 * - the registration coordinator (tl_calendar_events.registrationGoesTo), if set
 * - otherwise the main instructor.
 *
 * An event is relevant if it is published, has not started yet and has at least one
 * unconfirmed registration that is older than the calendar setting "sendFirstReminderAfter" (days).
 *
 * Deliberately not "readonly" so it can be mocked in unit tests.
 */
class PendingRegistrationProvider
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<int> IDs of the users with at least one relevant event in this calendar
     */
    public function getRecipientIds(CalendarModel $calendar, int $now): array
    {
        return array_keys($this->getPendingEventsByRecipient($calendar, $now));
    }

    /**
     * @return list<PendingEvent>
     */
    public function getPendingEvents(int $userId, CalendarModel $calendar, int $now): array
    {
        return $this->getPendingEventsByRecipient($calendar, $now)[$userId] ?? [];
    }

    /**
     * The user if he is active and has an e-mail address, otherwise null.
     */
    public function getRecipient(int $userId): UserModel|null
    {
        $this->framework->initialize();

        $user = $this->framework->getAdapter(UserModel::class)->findById($userId);

        if (null === $user || $user->disable || '' === trim((string) $user->email)) {
            return null;
        }

        return $user;
    }

    /**
     * @return array<int, list<PendingEvent>> userId => pending events
     */
    public function getPendingEventsByRecipient(CalendarModel $calendar, int $now): array
    {
        $firstReminderAfterDays = (int) $calendar->sendFirstReminderAfter;

        if ($firstReminderAfterDays < 1) {
            return [];
        }

        $overdueLimit = $now - $firstReminderAfterDays * 86400;
        $pendingEventsByRecipient = [];

        foreach ($this->fetchUpcomingEvents((int) $calendar->id, $now) as $row) {
            $userId = (int) $row['registrationGoesTo'] ?: (int) $row['mainInstructorId'];

            if ($userId < 1 || null === $this->getRecipient($userId)) {
                continue;
            }

            $overdue = [];
            $recent = [];

            foreach ($this->fetchUnconfirmedRegistrations((int) $row['id'], $now) as $registration) {
                $pendingRegistration = new PendingRegistration(
                    (string) $registration['firstname'],
                    (string) $registration['lastname'],
                    (string) $registration['gender'],
                    (int) $registration['sacMemberId'],
                    max(0, intdiv($now - (int) $registration['dateAdded'], 86400)),
                );

                if ((int) $registration['dateAdded'] <= $overdueLimit) {
                    $overdue[] = $pendingRegistration;
                } else {
                    $recent[] = $pendingRegistration;
                }
            }

            // Only overdue registrations trigger the reminder
            if (empty($overdue)) {
                continue;
            }

            $pendingEventsByRecipient[$userId][] = new PendingEvent(
                (int) $row['id'],
                // Contao stores the title input-encoded (e.g. "&amp;"); the template escapes it itself
                html_entity_decode((string) $row['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                (string) $row['eventType'],
                $overdue,
                $recent,
            );
        }

        return $pendingEventsByRecipient;
    }

    /**
     * Published events of the calendar that have not started yet,
     * with the registration coordinator and the main instructor.
     *
     * @return list<array{id: int|string, title: string, eventType: string, registrationGoesTo: int|string, mainInstructorId: int|string|null}>
     */
    protected function fetchUpcomingEvents(int $calendarId, int $now): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT e.id, e.title, e.eventType, e.registrationGoesTo,
                (SELECT i.userId FROM tl_calendar_events_instructor i WHERE i.pid = e.id AND i.isMainInstructor = 1 LIMIT 1) AS mainInstructorId
            FROM tl_calendar_events e
            WHERE e.pid = ? AND e.published = 1 AND e.startDate > ?
            ORDER BY e.startDate, e.id',
            [$calendarId, $now],
        );
    }

    /**
     * Unconfirmed registrations of an event with complete personal data, oldest first.
     *
     * @return list<array{firstname: string, lastname: string, gender: string, sacMemberId: int|string, dateAdded: int|string}>
     */
    protected function fetchUnconfirmedRegistrations(int $eventId, int $now): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT firstname, lastname, gender, sacMemberId, dateAdded
            FROM tl_calendar_events_member
            WHERE eventId = ? AND stateOfSubscription = ? AND dateAdded <= ?
                AND firstname != '' AND lastname != '' AND gender != '' AND street != '' AND postal != '' AND city != ''
            ORDER BY dateAdded, id",
            [$eventId, EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED, $now],
        );
    }
}
