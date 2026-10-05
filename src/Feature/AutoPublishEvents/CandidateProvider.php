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

namespace Markocupic\SacEventToolBundle\Feature\AutoPublishEvents;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Util\EventReleaseLevelPolicyUtil;
use Markocupic\SacEventToolBundle\Util\ReleaseLevel;

/**
 * Finds the events of a calendar that are promoted to the highest release level and published.
 *
 * Rules (see docs/features/auto-publish-events.md):
 * - unpublished events with a release level (eventReleaseLevel > 0)
 * - the release level belongs to the release level system of the event type, otherwise skipped (logged)
 * - the event is on the second-highest level of its system (systems with two levels included)
 * - if the calendar setting "enableEventStartDateValidation" is set:
 *   the start date is within the valid time period, otherwise skipped (logged)
 * - no filter on event type, end date or event state: past, canceled and rescheduled events are included
 */
class CandidateProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventReleaseLevelPolicyUtil $eventReleaseLevelPolicyUtil,
    ) {
    }

    /**
     * Skipped events are added to $result.
     *
     * @param array<string, mixed> $calendar tl_calendar row (id, enableEventStartDateValidation, validTimePeriodStart, validTimePeriodStop)
     *
     * @return list<Candidate>
     */
    public function findCandidates(array $calendar, PublishResult $result): array
    {
        $calendarId = (int) $calendar['id'];

        $events = $this->connection->fetchAllAssociative(
            'SELECT id, title, eventType, eventReleaseLevel, startDate
            FROM tl_calendar_events
            WHERE pid = ? AND published = 0 AND eventReleaseLevel > 0
            ORDER BY startDate, id',
            [$calendarId],
        );

        // The release levels are loaded once per event type and run
        $levelsByEventType = [];
        $candidates = [];

        foreach ($events as $event) {
            $eventId = (int) $event['id'];
            // Titles are stored input-encoded (e.g. &#40; for an opening bracket)
            $title = StringUtil::revertInputEncoding((string) $event['title']);
            $eventType = (string) $event['eventType'];

            $levelsByEventType[$eventType] ??= $this->eventReleaseLevelPolicyUtil->getLevelsByEventType($eventType);
            $levels = $levelsByEventType[$eventType];

            $currentLevel = $this->findLevel($levels, (int) $event['eventReleaseLevel']);

            if (null === $currentLevel) {
                $result->addSkipped(new SkippedEvent($eventId, $title, SkippedEvent::REASON_INVALID_RELEASE_LEVEL));

                continue;
            }

            // Only events on the second-highest level. $levels is sorted from highest to lowest.
            if (\count($levels) < 2 || $levels[1]->id !== $currentLevel->id) {
                continue;
            }

            if (!$this->isInValidTimePeriod($calendar, (int) $event['startDate'])) {
                $result->addSkipped(new SkippedEvent($eventId, $title, SkippedEvent::REASON_OUTSIDE_VALID_TIME_PERIOD));

                continue;
            }

            $candidates[] = new Candidate($eventId, $calendarId, $title, $currentLevel, $levels[0]);
        }

        return $candidates;
    }

    /**
     * Same rule as EventReleaseLevelTimeRules::getViolation() (StartDateOutsideValidTimePeriod) for non-admins.
     *
     * @param array<string, mixed> $calendar
     */
    public function isInValidTimePeriod(array $calendar, int $startDate): bool
    {
        if (!$calendar['enableEventStartDateValidation']) {
            return true;
        }

        return $startDate >= (int) $calendar['validTimePeriodStart'] && $startDate <= (int) $calendar['validTimePeriodStop'];
    }

    /**
     * @param list<ReleaseLevel> $levels
     */
    private function findLevel(array $levels, int $levelId): ReleaseLevel|null
    {
        foreach ($levels as $level) {
            if ($level->id === $levelId) {
                return $level;
            }
        }

        return null;
    }
}
