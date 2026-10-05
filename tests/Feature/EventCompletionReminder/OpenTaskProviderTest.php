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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventCompletionReminder;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\OpenTask;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\OpenTaskProvider;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\TaskEvaluator;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\TaskItem;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the assignment of open tasks to recipients.
 * The SQL query of fetchCandidateEvents() needs a database and is not covered here.
 * The due check in PHP (incl. rescheduled events) is covered via findDueEvents();
 * the date boundaries are tested in ReminderScheduleTest.
 */
final class OpenTaskProviderTest extends ContaoTestCase
{
    private const ANNA = 1; // main instructor

    private const BEAT = 2; // assistant instructor

    private const CORA = 3; // registration coordinator

    private const DORA = 4; // disabled user

    private const EMIL = 5; // user without email

    public function testInstructorsGetTheOpenTasks(): void
    {
        $provider = $this->createProvider(
            events: [10 => ['registrationGoesTo' => 0]],
            instructors: [10 => [self::ANNA, self::BEAT]],
        );

        $result = $provider->getOpenTasksByRecipient($this->createCalendar(), new \DateTimeImmutable());

        $this->assertSame([self::ANNA, self::BEAT], array_keys($result));
        $this->assertSame(OpenTask::ROLE_INSTRUCTOR, $result[self::ANNA][0]->role);
        $this->assertSame(10, $result[self::BEAT][0]->eventId);
        $this->assertSame('tour_report', $result[self::BEAT][0]->tasks[0]->name);
    }

    public function testRegistrationCoordinatorWithoutInstructorRoleIsNotified(): void
    {
        $provider = $this->createProvider(
            events: [10 => ['registrationGoesTo' => self::CORA]],
            instructors: [10 => [self::ANNA]],
        );

        $result = $provider->getOpenTasksByRecipient($this->createCalendar(), new \DateTimeImmutable());

        $this->assertSame([self::ANNA, self::CORA], array_keys($result));
        $this->assertSame(OpenTask::ROLE_REGISTRATION_COORDINATOR, $result[self::CORA][0]->role);
        $this->assertSame('tour_report', $result[self::CORA][0]->tasks[0]->name, 'The coordinator sees the same tasks as the instructors');
    }

    public function testCoordinatorWhoIsAlsoInstructorGetsTheEventOnlyOnce(): void
    {
        $provider = $this->createProvider(
            events: [10 => ['registrationGoesTo' => self::ANNA]],
            instructors: [10 => [self::ANNA]],
        );

        $result = $provider->getOpenTasksByRecipient($this->createCalendar(), new \DateTimeImmutable());

        $this->assertCount(1, $result[self::ANNA]);
        $this->assertSame(OpenTask::ROLE_INSTRUCTOR, $result[self::ANNA][0]->role);
    }

    public function testDisabledUsersAndUsersWithoutEmailAreSkipped(): void
    {
        $provider = $this->createProvider(
            events: [10 => ['registrationGoesTo' => self::DORA]],
            instructors: [10 => [self::ANNA, self::EMIL]],
        );

        $result = $provider->getOpenTasksByRecipient($this->createCalendar(), new \DateTimeImmutable());

        $this->assertSame([self::ANNA], array_keys($result));
    }

    public function testEventsWithoutOpenTasksAreSkipped(): void
    {
        $provider = $this->createProvider(
            events: [10 => [], 11 => []],
            instructors: [10 => [self::ANNA], 11 => [self::ANNA]],
            eventsWithOpenTasks: [11],
        );

        $result = $provider->getOpenTasksByRecipient($this->createCalendar(), new \DateTimeImmutable());

        $this->assertCount(1, $result[self::ANNA]);
        $this->assertSame(11, $result[self::ANNA][0]->eventId);
    }

    public function testCollectsAllEventsOfTheCalendarForOneRecipient(): void
    {
        $provider = $this->createProvider(
            events: [10 => [], 11 => ['registrationGoesTo' => self::ANNA]],
            instructors: [10 => [self::ANNA], 11 => [self::BEAT]],
        );

        $result = $provider->getOpenTasks(self::ANNA, $this->createCalendar(), new \DateTimeImmutable());

        $this->assertSame([10, 11], array_map(static fn (OpenTask $t): int => $t->eventId, $result));
        $this->assertSame([OpenTask::ROLE_INSTRUCTOR, OpenTask::ROLE_REGISTRATION_COORDINATOR], array_map(static fn (OpenTask $t): string => $t->role, $result));
    }

    public function testRescheduledEventIsCheckedWithShiftedEndDate(): void
    {
        $now = new \DateTimeImmutable('2026-02-20 03:45');

        $provider = $this->createProvider(
            events: [
                // Rescheduled from 10./11.01. to 24.01. → new end 25.01., due on 01.02. (completion period 7 days)
                10 => ['startDate' => strtotime('2026-01-10'), 'endDate' => strtotime('2026-01-11'), 'eventState' => 'event_rescheduled', 'rescheduledEventDate' => strtotime('2026-01-24')],
                // Rescheduled to 14./15.02. → new end 15.02., not yet due on 20.02.
                11 => ['startDate' => strtotime('2026-01-10'), 'endDate' => strtotime('2026-01-11'), 'eventState' => 'event_rescheduled', 'rescheduledEventDate' => strtotime('2026-02-14')],
                // Rescheduled, but no new date yet → not checked
                12 => ['startDate' => strtotime('2026-01-10'), 'endDate' => strtotime('2026-01-11'), 'eventState' => 'event_rescheduled', 'rescheduledEventDate' => null],
            ],
            instructors: [10 => [self::ANNA], 11 => [self::ANNA], 12 => [self::ANNA]],
        );

        $result = $provider->getOpenTasks(self::ANNA, $this->createCalendar(), $now);

        $this->assertSame([10], array_map(static fn (OpenTask $t): int => $t->eventId, $result));
        $this->assertSame(strtotime('2026-01-25'), $result[0]->endDate, 'The notification shows the shifted end date');
    }

    public function testEventsAreOrderedByEffectiveEndDate(): void
    {
        $provider = $this->createProvider(
            events: [
                10 => ['endDate' => strtotime('2026-01-20')],
                11 => ['startDate' => strtotime('2025-12-01'), 'endDate' => strtotime('2025-12-01'), 'eventState' => 'event_rescheduled', 'rescheduledEventDate' => strtotime('2026-01-05')],
            ],
            instructors: [10 => [self::ANNA], 11 => [self::ANNA]],
        );

        $result = $provider->getOpenTasks(self::ANNA, $this->createCalendar(), new \DateTimeImmutable('2026-02-20 03:45'));

        $this->assertSame([11, 10], array_map(static fn (OpenTask $t): int => $t->eventId, $result));
    }

    public function testNothingIsCheckedWithoutSelectedEventTypes(): void
    {
        $provider = $this->createProvider(
            events: [10 => []],
            instructors: [10 => [self::ANNA]],
            expectedEventTypes: null,
        );

        $calendar = $this->createCalendar(['eventCompletionReminderEventTypes' => null]);

        $this->assertSame([], $provider->getOpenTasksByRecipient($calendar, new \DateTimeImmutable()));
    }

    public function testSelectedEventTypesArePassedToTheQuery(): void
    {
        $provider = $this->createProvider(
            events: [10 => []],
            instructors: [10 => [self::ANNA]],
            expectedEventTypes: ['course', 'generalEvent'],
        );

        $calendar = $this->createCalendar(['eventCompletionReminderEventTypes' => serialize(['course', 'generalEvent'])]);

        $this->assertSame([self::ANNA], array_keys($provider->getOpenTasksByRecipient($calendar, new \DateTimeImmutable())));
    }

    /**
     * @param array<int, array<string, mixed>> $events              eventId => properties
     * @param array<int, list<int>>            $instructors         eventId => instructor user IDs
     * @param list<int>|null                   $eventsWithOpenTasks
     * @param list<string>|null                $expectedEventTypes  event types expected in the query; null: the query must not run
     */
    private function createProvider(array $events, array $instructors, array|null $eventsWithOpenTasks = null, array|null $expectedEventTypes = ['tour', 'lastMinuteTour', 'course']): OpenTaskProvider&MockObject
    {
        // Database rows: default is a normal event that ended long ago (due)
        $rows = [];

        foreach ($events as $id => $properties) {
            $rows[$id] = array_merge(
                [
                    'id' => $id,
                    'startDate' => 1759000000 + $id,
                    'endDate' => 1759000000 + $id,
                    'eventState' => '',
                    'rescheduledEventDate' => null,
                ],
                array_intersect_key($properties, array_flip(['startDate', 'endDate', 'eventState', 'rescheduledEventDate'])),
            );
        }

        $eventAdapter = $this->mockAdapter(['findById']);
        $eventAdapter
            ->method('findById')
            ->willReturnCallback(
                fn (int $id): CalendarEventsModel => $this->mockClassWithProperties(CalendarEventsModel::class, array_merge([
                    'id' => $id,
                    'title' => 'Event '.$id,
                    'eventType' => 'tour',
                    'registrationGoesTo' => 0,
                ], $rows[$id], $events[$id])),
            )
        ;

        $users = [
            self::ANNA => ['disable' => false, 'email' => 'anna@example.org'],
            self::BEAT => ['disable' => false, 'email' => 'beat@example.org'],
            self::CORA => ['disable' => false, 'email' => 'cora@example.org'],
            self::DORA => ['disable' => true, 'email' => 'dora@example.org'],
            self::EMIL => ['disable' => false, 'email' => ''],
        ];

        $userAdapter = $this->mockAdapter(['findById']);
        $userAdapter
            ->method('findById')
            ->willReturnCallback(
                fn (int $id): UserModel|null => isset($users[$id]) ? $this->mockClassWithProperties(UserModel::class, ['id' => $id] + $users[$id]) : null,
            )
        ;

        $framework = $this->mockContaoFramework([
            CalendarEventsModel::class => $eventAdapter,
            UserModel::class => $userAdapter,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturnCallback(static fn (string $sql, array $params): array => $instructors[$params[0]] ?? [])
        ;

        $eventsWithOpenTasks ??= array_keys($events);

        $taskEvaluator = $this->createMock(TaskEvaluator::class);
        $taskEvaluator
            ->method('getOpenTasks')
            ->willReturnCallback(
                static fn (CalendarEventsModel $event): array => \in_array((int) $event->id, $eventsWithOpenTasks, true)
                    ? [new TaskItem('tour_report', 'Tourenbericht ausfüllen', 'https://example.org/'.$event->id)]
                    : [],
            )
        ;

        $provider = $this->getMockBuilder(OpenTaskProvider::class)
            ->setConstructorArgs([$connection, $framework, $taskEvaluator])
            ->onlyMethods(['fetchCandidateEvents'])
            ->getMock()
        ;

        if (null === $expectedEventTypes) {
            $provider
                ->expects($this->never())
                ->method('fetchCandidateEvents')
            ;
        } else {
            $provider
                ->method('fetchCandidateEvents')
                ->with($this->anything(), $expectedEventTypes, $this->anything())
                ->willReturn(array_values($rows))
            ;
        }

        return $provider;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function createCalendar(array $properties = []): CalendarModel
    {
        return $this->mockClassWithProperties(CalendarModel::class, array_merge([
            'id' => 7,
            'eventCompletionReminderEventTypes' => serialize(['tour', 'lastMinuteTour', 'course']),
            'eventCompletionReminderFirstOffset' => 7,
        ], $properties));
    }
}
