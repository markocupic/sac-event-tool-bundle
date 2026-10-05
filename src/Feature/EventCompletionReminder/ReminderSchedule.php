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

namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder;

use Markocupic\SacEventToolBundle\Config\EventState;

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
     * its completion period of $firstOffsetDays days after the end day has expired.
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
     * Start date shown in the notification:
     * - normal events: tl_calendar_events.startDate
     * - rescheduled events with a new date: tl_calendar_events.rescheduledEventDate (the new start day)
     */
    public static function getEffectiveStartDate(int $startDate, string $eventState, int|null $rescheduledEventDate): int
    {
        if (EventState::STATE_RESCHEDULED === $eventState && null !== $rescheduledEventDate && $rescheduledEventDate > 0) {
            return $rescheduledEventDate;
        }

        return $startDate;
    }

    /**
     * End date that counts for the reminder:
     * - normal events: tl_calendar_events.endDate
     * - rescheduled events: the shifted end date (see getRescheduledEndDate()),
     *   or null if no new date (rescheduledEventDate) has been entered yet
     *
     * Returns null if the event has no valid end date and must not be checked.
     */
    public static function getEffectiveEndDate(int $startDate, int $endDate, string $eventState, int|null $rescheduledEventDate): int|null
    {
        if (EventState::STATE_RESCHEDULED !== $eventState) {
            return $endDate > 0 ? $endDate : null;
        }

        if (null === $rescheduledEventDate || $rescheduledEventDate < 1) {
            return null;
        }

        return self::getRescheduledEndDate($startDate, $endDate, $rescheduledEventDate);
    }

    /**
     * End date of a rescheduled event.
     *
     * tl_calendar_events.rescheduledEventDate is the new START day. The original duration
     * (in calendar days between the start day and the end day) is added to it:
     * Sa 10.01. - So 11.01. rescheduled to 24.01. → new end 25.01.
     * Two weekends 10.01. - 18.01. rescheduled to 07.02. → new end 15.02.
     * One-day events keep the rescheduled day as end day.
     *
     * Counted in calendar days, so daylight saving time changes do not shift the result.
     * Returns the start of the new end day (00:00).
     */
    public static function getRescheduledEndDate(int $startDate, int $endDate, int $rescheduledStartDate): int
    {
        $durationDays = 0;

        if ($startDate > 0 && $endDate > $startDate) {
            $startDay = self::startOfDay((new \DateTimeImmutable())->setTimestamp($startDate));
            $endDay = self::startOfDay((new \DateTimeImmutable())->setTimestamp($endDate));
            $durationDays = (int) $startDay->diff($endDay)->days;
        }

        return self::startOfDay((new \DateTimeImmutable())->setTimestamp($rescheduledStartDate))
            ->modify(\sprintf('+%d days', $durationDays))
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
