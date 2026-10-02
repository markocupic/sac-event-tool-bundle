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

namespace Markocupic\SacEventToolBundle\Tests\Feature\InstructorPostEventTaskReminder;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\OpenTask;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\OpenTaskProvider;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\TaskEvaluator;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\TaskItem;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the assignment of open tasks to recipients.
 * The SQL filters of findDueEventIds() (published, canceled/rescheduled, completion period, lookback)
 * need a database and are not covered here; the date boundaries are tested in ReminderScheduleTest.
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

    /**
     * @param array<int, array<string, mixed>> $events      eventId => properties
     * @param array<int, list<int>>            $instructors eventId => instructor user IDs
     * @param list<int>|null                   $eventsWithOpenTasks
     */
    private function createProvider(array $events, array $instructors, array|null $eventsWithOpenTasks = null): OpenTaskProvider&MockObject
    {
        $eventAdapter = $this->mockAdapter(['findById']);
        $eventAdapter
            ->method('findById')
            ->willReturnCallback(
                fn (int $id): CalendarEventsModel => $this->mockClassWithProperties(CalendarEventsModel::class, array_merge([
                    'id' => $id,
                    'title' => 'Event '.$id,
                    'eventType' => 'tour',
                    'endDate' => 1759000000 + $id,
                    'registrationGoesTo' => 0,
                ], $events[$id])),
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
            ->onlyMethods(['findDueEventIds'])
            ->getMock()
        ;

        $provider
            ->method('findDueEventIds')
            ->willReturn(array_keys($events))
        ;

        return $provider;
    }

    private function createCalendar(): CalendarModel
    {
        return $this->mockClassWithProperties(CalendarModel::class, [
            'id' => 7,
            'instructorPostEventTaskReminderFirstOffset' => 7,
            'instructorPostEventTaskReminderLookback' => 365,
        ]);
    }
}
