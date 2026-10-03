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

namespace Markocupic\SacEventToolBundle\Tests\Messenger\MessageHandler;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Date;
use Contao\Events;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\EventReminder\Participant;
use Markocupic\SacEventToolBundle\EventReminder\Person;
use Markocupic\SacEventToolBundle\EventReminder\PersonProvider;
use Markocupic\SacEventToolBundle\EventReminder\RecipientResolver;
use Markocupic\SacEventToolBundle\Messenger\Message\SendEventReminderMessage;
use Markocupic\SacEventToolBundle\Messenger\MessageHandler\SendEventReminderHandler;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Terminal42\NotificationCenterBundle\Receipt\ReceiptCollection;

final class SendEventReminderHandlerTest extends ContaoTestCase
{
    private const EVENT_ID = 42;

    private const CALENDAR_ID = 7;

    private const NOTIFICATION_ID = 3;

    /**
     * @var list<string> Call log to verify the order of side effects
     */
    private array $calls = [];

    private Connection&MockObject $connection;

    private NotificationCenter&MockObject $notificationCenter;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // StringUtil::revertInputEncoding() needs the kernel.charset parameter
        System::setContainer($this->getContainerWithContaoConfiguration());

        $this->calls = [];
        $this->connection = $this->createMock(Connection::class);
        $this->notificationCenter = $this->createMock(NotificationCenter::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testDoesNothingIfReminderWasAlreadySent(): void
    {
        $handler = $this->createHandler(['eventReminderSentAt' => 1700000000]);

        $this->connection
            ->expects($this->never())
            ->method('update')
        ;

        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $handler(new SendEventReminderMessage(self::EVENT_ID));
    }

    public function testDoesNothingIfReminderIsDisabledOnCalendar(): void
    {
        $handler = $this->createHandler([], ['sendEventReminder' => false]);

        $this->connection
            ->expects($this->never())
            ->method('update')
        ;

        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $handler(new SendEventReminderMessage(self::EVENT_ID));
    }

    public function testDoesNothingWithoutAcceptedParticipants(): void
    {
        $handler = $this->createHandler([], [], []);

        $this->connection
            ->expects($this->never())
            ->method('update')
        ;

        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $handler(new SendEventReminderMessage(self::EVENT_ID));
    }

    public function testLogsErrorAndDoesNotSendWithoutAnyRecipient(): void
    {
        $handler = $this->createHandler([], [], null, []);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('no recipient'))
        ;

        $this->connection
            ->expects($this->never())
            ->method('update')
        ;

        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $handler(new SendEventReminderMessage(self::EVENT_ID));
    }

    public function testDoesNotSendIfAnotherWorkerWasFaster(): void
    {
        $handler = $this->createHandler();

        $this->connection
            ->method('update')
            ->willReturn(0)
        ;

        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $handler(new SendEventReminderMessage(self::EVENT_ID));
    }

    public function testSetsFlagBeforeSendingAndPassesRecipientTokens(): void
    {
        $handler = $this->createHandler();

        $this->connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'tl_calendar_events',
                $this->callback(static fn (array $data): bool => $data['eventReminderSentAt'] > 0),
                ['id' => self::EVENT_ID, 'eventReminderSentAt' => 0],
            )
            ->willReturnCallback(
                function (): int {
                    $this->calls[] = 'update';

                    return 1;
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
                        $this->assertSame('anna@example.org', $tokens['recipient_to']);
                        $this->assertSame('beat@example.org', $tokens['recipient_cc']);
                        $this->assertSame('p1@example.org,p2@example.org', $tokens['recipient_bcc']);
                        $this->assertSame('Anna Muster', $tokens['main_instructor_name']);
                        $this->assertSame('Beat Muster', $tokens['co_instructors_names']);
                        $this->assertSame(2, $tokens['participants_count']);
                        $this->assertSame('Skitour', $tokens['event_title']);
                        $this->assertSame('https://example.org/event', $tokens['event_link_detail']);
                        $this->assertSame(14, $tokens['reminder_offset_days']);

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

        $this->logger
            ->expects($this->never())
            ->method('error')
        ;

        $handler(new SendEventReminderMessage(self::EVENT_ID));

        $this->assertSame(['update', 'send'], $this->calls, 'The sent flag must be set before the notification is sent');
    }

    /**
     * @param array<string, mixed>   $eventProperties
     * @param array<string, mixed>   $calendarProperties
     * @param list<Participant>|null $participants
     * @param list<int>|null         $instructorIds
     */
    private function createHandler(array $eventProperties = [], array $calendarProperties = [], array|null $participants = null, array|null $instructorIds = null): SendEventReminderHandler
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, array_merge([
            'id' => self::EVENT_ID,
            'pid' => self::CALENDAR_ID,
            'title' => 'Skitour',
            'eventType' => 'tour',
            'startDate' => 1765000000,
            'endDate' => 1765000000,
            'eventDates' => serialize([['new_repeat' => 1765000000]]),
            'durationInfo' => '1 Tag',
            'meetingPoint' => '',
            'leistungen' => '',
            'allowDeregistration' => false,
            'deregistrationLimit' => 0,
            'registrationGoesTo' => 0,
            'eventReminderSentAt' => 0,
        ], $eventProperties));

        $event
            ->method('row')
            ->willReturn(['id' => self::EVENT_ID, 'title' => 'Skitour', 'eventType' => 'tour'])
        ;

        $calendar = $this->mockClassWithProperties(CalendarModel::class, array_merge([
            'id' => self::CALENDAR_ID,
            'sendEventReminder' => true,
            'eventReminderNotification' => self::NOTIFICATION_ID,
            'eventReminderOffset' => 14,
        ], $calendarProperties));

        $eventAdapter = $this->mockAdapter(['findById']);
        $eventAdapter
            ->method('findById')
            ->with(self::EVENT_ID)
            ->willReturn($event)
        ;

        $calendarAdapter = $this->mockAdapter(['findById']);
        $calendarAdapter
            ->method('findById')
            ->with(self::CALENDAR_ID)
            ->willReturn($calendar)
        ;

        $dateAdapter = $this->mockAdapter(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int|string $tstamp): string => date($format, (int) $tstamp))
        ;

        $eventsAdapter = $this->mockAdapter(['generateEventUrl']);
        $eventsAdapter
            ->method('generateEventUrl')
            ->willReturn('https://example.org/event')
        ;

        $framework = $this->mockContaoFramework([
            CalendarEventsModel::class => $eventAdapter,
            CalendarModel::class => $calendarAdapter,
            Events::class => $eventsAdapter,
            Date::class => $dateAdapter,
        ]);

        $anna = new Person(1, 'Anna Muster', 'anna@example.org');
        $beat = new Person(2, 'Beat Muster', 'beat@example.org');
        $instructors = array_values(array_filter([$anna, $beat], static fn (Person $p): bool => \in_array($p->id, $instructorIds ?? [1, 2], true)));

        $personProvider = $this->createMock(PersonProvider::class);
        $personProvider
            ->method('getInstructors')
            ->willReturn($instructors)
        ;

        $personProvider
            ->method('getFlaggedMainInstructor')
            ->willReturn($instructors[0] ?? null)
        ;

        $personProvider
            ->method('getRegistrationCoordinator')
            ->willReturn(null)
        ;

        $personProvider
            ->method('getParticipants')
            ->willReturn($participants ?? [
                new Participant('Petra', 'Eins', 'p1@example.org'),
                new Participant('Paul', 'Zwei', 'p2@example.org'),
            ])
        ;

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id)
        ;

        return new SendEventReminderHandler(
            new CalendarEventsUtil($framework),
            $this->connection,
            $framework,
            $this->notificationCenter,
            $personProvider,
            new RecipientResolver(),
            $translator,
            'de',
            $this->logger,
        );
    }
}
