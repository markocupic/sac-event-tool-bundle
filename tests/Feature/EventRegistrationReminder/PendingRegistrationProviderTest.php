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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventRegistrationReminder;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\PendingEvent;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\PendingRegistrationProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * The SQL queries (fetchUpcomingEvents(), fetchUnconfirmedRegistrations()) are mocked;
 * tested is the assignment to the recipients and the split into overdue and recent registrations.
 */
final class PendingRegistrationProviderTest extends ContaoTestCase
{
    private const ANNA = 1;

    private const BEAT = 2;

    private const DISABLED = 3;

    public function testAssignsEventsToRegistrationCoordinatorOrMainInstructor(): void
    {
        $now = strtotime('2026-10-08 04:30');

        $provider = $this->createProvider(
            events: [
                // Coordinator set: goes to the coordinator
                ['id' => 10, 'title' => 'Skitour', 'eventType' => 'tour', 'registrationGoesTo' => self::BEAT, 'mainInstructorId' => self::ANNA],
                // No coordinator: goes to the main instructor
                ['id' => 11, 'title' => 'Kletterkurs', 'eventType' => 'course', 'registrationGoesTo' => 0, 'mainInstructorId' => self::ANNA],
                // Recipient disabled: skipped
                ['id' => 12, 'title' => 'Hochtour', 'eventType' => 'tour', 'registrationGoesTo' => self::DISABLED, 'mainInstructorId' => self::ANNA],
            ],
            registrations: [
                10 => [$this->registration('2026-09-20')],
                11 => [$this->registration('2026-09-20')],
                12 => [$this->registration('2026-09-20')],
            ],
        );

        $result = $provider->getPendingEventsByRecipient($this->createCalendar(), $now);

        $this->assertSame([self::BEAT => [10], self::ANNA => [11]], array_map(static fn (array $events): array => array_map(static fn (PendingEvent $e): int => $e->eventId, $events), $result));
    }

    public function testOnlyOverdueRegistrationsTriggerTheReminder(): void
    {
        $now = strtotime('2026-10-08 04:30');

        $provider = $this->createProvider(
            events: [
                ['id' => 10, 'title' => 'Touren &amp; Kurse', 'eventType' => 'tour', 'registrationGoesTo' => 0, 'mainInstructorId' => self::ANNA],
                ['id' => 11, 'title' => 'Kletterkurs', 'eventType' => 'course', 'registrationGoesTo' => 0, 'mainInstructorId' => self::ANNA],
            ],
            registrations: [
                // 8 days (overdue) and 2 days (recent)
                10 => [$this->registration('2026-09-30 04:30', 'Heidi'), $this->registration('2026-10-06 04:30', 'Fritz')],
                // Only recent registrations: no reminder for this event
                11 => [$this->registration('2026-10-06 04:30')],
            ],
        );

        $events = $provider->getPendingEvents(self::ANNA, $this->createCalendar(), $now);

        $this->assertCount(1, $events);
        $this->assertSame('Touren & Kurse', $events[0]->title);
        $this->assertSame(['Heidi'], array_map(static fn ($r): string => $r->firstname, $events[0]->overdueRegistrations));
        $this->assertSame(8, $events[0]->overdueRegistrations[0]->daysRegistered);
        $this->assertSame(['Fritz'], array_map(static fn ($r): string => $r->firstname, $events[0]->recentRegistrations));
        $this->assertSame(2, $events[0]->recentRegistrations[0]->daysRegistered);
    }

    public function testNothingIfFirstReminderAfterIsNotSet(): void
    {
        $provider = $this->createProvider(events: [], registrations: []);

        $this->assertSame([], $provider->getPendingEventsByRecipient($this->createCalendar(0), time()));
    }

    private function createCalendar(int $sendFirstReminderAfter = 7): CalendarModel
    {
        return $this->mockClassWithProperties(CalendarModel::class, [
            'id' => 7,
            'sendFirstReminderAfter' => $sendFirstReminderAfter,
            'sendReminderEach' => 7,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function registration(string $dateAdded, string $firstname = 'Heidi'): array
    {
        return ['firstname' => $firstname, 'lastname' => 'Muster', 'gender' => 'female', 'sacMemberId' => 123456, 'dateAdded' => strtotime($dateAdded)];
    }

    /**
     * @param list<array<string, mixed>>             $events
     * @param array<int, list<array<string, mixed>>> $registrations
     */
    private function createProvider(array $events, array $registrations): PendingRegistrationProvider&MockObject
    {
        $provider = $this->getMockBuilder(PendingRegistrationProvider::class)
            ->setConstructorArgs([$this->createMock(ContaoFramework::class), $this->createMock(Connection::class)])
            ->onlyMethods(['fetchUpcomingEvents', 'fetchUnconfirmedRegistrations', 'getRecipient'])
            ->getMock()
        ;

        $provider
            ->method('fetchUpcomingEvents')
            ->willReturn($events)
        ;

        $provider
            ->method('fetchUnconfirmedRegistrations')
            ->willReturnCallback(static fn (int $eventId): array => $registrations[$eventId] ?? [])
        ;

        $provider
            ->method('getRecipient')
            ->willReturnCallback(fn (int $userId): UserModel|null => self::DISABLED === $userId ? null : $this->mockClassWithProperties(UserModel::class, ['id' => $userId]))
        ;

        return $provider;
    }
}
