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

namespace Markocupic\SacEventToolBundle\Tests\Feature\InstructorPostEventTaskReminder\Cron;

use Contao\CalendarModel;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\Cron\InstructorPostEventTaskReminderCron;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\Messenger\Message\SendInstructorPostEventTaskReminderMessage;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\OpenTaskProvider;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\ReminderLog;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class InstructorPostEventTaskReminderCronTest extends ContaoTestCase
{
    public function testDispatchesOneMessagePerDueRecipientAndCalendar(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn([7, 8])
        ;

        $calendarAdapter = $this->mockAdapter(['findById']);
        $calendarAdapter
            ->method('findById')
            ->willReturnCallback(
                fn (int $id): CalendarModel => $this->mockClassWithProperties(CalendarModel::class, [
                    'id' => $id,
                    'instructorPostEventTaskReminderInterval' => 7,
                ]),
            )
        ;

        $framework = $this->mockContaoFramework([CalendarModel::class => $calendarAdapter]);

        // Calendar 7: users 1 and 2 have open tasks; calendar 8: user 1
        $openTaskProvider = $this->createMock(OpenTaskProvider::class);
        $openTaskProvider
            ->method('getRecipientIdsWithOpenTasks')
            ->willReturnCallback(static fn (CalendarModel $calendar): array => 7 === $calendar->id ? [1, 2] : [1])
        ;

        // User 2 was notified for calendar 7 yesterday: interval not expired
        $reminderLog = $this->createMock(ReminderLog::class);
        $reminderLog
            ->method('getLastSentAt')
            ->willReturnCallback(static fn (int $userId, int $calendarId): int|null => 2 === $userId && 7 === $calendarId ? strtotime('-1 day') : null)
        ;

        $dispatched = [];

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->method('dispatch')
            ->willReturnCallback(
                static function (SendInstructorPostEventTaskReminderMessage $message) use (&$dispatched): Envelope {
                    $dispatched[] = [$message->getUserId(), $message->getCalendarId()];

                    return new Envelope($message);
                },
            )
        ;

        $cron = new InstructorPostEventTaskReminderCron($connection, $framework, $messageBus, $openTaskProvider, $reminderLog, null);
        $cron();

        $this->assertSame([[1, 7], [1, 8]], $dispatched);
    }
}
