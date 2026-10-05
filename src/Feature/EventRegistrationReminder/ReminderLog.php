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

use Doctrine\DBAL\Connection;

/**
 * Reads and writes tl_event_registration_reminder_notification.
 *
 * One row per (user, calendar) pair: the last reminder (dateAdded), the one before
 * (prevReminderTstamp), a title and the history of the last 10 reminders.
 *
 * Deliberately not "readonly" so it can be mocked in unit tests.
 */
class ReminderLog
{
    public const TABLE = 'tl_event_registration_reminder_notification';

    private const HISTORY_LENGTH = 10;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Is a reminder due for this (user, calendar) pair?
     * Due if none was sent yet or the last one was sent at least $intervalDays days ago
     * (60 seconds tolerance for the start time of the cron).
     */
    public static function isReminderDue(int|null $lastSentAt, int $intervalDays, int $now): bool
    {
        if (null === $lastSentAt) {
            return true;
        }

        return $lastSentAt <= $now - $intervalDays * 86400 + 60;
    }

    /**
     * Timestamp of the last reminder for this (user, calendar) pair, null if none was sent yet.
     */
    public function getLastSentAt(int $userId, int $calendarId): int|null
    {
        $lastSentAt = $this->connection->fetchOne(
            'SELECT MAX(dateAdded) FROM '.self::TABLE.' WHERE user = ? AND calendar = ?',
            [$userId, $calendarId],
        );

        return $lastSentAt ? (int) $lastSentAt : null;
    }

    /**
     * Logs a reminder: replaces the row of this (user, calendar) pair.
     */
    public function logReminder(int $userId, int $calendarId, string $userName, int $intervalDays, int $now): void
    {
        $previous = $this->connection->fetchAssociative(
            'SELECT dateAdded, history FROM '.self::TABLE.' WHERE user = ? AND calendar = ? ORDER BY dateAdded DESC',
            [$userId, $calendarId],
        );

        $prevReminderTstamp = false !== $previous ? (int) $previous['dateAdded'] : 0;

        // Point out users who were already reminded within the last two intervals
        $suffix = $prevReminderTstamp && $prevReminderTstamp + 2 * $intervalDays * 86400 > $now ? \sprintf(' (last time %s)', date('d.m.Y', $prevReminderTstamp)) : '';

        // Latest entry on top, keep the last 10 entries
        $history = false !== $previous && '' !== trim((string) $previous['history']) ? explode("\n", (string) $previous['history']) : [];
        array_unshift($history, \sprintf('Sent a reminder to %s on %s;', $userName, date('d.m.Y H:i:s', $now)));
        $history = \array_slice($history, 0, self::HISTORY_LENGTH);

        $this->connection->transactional(
            static function (Connection $connection) use ($userId, $calendarId, $userName, $suffix, $now, $prevReminderTstamp, $history): void {
                $connection->executeStatement('DELETE FROM '.self::TABLE.' WHERE user = ? AND calendar = ?', [$userId, $calendarId]);

                $connection->insert(self::TABLE, [
                    'tstamp' => $now,
                    'dateAdded' => $now,
                    'prevReminderTstamp' => $prevReminderTstamp,
                    'title' => \sprintf('Sent a reminder to %s%s.', $userName, $suffix),
                    'user' => $userId,
                    'calendar' => $calendarId,
                    'history' => implode("\n", $history),
                ]);
            }
        );
    }
}
