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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventCompletionReminder\Messenger\MessageHandler;

use Contao\CalendarModel;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Messenger\Message\SendEventCompletionReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Messenger\MessageHandler\SendEventCompletionReminderHandler;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\OpenTask;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\OpenTaskProvider;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\ReminderLog;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\TaskItem;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Routing\RouterInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Terminal42\NotificationCenterBundle\Receipt\ReceiptCollection;
use Twig\Environment;

final class SendEventCompletionReminderHandlerTest extends ContaoTestCase
{
    private const USER_ID = 5;

    private const CALENDAR_ID = 7;

    private const NOTIFICATION_ID = 3;

    /**
     * @var list<string> Call log to verify the order of side effects
     */
    private array $calls = [];

    private NotificationCenter&MockObject $notificationCenter;

    private ReminderLog&MockObject $reminderLog;

    private OpenTaskProvider&MockObject $openTaskProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calls = [];
        $this->notificationCenter = $this->createMock(NotificationCenter::class);
        $this->reminderLog = $this->createMock(ReminderLog::class);
        $this->openTaskProvider = $this->createMock(OpenTaskProvider::class);
    }

    public function testDoesNothingIfFeatureIsDisabledOnCalendar(): void
    {
        $handler = $this->createHandler(calendarProperties: ['sendEventCompletionReminder' => false]);

        $this->expectNoNotification();

        $handler(new SendEventCompletionReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingWithoutNotification(): void
    {
        $handler = $this->createHandler(calendarProperties: ['eventCompletionReminderNotification' => 0]);

        $this->expectNoNotification();

        $handler(new SendEventCompletionReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingIfRecipientIsNotValid(): void
    {
        $handler = $this->createHandler(validRecipient: false);

        $this->expectNoNotification();

        $handler(new SendEventCompletionReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingIfIntervalHasNotExpired(): void
    {
        $handler = $this->createHandler(lastSentAt: time() - 3600);

        $this->openTaskProvider
            ->expects($this->never())
            ->method('getOpenTasks')
        ;

        $this->expectNoNotification();

        $handler(new SendEventCompletionReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingIfAllTasksAreDone(): void
    {
        $handler = $this->createHandler(openTasks: []);

        $this->expectNoNotification();

        $handler(new SendEventCompletionReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingIfAnotherWorkerHoldsTheLock(): void
    {
        $handler = $this->createHandler(lockAcquired: false);

        $this->expectNoNotification();

        $handler(new SendEventCompletionReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testLogsBeforeSendingAndPassesTokens(): void
    {
        $handler = $this->createHandler(lastSentAt: strtotime('-8 days'), sentCount: 1);

        $this->reminderLog
            ->expects($this->once())
            ->method('logNotification')
            ->with(self::USER_ID, self::CALENDAR_ID, self::NOTIFICATION_ID, $this->greaterThan(0), 3, [10, 11])
            ->willReturnCallback(
                function (): int {
                    $this->calls[] = 'log';

                    return 99;
                },
            )
        ;

        $this->notificationCenter
            ->expects($this->once())
            ->method('sendNotification')
            ->with(
                self::NOTIFICATION_ID,
                $this->callback(
                    function (array $tokens): bool {
                        $this->assertSame('anna@example.org', $tokens['recipient_email']);
                        $this->assertSame('Anna Muster', $tokens['instructor_name']);
                        $this->assertSame('Anna', $tokens['instructor_firstname']);
                        $this->assertSame('Touren & Kurse', $tokens['calendar_title']);
                        $this->assertSame(3, $tokens['open_task_count']);
                        $this->assertSame(2, $tokens['event_count']);
                        $this->assertSame(2, $tokens['reminder_count']);
                        $this->assertSame(7, $tokens['first_offset_days']);
                        $this->assertSame(7, $tokens['interval_days']);
                        $this->assertArrayNotHasKey('lookback_days', $tokens);
                        $this->assertSame('<html-list>', $tokens['task_list_html']);
                        $this->assertSame('text-list', $tokens['task_list_text']);
                        $this->assertSame('https://example.org/contao', $tokens['link_my_events_dashboard']);

                        return true;
                    },
                ),
                'de',
            )
            ->willReturnCallback(
                function (): ReceiptCollection {
                    $this->calls[] = 'send';

                    return new ReceiptCollection([]);
                },
            )
        ;

        $this->reminderLog
            ->expects($this->once())
            ->method('markAsDelivered')
            ->with(99)
        ;

        $handler(new SendEventCompletionReminderMessage(self::USER_ID, self::CALENDAR_ID));

        $this->assertSame(['log', 'send'], $this->calls, 'The log entry must be written before the notification is sent');
    }

    private function expectNoNotification(): void
    {
        $this->reminderLog
            ->expects($this->never())
            ->method('logNotification')
        ;

        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;
    }

    /**
     * @param array<string, mixed> $calendarProperties
     * @param list<OpenTask>|null  $openTasks
     */
    private function createHandler(array $calendarProperties = [], bool $validRecipient = true, int|null $lastSentAt = null, int $sentCount = 0, array|null $openTasks = null, bool $lockAcquired = true): SendEventCompletionReminderHandler
    {
        $calendar = $this->mockClassWithProperties(CalendarModel::class, array_merge([
            'id' => self::CALENDAR_ID,
            'title' => 'Touren &amp; Kurse',
            'sendEventCompletionReminder' => true,
            'eventCompletionReminderNotification' => self::NOTIFICATION_ID,
            'eventCompletionReminderFirstOffset' => 7,
            'eventCompletionReminderInterval' => 7,
        ], $calendarProperties));

        $calendarAdapter = $this->mockAdapter(['findById']);
        $calendarAdapter
            ->method('findById')
            ->with(self::CALENDAR_ID)
            ->willReturn($calendar)
        ;

        $framework = $this->mockContaoFramework([CalendarModel::class => $calendarAdapter]);

        $user = $this->mockClassWithProperties(UserModel::class, [
            'id' => self::USER_ID,
            'name' => 'Anna Muster',
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'email' => 'anna@example.org',
            'disable' => false,
        ]);

        $this->openTaskProvider
            ->method('getRecipient')
            ->willReturn($validRecipient ? $user : null)
        ;

        $this->openTaskProvider
            ->method('getOpenTasks')
            ->willReturn($openTasks ?? [
                new OpenTask(10, 'Skitour', 'tour', 1758900000, 1759000000, OpenTask::ROLE_INSTRUCTOR, [
                    new TaskItem('tour_report', 'Tourenbericht ausfüllen', 'https://example.org/1'),
                    new TaskItem('participation_confirmation', 'Teilnahme bestätigen', 'https://example.org/2'),
                ]),
                new OpenTask(11, 'Kletterkurs', 'course', 1759100000, 1759100000, OpenTask::ROLE_REGISTRATION_COORDINATOR, [
                    new TaskItem('participation_confirmation', 'Teilnahme bestätigen', 'https://example.org/3'),
                ]),
            ])
        ;

        $this->reminderLog
            ->method('getLastSentAt')
            ->willReturn($lastSentAt)
        ;

        $this->reminderLog
            ->method('countSent')
            ->willReturn($sentCount)
        ;

        $lock = $this->createMock(SharedLockInterface::class);
        $lock
            ->method('acquire')
            ->willReturn($lockAcquired)
        ;

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory
            ->method('createLock')
            ->willReturn($lock)
        ;

        $router = $this->createMock(RouterInterface::class);
        $router
            ->method('generate')
            ->willReturn('https://example.org/contao')
        ;

        $twig = $this->createMock(Environment::class);
        $twig
            ->method('render')
            ->willReturnCallback(
                static fn (string $template): string => SendEventCompletionReminderHandler::TEMPLATE_HTML === $template ? '<html-list>' : "text-list\n",
            )
        ;

        return new SendEventCompletionReminderHandler(
            $framework,
            $lockFactory,
            $this->notificationCenter,
            $this->openTaskProvider,
            $this->reminderLog,
            $router,
            $twig,
            'de',
            $this->createMock(LoggerInterface::class),
        );
    }
}
