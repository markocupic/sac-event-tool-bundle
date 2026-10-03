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
use Psr\Log\LoggerInterface;

/**
 * Runs the auto publish for one calendar. Used by the cron and the console command.
 *
 * The run happens once per due date: after the run, autoPublishEventsExecutedForDate is set to the
 * due date. If the due date is changed in the back end, the calendar is due again.
 */
class CalendarRunner
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CandidateProvider $candidateProvider,
        private readonly EventPublisher $eventPublisher,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * Calendars with the feature enabled, the due date reached and no run for this due date yet.
     *
     * @return list<int>
     */
    public function findDueCalendarIds(\DateTimeImmutable $now): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_calendar
            WHERE autoPublishEvents = 1
                AND autoPublishEventsDate IS NOT NULL
                AND autoPublishEventsDate <= ?
                AND (autoPublishEventsExecutedForDate IS NULL OR autoPublishEventsExecutedForDate <> autoPublishEventsDate)
            ORDER BY id',
            [$now->getTimestamp()],
        );

        return array_map(intval(...), $ids);
    }

    /**
     * Calendars with the feature enabled and a due date in the future, sorted by due date.
     * Also includes calendars whose due date has just been reached but whose run is still pending (next cron run).
     * Used for the preview (AutoPublishEventsPreviewCommand).
     *
     * @return list<int>
     */
    public function findUpcomingCalendarIds(\DateTimeImmutable $now): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_calendar
            WHERE autoPublishEvents = 1
                AND autoPublishEventsDate IS NOT NULL
                AND (
                    autoPublishEventsDate > ?
                    OR autoPublishEventsExecutedForDate IS NULL
                    OR autoPublishEventsExecutedForDate <> autoPublishEventsDate
                )
            ORDER BY autoPublishEventsDate, id',
            [$now->getTimestamp()],
        );

        return array_map(intval(...), $ids);
    }

    /**
     * Publishes the candidates of the calendar and marks the run as executed.
     * A dry run only determines the candidates: nothing is changed, nothing is logged.
     */
    public function run(int $calendarId, bool $dryRun = false): PublishResult
    {
        $calendar = $this->connection->fetchAssociative(
            'SELECT id, title, autoPublishEventsDate, enableEventStartDateValidation, validTimePeriodStart, validTimePeriodStop FROM tl_calendar WHERE id = ?',
            [$calendarId],
        );

        if (false === $calendar) {
            throw new \InvalidArgumentException(\sprintf('Calendar with ID %d not found.', $calendarId));
        }

        // Titles are stored input-encoded (e.g. &#40; for an opening bracket)
        $result = new PublishResult($calendarId, StringUtil::revertInputEncoding((string) $calendar['title']), $dryRun, (int) $calendar['autoPublishEventsDate']);

        foreach ($this->candidateProvider->findCandidates($calendar, $result) as $candidate) {
            if ($dryRun) {
                $result->addPublished($candidate);

                continue;
            }

            // An error in one event must not stop the run
            try {
                if ($this->eventPublisher->publish($candidate)) {
                    $result->addPublished($candidate);
                    $this->logPublished($candidate);
                } else {
                    $result->addSkipped(new SkippedEvent($candidate->eventId, $candidate->title, SkippedEvent::REASON_CHANGED_MEANWHILE));
                }
            } catch (\Throwable $e) {
                $result->addSkipped(new SkippedEvent($candidate->eventId, $candidate->title, SkippedEvent::REASON_ERROR, $e->getMessage()));
            }
        }

        if ($dryRun) {
            return $result;
        }

        foreach ($result->getSkipped() as $skippedEvent) {
            $this->logSkipped($result, $skippedEvent);
        }

        $this->markAsExecuted($calendarId, (int) $calendar['autoPublishEventsDate']);

        return $result;
    }

    public static function describeReason(SkippedEvent $skippedEvent): string
    {
        return match ($skippedEvent->reason) {
            SkippedEvent::REASON_INVALID_RELEASE_LEVEL => 'The release level does not belong to the release level system of the event type.',
            SkippedEvent::REASON_OUTSIDE_VALID_TIME_PERIOD => 'The start date is outside the valid time period of the calendar.',
            SkippedEvent::REASON_CHANGED_MEANWHILE => 'The event has been changed in the meantime.',
            SkippedEvent::REASON_ERROR => 'Error: '.$skippedEvent->detail,
            default => $skippedEvent->reason,
        };
    }

    private function markAsExecuted(int $calendarId, int $dueDate): void
    {
        $this->connection->update(
            'tl_calendar',
            [
                'autoPublishEventsExecutedAt' => time(),
                'autoPublishEventsExecutedForDate' => $dueDate,
            ],
            ['id' => $calendarId],
        );
    }

    private function logPublished(Candidate $candidate): void
    {
        $this->contaoGeneralLogger?->info(\sprintf(
            'Auto publish events: Event "%s" (ID %d) has been upgraded from release level %d ("%s") to %d ("%s") and published.',
            $candidate->title,
            $candidate->eventId,
            $candidate->currentLevel->level,
            $candidate->currentLevel->title,
            $candidate->targetLevel->level,
            $candidate->targetLevel->title,
        ));
    }

    private function logSkipped(PublishResult $result, SkippedEvent $skippedEvent): void
    {
        $message = \sprintf(
            'Auto publish events: Event "%s" (ID %d) in calendar "%s" (ID %d) has not been published. Reason: %s',
            $skippedEvent->title,
            $skippedEvent->eventId,
            $result->calendarTitle,
            $result->calendarId,
            self::describeReason($skippedEvent),
        );

        if (SkippedEvent::REASON_ERROR === $skippedEvent->reason) {
            $this->contaoGeneralLogger?->error($message);
        } else {
            $this->contaoGeneralLogger?->warning($message);
        }
    }
}
