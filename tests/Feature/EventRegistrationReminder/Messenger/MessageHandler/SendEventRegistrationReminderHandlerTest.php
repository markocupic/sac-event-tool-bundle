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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventRegistrationReminder\Messenger\MessageHandler;

use Contao\CalendarModel;
use Contao\Config;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\Messenger\Message\SendEventRegistrationReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\Messenger\MessageHandler\SendEventRegistrationReminderHandler;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\PendingEvent;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\PendingRegistration;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\PendingRegistrationProvider;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\ReminderLog;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Terminal42\NotificationCenterBundle\Receipt\ReceiptCollection;
use Twig\Environment;

final class SendEventRegistrationReminderHandlerTest extends ContaoTestCase
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

    private PendingRegistrationProvider&MockObject $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calls = [];
        $this->notificationCenter = $this->createMock(NotificationCenter::class);
        $this->reminderLog = $this->createMock(ReminderLog::class);
        $this->provider = $this->createMock(PendingRegistrationProvider::class);
    }

    public function testDoesNothingIfReminderIsDisabledOnCalendar(): void
    {
        $handler = $this->createHandler(calendarProperties: ['enableInstructorReminderNotification' => false]);

        $this->expectNoNotification();

        $handler(new SendEventRegistrationReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingWithoutNotification(): void
    {
        $handler = $this->createHandler(calendarProperties: ['sendReminderNotification' => '']);

        $this->expectNoNotification();

        $handler(new SendEventRegistrationReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingIfRecipientIsNotValid(): void
    {
        $handler = $this->createHandler(validRecipient: false);

        $this->expectNoNotification();

        $handler(new SendEventRegistrationReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingIfIntervalHasNotExpired(): void
    {
        $handler = $this->createHandler(lastSentAt: time() - 3600);

        $this->provider
            ->expects($this->never())
            ->method('getPendingEvents')
        ;

        $this->expectNoNotification();

        $handler(new SendEventRegistrationReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingIfAllRegistrationsWereProcessed(): void
    {
        $handler = $this->createHandler(pendingEvents: []);

        $this->expectNoNotification();

        $handler(new SendEventRegistrationReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testDoesNothingIfAnotherWorkerHoldsTheLock(): void
    {
        $handler = $this->createHandler(lockAcquired: false);

        $this->expectNoNotification();

        $handler(new SendEventRegistrationReminderMessage(self::USER_ID, self::CALENDAR_ID));
    }

    public function testLogsBeforeSendingAndPassesTokens(): void
    {
        $handler = $this->createHandler(lastSentAt: strtotime('-8 days'));

        $this->reminderLog
            ->expects($this->once())
            ->method('logReminder')
            ->with(self::USER_ID, self::CALENDAR_ID, 'Anna Muster', 7, $this->greaterThan(0))
            ->willReturnCallback(
                function (): void {
                    $this->calls[] = 'log';
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
                        $this->assertSame('anna@example.org', $tokens['instructor_email']);
                        $this->assertSame('Anna Muster', $tokens['instructor_name']);
                        $this->assertSame('Anna', $tokens['instructor_firstname']);
                        $this->assertSame('Muster', $tokens['instructor_lastname']);
                        $this->assertSame('admin@example.org', $tokens['admin_email']);
                        $this->assertSame('registration-list', $tokens['registrations']);
                        $this->assertSame(7, $tokens['send_reminder_each']);

                        return true;
                    },
                ),
                'fr',
            )
            ->willReturnCallback(
                function (): ReceiptCollection {
                    $this->calls[] = 'send';

                    return new ReceiptCollection([]);
                },
            )
        ;

        $handler(new SendEventRegistrationReminderMessage(self::USER_ID, self::CALENDAR_ID));

        $this->assertSame(['log', 'send'], $this->calls, 'The log entry must be written before the notification is sent');
    }

    private function expectNoNotification(): void
    {
        $this->reminderLog
            ->expects($this->never())
            ->method('logReminder')
        ;

        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;
    }

    /**
     * @param array<string, mixed>    $calendarProperties
     * @param list<PendingEvent>|null $pendingEvents
     */
    private function createHandler(array $calendarProperties = [], bool $validRecipient = true, int|null $lastSentAt = null, array|null $pendingEvents = null, bool $lockAcquired = true): SendEventRegistrationReminderHandler
    {
        $calendar = $this->mockClassWithProperties(CalendarModel::class, array_merge([
            'id' => self::CALENDAR_ID,
            'enableInstructorReminderNotification' => true,
            'sendReminderNotification' => (string) self::NOTIFICATION_ID,
            'sendFirstReminderAfter' => 7,
            'sendReminderEach' => 7,
        ], $calendarProperties));

        $calendarAdapter = $this->mockAdapter(['findById']);
        $calendarAdapter
            ->method('findById')
            ->with(self::CALENDAR_ID)
            ->willReturn($calendar)
        ;

        $configAdapter = $this->mockAdapter(['get']);
        $configAdapter
            ->method('get')
            ->willReturnMap([['adminEmail', 'admin@example.org']])
        ;

        $framework = $this->mockContaoFramework([CalendarModel::class => $calendarAdapter, Config::class => $configAdapter]);

        $user = $this->mockClassWithProperties(UserModel::class, [
            'id' => self::USER_ID,
            'name' => 'Anna Muster',
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'email' => 'anna@example.org',
            'language' => 'fr',
            'disable' => false,
        ]);

        $this->provider
            ->method('getRecipient')
            ->willReturn($validRecipient ? $user : null)
        ;

        $this->provider
            ->method('getPendingEvents')
            ->willReturn($pendingEvents ?? [
                new PendingEvent(10, 'Skitour', 'tour', [new PendingRegistration('Heidi', 'Muster', 'female', 123456, 8)], []),
            ])
        ;

        $this->reminderLog
            ->method('getLastSentAt')
            ->willReturn($lastSentAt)
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

        $twig = $this->createMock(Environment::class);
        $twig
            ->method('render')
            ->willReturn("registration-list\n")
        ;

        return new SendEventRegistrationReminderHandler(
            $framework,
            $lockFactory,
            $this->notificationCenter,
            $this->provider,
            $this->reminderLog,
            $twig,
            'de',
            $this->createMock(LoggerInterface::class),
        );
    }
}
