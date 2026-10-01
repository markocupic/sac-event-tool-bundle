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

namespace Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder;

/**
 * Pure date logic (no database, no framework).
 *
 * All calculations are based on whole calendar days in the PHP default time zone
 * (the same approach as EventReminderCron), so the time of day of the event end
 * and of the cron run do not matter.
 */
final readonly class ReminderSchedule
{
    /**
     * Latest endDate an event may have to be "due":
     * its grace period of $firstOffsetDays days after the end day has expired.
     *
     * Example: firstOffset = 7, today = 08.10. → events ending on 01.10. (any time) or earlier are due.
     */
    public static function getDueEndDateMax(int $firstOffsetDays, \DateTimeImmutable $now): int
    {
        return self::startOfDay($now)
            ->modify(\sprintf('-%d days', $firstOffsetDays))
            ->modify('+1 day')
            ->getTimestamp() - 1
        ;
    }

    /**
     * Earliest endDate an event may have to be checked at all.
     *
     * Example: lookback = 365, today = 08.10.2026 → events ending on 08.10.2025 or later are checked.
     */
    public static function getLookbackEndDateMin(int $lookbackDays, \DateTimeImmutable $now): int
    {
        return self::startOfDay($now)
            ->modify(\sprintf('-%d days', $lookbackDays))
            ->getTimestamp()
        ;
    }

    /**
     * Is a notification due for a (recipient, calendar) pair?
     *
     * - Never sent before ($lastSentAt null or 0): due.
     * - Otherwise due as soon as $intervalDays calendar days have passed since the day of the last notification.
     *   Sent on Monday at 03:45 with interval 7 → due again from next Monday 00:00,
     *   even if the cron runs a few seconds earlier than last time.
     */
    public static function isNotificationDue(int|null $lastSentAt, int $intervalDays, \DateTimeImmutable $now): bool
    {
        if (null === $lastSentAt || $lastSentAt < 1) {
            return true;
        }

        $lastSentDay = self::startOfDay($now->setTimestamp($lastSentAt));
        $nextDueDay = $lastSentDay->modify(\sprintf('+%d days', max(1, $intervalDays)));

        return $now >= $nextDueDay;
    }

    private static function startOfDay(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTime(0, 0);
    }
}
