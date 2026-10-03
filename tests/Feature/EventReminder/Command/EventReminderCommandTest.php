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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventReminder\Command;

use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Command\EventReminderCommand;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Cron\EventReminderCron;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Messenger\Message\SendEventReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Messenger\MessageHandler\SendEventReminderHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class EventReminderCommandTest extends ContaoTestCase
{
    /**
     * eventId => [title, eventReminderSentAt].
     *
     * @var array<int, array{0: string, 1: int}>
     */
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->events = [
            10 => ['Sidelhorn &#40;Skitour&#41;', 0],
            11 => ['Pilatus', 0],
            12 => ['Titlis', 1790000000],
        ];
    }

    public function testDispatchesMessagesForTheDueEventsLikeTheCron(): void
    {
        $dispatched = [];

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->method('dispatch')
            ->willReturnCallback(
                static function (SendEventReminderMessage $message) use (&$dispatched): Envelope {
                    $dispatched[] = $message->getEventId();

                    return new Envelope($message);
                },
            )
        ;

        $handler = $this->createMock(SendEventReminderHandler::class);
        $handler
            ->expects($this->never())
            ->method('__invoke')
        ;

        $tester = $this->createTester([10, 11], $messageBus, $handler);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame([10, 11], $dispatched);
        $this->assertStringContainsString('Event ID 10 Sidelhorn (Skitour)', $tester->getDisplay());
    }

    public function testSyncSendsImmediatelyAndReportsTheResult(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->never())
            ->method('dispatch')
        ;

        // Event 10 is sent, event 11 is not (e.g. no accepted participants)
        $handler = $this->createMock(SendEventReminderHandler::class);
        $handler
            ->expects($this->exactly(2))
            ->method('__invoke')
            ->willReturnCallback(
                function (SendEventReminderMessage $message): void {
                    if (10 === $message->getEventId()) {
                        $this->events[10][1] = time();
                    }
                },
            )
        ;

        $tester = $this->createTester([10, 11], $messageBus, $handler);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--sync' => true]));

        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Erinnerung versendet: * Event ID 10 Sidelhorn (Skitour)', $display);
        $this->assertStringContainsString('Keine Erinnerung versendet: * Event ID 11 Pilatus', $display);
    }

    public function testSingleEventIsSentImmediatelyRegardlessOfTheDate(): void
    {
        $handler = $this->createMock(SendEventReminderHandler::class);
        $handler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static fn (SendEventReminderMessage $message): bool => 12 === $message->getEventId()))
        ;

        // No due events today: --event ignores the date
        $tester = $this->createTester([], $this->createMock(MessageBusInterface::class), $handler);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--event' => '12']));
        $this->assertStringContainsString('Event ID 12 Titlis: bereits versendet am', $tester->getDisplay());
    }

    public function testDryRunSendsNothing(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->never())
            ->method('dispatch')
        ;

        $handler = $this->createMock(SendEventReminderHandler::class);
        $handler
            ->expects($this->never())
            ->method('__invoke')
        ;

        $tester = $this->createTester([10, 11], $messageBus, $handler);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('Event ID 11 Pilatus', $tester->getDisplay());
    }

    public function testNoDueEvents(): void
    {
        $tester = $this->createTester([], $this->createMock(MessageBusInterface::class), $this->createMock(SendEventReminderHandler::class));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Heute ist für keinen Event eine Erinnerung fällig.', $tester->getDisplay());
    }

    /**
     * @param list<int> $dueEventIds
     */
    private function createTester(array $dueEventIds, MessageBusInterface $messageBus, SendEventReminderHandler $handler): CommandTester
    {
        $cron = $this->createMock(EventReminderCron::class);
        $cron
            ->method('findDueEventIds')
            ->willReturn($dueEventIds)
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturnCallback(
                function (string $sql, array $params): int|string|false {
                    $event = $this->events[$params[0]] ?? null;

                    if (null === $event) {
                        return false;
                    }

                    return str_contains($sql, 'SELECT title') ? $event[0] : $event[1];
                },
            )
        ;

        return new CommandTester(new EventReminderCommand($connection, $this->mockContaoFramework(), $cron, $messageBus, $handler));
    }
}
