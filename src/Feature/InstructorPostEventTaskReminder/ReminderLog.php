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
 * One row per sent notification; the rows are kept as history.
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
            'SELECT MAX(sentAt) FROM '.self::TABLE.' WHERE userId = ? AND calendarId = ?',
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
            'SELECT COUNT(id) FROM '.self::TABLE.' WHERE userId = ? AND calendarId = ?',
            [$userId, $calendarId],
        );
    }

    /**
     * @param int       $reminderCount the how-manieth notification for this (user, calendar) pair, including this one (1 = first)
     * @param list<int> $eventIds
     *
     * @return int the ID of the new log entry
     */
    public function add(int $userId, int $calendarId, int $notificationId, int $sentAt, int $reminderCount, int $openTaskCount, array $eventIds): int
    {
        $this->connection->insert(self::TABLE, [
            'tstamp' => time(),
            'userId' => $userId,
            'calendarId' => $calendarId,
            'notificationId' => $notificationId,
            'sentAt' => $sentAt,
            'reminderCount' => $reminderCount,
            'openTaskCount' => $openTaskCount,
            'eventIds' => implode(',', $eventIds),
            'delivered' => 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function markAsDelivered(int $logId): void
    {
        $this->connection->update(self::TABLE, ['tstamp' => time(), 'delivered' => 1], ['id' => $logId]);
    }
}
