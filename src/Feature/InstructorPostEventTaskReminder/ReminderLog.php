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

namespace Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder;

use Doctrine\DBAL\Connection;

/**
 * Reads and writes tl_instructor_post_event_task_reminder_log.
 *
 * One row per (user, calendar) pair (unique index). It holds the LAST notification
 * (sentAt, notificationId, openTaskCount, eventIds, delivered) and the total number
 * of notifications sent so far (reminderCount). Every further notification updates the row.
 *
 * Deliberately not "readonly" so it can be mocked in unit tests.
 */
class ReminderLog
{
    public const TABLE = 'tl_instructor_post_event_task_reminder_log';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Timestamp of the last notification for this (user, calendar) pair, null if none was sent yet.
     */
    public function getLastSentAt(int $userId, int $calendarId): int|null
    {
        $lastSentAt = $this->connection->fetchOne(
            'SELECT sentAt FROM '.self::TABLE.' WHERE userId = ? AND calendarId = ?',
            [$userId, $calendarId],
        );

        return $lastSentAt ? (int) $lastSentAt : null;
    }

    /**
     * Number of notifications sent so far for this (user, calendar) pair.
     */
    public function countSent(int $userId, int $calendarId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT reminderCount FROM '.self::TABLE.' WHERE userId = ? AND calendarId = ?',
            [$userId, $calendarId],
        );
    }

    /**
     * Logs a notification for this (user, calendar) pair:
     * - first notification: inserts the row with reminderCount = 1
     * - further notifications: overwrites the data of the last notification and increments reminderCount
     *
     * Atomic thanks to the unique index (userId, calendarId).
     *
     * @param list<int> $eventIds
     *
     * @return int the ID of the log entry
     */
    public function logNotification(int $userId, int $calendarId, int $notificationId, int $sentAt, int $openTaskCount, array $eventIds): int
    {
        $this->connection->executeStatement(
            'INSERT INTO '.self::TABLE.' (tstamp, userId, calendarId, notificationId, sentAt, reminderCount, openTaskCount, eventIds, delivered)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, 0)
            ON DUPLICATE KEY UPDATE
                tstamp = VALUES(tstamp),
                notificationId = VALUES(notificationId),
                sentAt = VALUES(sentAt),
                reminderCount = reminderCount + 1,
                openTaskCount = VALUES(openTaskCount),
                eventIds = VALUES(eventIds),
                delivered = 0',
            [time(), $userId, $calendarId, $notificationId, $sentAt, $openTaskCount, implode(',', $eventIds)],
        );

        return (int) $this->connection->fetchOne(
            'SELECT id FROM '.self::TABLE.' WHERE userId = ? AND calendarId = ?',
            [$userId, $calendarId],
        );
    }

    public function markAsDelivered(int $logId): void
    {
        $this->connection->update(self::TABLE, ['tstamp' => time(), 'delivered' => 1], ['id' => $logId]);
    }
}
