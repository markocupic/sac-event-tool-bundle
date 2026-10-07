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

namespace Markocupic\SacEventToolBundle\Tests\Feature\AutoPublishEvents;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\EventReleaseLevel\EventReleaseLevelPolicyUtil;
use Markocupic\SacEventToolBundle\EventReleaseLevel\ReleaseLevel;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Candidate;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CandidateProvider;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\PublishResult;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\SkippedEvent;
use PHPUnit\Framework\TestCase;

/**
 * Release level systems used in the tests (IDs of tl_event_release_level_policy):
 * - tour: 4 levels (FS 1–4): IDs 11, 12, 13, 14
 * - course: 3 levels with a gap (FS 1, 2, 5): IDs 21, 22, 25
 * - generalEvent: 2 levels (FS 1–2): IDs 31, 32
 * - lastMinuteTour: 1 level: ID 41
 * - unknownType: no release level system.
 */
final class CandidateProviderTest extends TestCase
{
    public function testEventOnTheSecondHighestLevelIsACandidate(): void
    {
        $result = $this->createPublishResult();

        $candidates = $this->createProvider([$this->event(1, 'tour', 13)])->findCandidates($this->calendar(), $result);

        $this->assertCount(1, $candidates);
        $this->assertSame(1, $candidates[0]->eventId);
        $this->assertSame(7, $candidates[0]->calendarId);
        $this->assertSame(13, $candidates[0]->currentLevel->id);
        $this->assertSame(14, $candidates[0]->targetLevel->id);
        $this->assertSame([], $result->getSkipped());
    }

    public function testTitleIsDecoded(): void
    {
        $event = $this->event(1, 'tour', 13);
        $event['title'] = 'Test Tour &#40;Admins&#41; &amp; Co';

        $candidates = $this->createProvider([$event])->findCandidates($this->calendar(), $this->createPublishResult());

        $this->assertSame('Test Tour (Admins) & Co', $candidates[0]->title);
    }

    public function testEventsOnOtherLevelsAreIgnoredSilently(): void
    {
        $result = $this->createPublishResult();

        $candidates = $this->createProvider([
            $this->event(1, 'tour', 11),
            $this->event(2, 'tour', 12),
            // Highest level, but unpublished (manually hidden): not touched
            $this->event(3, 'tour', 14),
        ])->findCandidates($this->calendar(), $result);

        $this->assertSame([], $candidates);
        $this->assertSame([], $result->getSkipped());
    }

    public function testSecondHighestLevelIsDeterminedByOrderNotByLevelNumber(): void
    {
        // course: FS 1, 2, 5 → the second-highest level is FS 2 (ID 22), the highest FS 5 (ID 25)
        $candidates = $this->createProvider([$this->event(1, 'course', 22)])->findCandidates($this->calendar(), $this->createPublishResult());

        $this->assertSame([22], $this->currentLevelIds($candidates));
        $this->assertSame(25, $candidates[0]->targetLevel->id);
    }

    public function testSystemWithTwoLevelsPublishesEventsOnTheInitialLevel(): void
    {
        // Decided on 2026-10-03: systems with two levels are included
        $candidates = $this->createProvider([$this->event(1, 'generalEvent', 31)])->findCandidates($this->calendar(), $this->createPublishResult());

        $this->assertSame([31], $this->currentLevelIds($candidates));
        $this->assertSame(32, $candidates[0]->targetLevel->id);
    }

    public function testSystemWithOneLevelHasNoCandidates(): void
    {
        $result = $this->createPublishResult();

        $candidates = $this->createProvider([$this->event(1, 'lastMinuteTour', 41)])->findCandidates($this->calendar(), $result);

        $this->assertSame([], $candidates);
        $this->assertSame([], $result->getSkipped());
    }

    public function testInvalidReleaseLevelIsSkipped(): void
    {
        $result = $this->createPublishResult();

        $candidates = $this->createProvider([
            // Level of the course system, but the event is a tour (event type changed)
            $this->event(1, 'tour', 22),
            // Event type without release level system
            $this->event(2, 'unknownType', 13),
        ])->findCandidates($this->calendar(), $result);

        $this->assertSame([], $candidates);
        $this->assertSame([1, 2], array_map(static fn (SkippedEvent $s): int => $s->eventId, $result->getSkipped()));
        $this->assertSame(SkippedEvent::REASON_INVALID_RELEASE_LEVEL, $result->getSkipped()[0]->reason);
    }

    public function testCalendarWithMixedReleaseLevelSystems(): void
    {
        $candidates = $this->createProvider([
            $this->event(1, 'tour', 13),
            $this->event(2, 'course', 22),
            $this->event(3, 'generalEvent', 31),
            $this->event(4, 'tour', 12),
            $this->event(5, 'course', 21),
        ])->findCandidates($this->calendar(), $this->createPublishResult());

        $this->assertSame([1, 2, 3], array_map(static fn (Candidate $c): int => $c->eventId, $candidates));
    }

    public function testReleaseLevelsAreLoadedOncePerEventType(): void
    {
        $eventReleaseLevelPolicyUtil = $this->createEventReleaseLevelPolicyUtil();
        $eventReleaseLevelPolicyUtil
            ->expects($this->exactly(2))
            ->method('getLevelsByEventType')
        ;

        $provider = new CandidateProvider($this->createConnection([
            $this->event(1, 'tour', 13),
            $this->event(2, 'tour', 13),
            $this->event(3, 'course', 22),
        ]), $eventReleaseLevelPolicyUtil);

        $this->assertCount(3, $provider->findCandidates($this->calendar(), $this->createPublishResult()));
    }

    public function testEventsOutsideTheValidTimePeriodAreSkipped(): void
    {
        $result = $this->createPublishResult();

        $calendar = $this->calendar([
            'enableEventStartDateValidation' => '1',
            'validTimePeriodStart' => (string) strtotime('2027-01-01'),
            'validTimePeriodStop' => (string) strtotime('2027-12-31'),
        ]);

        $candidates = $this->createProvider([
            $this->event(1, 'tour', 13, strtotime('2026-12-31')),
            $this->event(2, 'tour', 13, strtotime('2027-01-01')),
            $this->event(3, 'tour', 13, strtotime('2027-12-31')),
            $this->event(4, 'tour', 13, strtotime('2028-01-01')),
            // Not on the second-highest level: ignored without log entry, even outside the time period
            $this->event(5, 'tour', 12, strtotime('2028-01-01')),
        ])->findCandidates($calendar, $result);

        $this->assertSame([2, 3], array_map(static fn (Candidate $c): int => $c->eventId, $candidates));
        $this->assertSame([1, 4], array_map(static fn (SkippedEvent $s): int => $s->eventId, $result->getSkipped()));
        $this->assertSame(SkippedEvent::REASON_OUTSIDE_VALID_TIME_PERIOD, $result->getSkipped()[0]->reason);
    }

    public function testValidTimePeriodIsIgnoredIfTheValidationIsDisabled(): void
    {
        $calendar = $this->calendar([
            'enableEventStartDateValidation' => '',
            'validTimePeriodStart' => (string) strtotime('2027-01-01'),
            'validTimePeriodStop' => (string) strtotime('2027-12-31'),
        ]);

        $candidates = $this->createProvider([$this->event(1, 'tour', 13, strtotime('2020-05-01'))])->findCandidates($calendar, $this->createPublishResult());

        $this->assertCount(1, $candidates);
    }

    public function testQueryOnlyFetchesUnpublishedEventsWithReleaseLevelOfTheCalendar(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('pid = ?'),
                    $this->stringContains('published = 0'),
                    $this->stringContains('eventReleaseLevel > 0'),
                    $this->logicalNot($this->stringContains('eventState')),
                    $this->logicalNot($this->stringContains('endDate')),
                ),
                [7],
            )
            ->willReturn([])
        ;

        (new CandidateProvider($connection, $this->createEventReleaseLevelPolicyUtil()))->findCandidates($this->calendar(), $this->createPublishResult());
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private function createProvider(array $events): CandidateProvider
    {
        return new CandidateProvider($this->createConnection($events), $this->createEventReleaseLevelPolicyUtil());
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private function createConnection(array $events): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn($events)
        ;

        return $connection;
    }

    private function createEventReleaseLevelPolicyUtil(): EventReleaseLevelPolicyUtil
    {
        $systems = [
            'tour' => [[14, 4], [13, 3], [12, 2], [11, 1]],
            'course' => [[25, 5], [22, 2], [21, 1]],
            'generalEvent' => [[32, 2], [31, 1]],
            'lastMinuteTour' => [[41, 1]],
        ];

        $eventReleaseLevelPolicyUtil = $this->createMock(EventReleaseLevelPolicyUtil::class);
        $eventReleaseLevelPolicyUtil
            ->method('getLevelsByEventType')
            ->willReturnCallback(
                static fn (string $eventType): array => array_map(
                    static fn (array $level): ReleaseLevel => new ReleaseLevel($level[0], $level[1], 'FS '.$level[1]),
                    $systems[$eventType] ?? [],
                ),
            )
        ;

        return $eventReleaseLevelPolicyUtil;
    }

    /**
     * Rows as returned by Doctrine (strings).
     *
     * @return array<string, mixed>
     */
    private function event(int $id, string $eventType, int $releaseLevelId, int|null $startDate = null): array
    {
        return [
            'id' => (string) $id,
            'title' => 'Event '.$id,
            'eventType' => $eventType,
            'eventReleaseLevel' => (string) $releaseLevelId,
            'startDate' => (string) ($startDate ?? strtotime('2027-06-01')),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function calendar(array $properties = []): array
    {
        return array_merge(
            [
                'id' => '7',
                'title' => 'Touren 2027',
                'autoPublishEventsDate' => (string) strtotime('2026-12-01 18:00'),
                'enableEventStartDateValidation' => '',
                'validTimePeriodStart' => '',
                'validTimePeriodStop' => '',
            ],
            $properties,
        );
    }

    private function createPublishResult(): PublishResult
    {
        return new PublishResult(7, 'Touren 2027');
    }

    /**
     * @param list<Candidate> $candidates
     *
     * @return list<int>
     */
    private function currentLevelIds(array $candidates): array
    {
        return array_map(static fn (Candidate $c): int => $c->currentLevel->id, $candidates);
    }
}
