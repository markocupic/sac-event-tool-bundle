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

namespace Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory;

use Contao\CalendarEventsModel;
use Markocupic\SacEventToolBundle\Util\EventDateUtil;

/**
 * The history can be viewed until ACCESS_DAYS_AFTER_EVENT_END days after the end of the
 * event (until 23:59:59 of that day). Before the event there is no limit. Admins are not
 * bound to the access period (see ParticipantEventHistoryVoter).
 *
 * Rescheduled events: the access period starts at the shifted end date (see
 * EventDateUtil::getEffectiveEndDate()). The period is calculated on every access and never
 * stored, so a rescheduled event is accessible again even if the period of the original
 * date has already expired.
 */
final readonly class ParticipantEventHistoryAccessPeriod
{
    /**
     * Days after the end of the event; 0 = unlimited.
     */
    public const int ACCESS_DAYS_AFTER_EVENT_END = 30;

    /**
     * Last second (timestamp) the history may be viewed, or null if unlimited:
     * - ACCESS_DAYS_AFTER_EVENT_END is 0
     * - the event has no end date
     * - the event has been rescheduled, but no new date has been entered yet
     */
    public static function getAccessEnd(int $startDate, int $endDate, string $eventState, int|null $rescheduledEventDate, int $days = self::ACCESS_DAYS_AFTER_EVENT_END): int|null
    {
        if ($days < 1) {
            return null;
        }

        $effectiveEndDate = EventDateUtil::getEffectiveEndDate($startDate, $endDate, $eventState, $rescheduledEventDate);

        if (null === $effectiveEndDate) {
            return null;
        }

        return (new \DateTimeImmutable())
            ->setTimestamp($effectiveEndDate)
            ->setTime(0, 0)
            ->modify(\sprintf('+%d days', $days + 1))
            ->getTimestamp() - 1
        ;
    }

    public static function getAccessEndOfEvent(CalendarEventsModel $event): int|null
    {
        return self::getAccessEnd(
            (int) $event->startDate,
            (int) $event->endDate,
            (string) $event->eventState,
            $event->rescheduledEventDate ? (int) $event->rescheduledEventDate : null,
        );
    }

    public static function isOpen(CalendarEventsModel $event, \DateTimeImmutable $now): bool
    {
        $accessEnd = self::getAccessEndOfEvent($event);

        return null === $accessEnd || $now->getTimestamp() <= $accessEnd;
    }
}
