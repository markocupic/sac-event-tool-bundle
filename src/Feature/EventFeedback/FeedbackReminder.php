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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;

/**
 * Reads and writes the scheduled feedback requests (tl_event_feedback_reminder).
 *
 * One row per (registration, sending date). A row is claimed (dispatched = 1) when the cron
 * dispatches it and deleted after sending.
 *
 * Deliberately not "readonly" so it can be mocked in unit tests.
 */
class FeedbackReminder
{
    public const TABLE = 'tl_event_feedback_reminder';

    /**
     * Claimed rows that were not deleted by the handler (e.g. worker crash) are removed after this time.
     */
    private const CLAIM_TIMEOUT = 86400;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Sending dates: the reminders start when the participation is confirmed,
     * but not before the end of the event.
     *
     * @param list<int> $sendAfterDays e.g. [0, 14, 28]
     *
     * @return array{executionDates: list<int>, expiration: int}
     */
    public static function getSchedule(int $now, int $eventEndDate, array $sendAfterDays, int $expirationDays): array
    {
        $start = (new \DateTimeImmutable())->setTimestamp(max($now, $eventEndDate));

        return [
            'executionDates' => array_map(static fn (int $days): int => $start->modify(\sprintf('+%d day', $days))->getTimestamp(), $sendAfterDays),
            'expiration' => $start->modify(\sprintf('+%d day', $expirationDays))->getTimestamp(),
        ];
    }

    /**
     * Schedules the feedback requests for a registration. Existing rows (same sending date) are not duplicated.
     *
     * @param list<int> $sendAfterDays
     */
    public function schedule(CalendarEventsMemberModel $registration, int $eventEndDate, array $sendAfterDays, int $expirationDays): void
    {
        $now = time();
        $schedule = self::getSchedule($now, $eventEndDate, $sendAfterDays, $expirationDays);

        foreach ($schedule['executionDates'] as $executionDate) {
            $this->connection->executeStatement(
                'INSERT INTO '.self::TABLE.' (pid, uuid, executionDate, dateAdded, tstamp, expiration) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE dateAdded = VALUES(dateAdded), tstamp = VALUES(tstamp)',
                [(int) $registration->id, (string) $registration->uuid, $executionDate, $now, $now, $schedule['expiration']],
            );
        }
    }

    public function deleteByUuid(string $uuid): void
    {
        $this->connection->delete(self::TABLE, ['uuid' => $uuid]);
    }

    public function delete(int $id): void
    {
        $this->connection->delete(self::TABLE, ['id' => $id]);
    }

    /**
     * Removes expired rows and claimed rows that were never sent.
     */
    public function deleteObsolete(int $now): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM '.self::TABLE.' WHERE expiration < ? OR (dispatched = 1 AND dispatchTime < ?)',
            [$now, $now - self::CLAIM_TIMEOUT],
        );
    }

    /**
     * Pending rows whose sending date has been reached, with the configuration name of the calendar.
     *
     * @return list<array{id: int|string, executionDate: int|string, configuration: string|null}>
     */
    public function findPending(int $now, int $limit = 500): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT r.id, r.executionDate, c.onlineFeedbackConfiguration AS configuration
            FROM '.self::TABLE.' r
            LEFT JOIN tl_calendar_events_member m ON m.id = r.pid
            LEFT JOIN tl_calendar_events e ON e.id = m.eventId
            LEFT JOIN tl_calendar c ON c.id = e.pid
            WHERE r.dispatched = 0 AND r.expiration > ? AND r.executionDate <= ?
            ORDER BY r.executionDate, r.id
            LIMIT '.$limit,
            [$now, $now],
        );
    }

    /**
     * Marks a row as dispatched. Returns false if another process claimed it first.
     */
    public function claim(int $id, int $now): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'UPDATE '.self::TABLE.' SET dispatched = 1, dispatchTime = ? WHERE id = ? AND dispatched = 0',
            [$now, $id],
        );
    }
}
