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
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CalendarRunner;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Candidate;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CandidateProvider;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\EventPublisher;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\PublishResult;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\SkippedEvent;
use Markocupic\SacEventToolBundle\Util\ReleaseLevel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CalendarRunnerTest extends TestCase
{
    private const DUE_DATE = 1796137200;

    /**
     * @var list<array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    private array $updates = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->updates = [];
    }

    public function testFindDueCalendarIdsPassesTheCurrentTimeAndReturnsIntegers(): void
    {
        $now = new \DateTimeImmutable('2026-12-01 18:10');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('autoPublishEvents = 1'),
                    $this->stringContains('autoPublishEventsDate <= ?'),
                    $this->stringContains('autoPublishEventsExecutedForDate <> autoPublishEventsDate'),
                ),
                [$now->getTimestamp()],
            )
            ->willReturn(['3', '7'])
        ;

        $runner = new CalendarRunner($connection, $this->createMock(CandidateProvider::class), $this->createMock(EventPublisher::class));

        $this->assertSame([3, 7], $runner->findDueCalendarIds($now));
    }

    public function testFindUpcomingCalendarIdsListsFutureDueDatesSortedByDate(): void
    {
        $now = new \DateTimeImmutable('2026-10-03 19:45');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('autoPublishEvents = 1'),
                    $this->stringContains('autoPublishEventsDate > ?'),
                    $this->stringContains('ORDER BY autoPublishEventsDate'),
                ),
                [$now->getTimestamp()],
            )
            ->willReturn(['12', '4'])
        ;

        $runner = new CalendarRunner($connection, $this->createMock(CandidateProvider::class), $this->createMock(EventPublisher::class));

        $this->assertSame([12, 4], $runner->findUpcomingCalendarIds($now));
    }

    public function testPublishesTheCandidatesAndMarksTheRunAsExecuted(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(2))
            ->method('info')
        ;

        $publisher = $this->createMock(EventPublisher::class);
        $publisher
            ->expects($this->exactly(2))
            ->method('publish')
            ->willReturn(true)
        ;

        $runner = new CalendarRunner($this->createConnection(), $this->createCandidateProvider([1, 2]), $publisher, $logger);
        $result = $runner->run(7);

        $this->assertSame([1, 2], array_map(static fn (Candidate $c): int => $c->eventId, $result->getPublished()));
        $this->assertSame('Touren (2027)', $result->calendarTitle);
        $this->assertSame(self::DUE_DATE, $result->dueDate);

        $this->assertCount(1, $this->updates);
        [$table, $data, $criteria] = $this->updates[0];
        $this->assertSame('tl_calendar', $table);
        $this->assertSame(self::DUE_DATE, $data['autoPublishEventsExecutedForDate']);
        $this->assertGreaterThan(0, $data['autoPublishEventsExecutedAt']);
        $this->assertSame(['id' => 7], $criteria);
    }

    public function testRunIsMarkedAsExecutedEvenWithoutCandidates(): void
    {
        $runner = new CalendarRunner($this->createConnection(), $this->createCandidateProvider([]), $this->createMock(EventPublisher::class));
        $runner->run(7);

        $this->assertCount(1, $this->updates);
    }

    public function testDryRunChangesNothing(): void
    {
        $publisher = $this->createMock(EventPublisher::class);
        $publisher
            ->expects($this->never())
            ->method('publish')
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method($this->anything())
        ;

        $runner = new CalendarRunner($this->createConnection(), $this->createCandidateProvider([1, 2]), $publisher, $logger);
        $result = $runner->run(7, true);

        $this->assertTrue($result->dryRun);
        $this->assertCount(2, $result->getPublished());
        $this->assertSame([], $this->updates, 'The run must not be marked as executed');
    }

    public function testEventChangedInTheMeantimeIsSkipped(): void
    {
        $publisher = $this->createMock(EventPublisher::class);
        $publisher
            ->method('publish')
            ->willReturn(false)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
        ;

        $result = (new CalendarRunner($this->createConnection(), $this->createCandidateProvider([1]), $publisher, $logger))->run(7);

        $this->assertSame([], $result->getPublished());
        $this->assertSame(SkippedEvent::REASON_CHANGED_MEANWHILE, $result->getSkipped()[0]->reason);
    }

    public function testErrorInOneEventDoesNotStopTheRun(): void
    {
        $publisher = $this->createMock(EventPublisher::class);
        $publisher
            ->method('publish')
            ->willReturnCallback(
                static function (Candidate $candidate): bool {
                    if (1 === $candidate->eventId) {
                        throw new \RuntimeException('Database is gone');
                    }

                    return true;
                },
            )
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Database is gone'))
        ;

        $result = (new CalendarRunner($this->createConnection(), $this->createCandidateProvider([1, 2]), $publisher, $logger))->run(7);

        $this->assertSame([2], array_map(static fn (Candidate $c): int => $c->eventId, $result->getPublished()));
        $this->assertSame(1, $result->countErrors());
        $this->assertCount(1, $this->updates, 'The run is marked as executed anyway');
    }

    public function testSkippedEventsOfTheCandidateProviderAreLogged(): void
    {
        $candidateProvider = $this->createMock(CandidateProvider::class);
        $candidateProvider
            ->method('findCandidates')
            ->willReturnCallback(
                static function (array $calendar, PublishResult $result): array {
                    $result->addSkipped(new SkippedEvent(3, 'Event 3', SkippedEvent::REASON_OUTSIDE_VALID_TIME_PERIOD));

                    return [];
                },
            )
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('outside the valid time period'))
        ;

        (new CalendarRunner($this->createConnection(), $candidateProvider, $this->createMock(EventPublisher::class), $logger))->run(7);
    }

    public function testUnknownCalendarThrowsAnException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(false)
        ;

        $this->expectException(\InvalidArgumentException::class);

        (new CalendarRunner($connection, $this->createMock(CandidateProvider::class), $this->createMock(EventPublisher::class)))->run(99);
    }

    private function createConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn([
                'id' => '7',
                'title' => 'Touren &#40;2027&#41;',
                'autoPublishEventsDate' => (string) self::DUE_DATE,
                'enableEventStartDateValidation' => '0',
                'validTimePeriodStart' => '',
                'validTimePeriodStop' => '',
            ])
        ;

        $connection
            ->method('update')
            ->willReturnCallback(
                function (string $table, array $data, array $criteria): int {
                    $this->updates[] = [$table, $data, $criteria];

                    return 1;
                },
            )
        ;

        return $connection;
    }

    /**
     * @param list<int> $eventIds
     */
    private function createCandidateProvider(array $eventIds): CandidateProvider
    {
        $candidates = array_map(
            static fn (int $id): Candidate => new Candidate($id, 7, 'Event '.$id, new ReleaseLevel(13, 3, 'FS 3'), new ReleaseLevel(14, 4, 'FS 4')),
            $eventIds,
        );

        $candidateProvider = $this->createMock(CandidateProvider::class);
        $candidateProvider
            ->method('findCandidates')
            ->willReturn($candidates)
        ;

        return $candidateProvider;
    }
}
