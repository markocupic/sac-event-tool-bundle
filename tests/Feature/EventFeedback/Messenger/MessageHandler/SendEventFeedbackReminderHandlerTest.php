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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventFeedback\Messenger\MessageHandler;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\EventFeedbackHelper;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackReminder;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackToken;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\Messenger\Message\SendEventFeedbackReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\Messenger\MessageHandler\SendEventFeedbackReminderHandler;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\EventFeedbackModel;
use Markocupic\SacEventToolBundle\Model\EventFeedbackReminderModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use PHPUnit\Framework\MockObject\MockObject;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Terminal42\NotificationCenterBundle\Receipt\ReceiptCollection;

final class SendEventFeedbackReminderHandlerTest extends ContaoTestCase
{
    private const REMINDER_ID = 7;

    private NotificationCenter&MockObject $notificationCenter;

    private FeedbackReminder&MockObject $feedbackReminder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationCenter = $this->createMock(NotificationCenter::class);
        $this->feedbackReminder = $this->createMock(FeedbackReminder::class);
    }

    public function testDoesNothingIfReminderDoesNotExist(): void
    {
        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $this->feedbackReminder
            ->expects($this->never())
            ->method('delete')
        ;

        $handler = $this->createHandler(reminderExists: false);
        $handler(new SendEventFeedbackReminderMessage(self::REMINDER_ID));
    }

    public function testDeletesReminderWithoutSendingIfFeedbackWasGiven(): void
    {
        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $this->feedbackReminder
            ->expects($this->once())
            ->method('delete')
            ->with(self::REMINDER_ID)
        ;

        $handler = $this->createHandler(feedbackExists: true);
        $handler(new SendEventFeedbackReminderMessage(self::REMINDER_ID));
    }

    public function testDeletesReminderWithoutSendingIfConfigurationIsInvalid(): void
    {
        $this->notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $this->feedbackReminder
            ->expects($this->once())
            ->method('delete')
            ->with(self::REMINDER_ID)
        ;

        $handler = $this->createHandler(validConfiguration: false);
        $handler(new SendEventFeedbackReminderMessage(self::REMINDER_ID));
    }

    public function testSendsNotificationAndDeletesReminder(): void
    {
        $this->notificationCenter
            ->expects($this->once())
            ->method('sendNotification')
            ->with(
                3,
                $this->callback(
                    function (array $tokens): bool {
                        $this->assertSame('Heidi', $tokens['participant_firstname']);
                        $this->assertSame('heidi@example.org', $tokens['participant_email']);
                        $this->assertSame('Skitour & Co', $tokens['event_title']);
                        $this->assertSame('Anna Muster', $tokens['instructor_name']);
                        $this->assertSame('admin@example.org', $tokens['admin_email']);
                        $this->assertStringStartsWith('https://example.org/feedback.html?token=', $tokens['feedback_url']);

                        return true;
                    },
                ),
            )
            ->willReturn(new ReceiptCollection([]))
        ;

        $this->feedbackReminder
            ->expects($this->once())
            ->method('delete')
            ->with(self::REMINDER_ID)
        ;

        $handler = $this->createHandler();
        $handler(new SendEventFeedbackReminderMessage(self::REMINDER_ID));
    }

    private function createHandler(bool $reminderExists = true, bool $feedbackExists = false, bool $validConfiguration = true): SendEventFeedbackReminderHandler
    {
        $reminder = $this->mockClassWithProperties(EventFeedbackReminderModel::class, [
            'id' => self::REMINDER_ID,
            'uuid' => 'abc-123',
            'expiration' => time() + 86400,
        ]);

        $registration = $this->mockClassWithProperties(CalendarEventsMemberModel::class, [
            'id' => 12,
            'uuid' => 'abc-123',
            'eventId' => 10,
            'firstname' => 'Heidi',
            'lastname' => 'Muster',
            'email' => 'heidi@example.org',
            'countOnlineEventFeedbackNotifications' => 0,
        ]);

        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 10, 'title' => 'Skitour &amp; Co']);

        $framework = $this->mockContaoFramework([
            EventFeedbackReminderModel::class => $this->mockConfiguredAdapter(['findById' => $reminderExists ? $reminder : null]),
            CalendarEventsMemberModel::class => $this->mockConfiguredAdapter(['findOneByUuid' => $registration]),
            EventFeedbackModel::class => $this->mockConfiguredAdapter(['findOneByUuid' => $feedbackExists ? $this->createMock(EventFeedbackModel::class) : null]),
            CalendarEventsModel::class => $this->mockConfiguredAdapter(['findById' => $event]),
            Config::class => $this->mockConfiguredAdapter(['get' => 'admin@example.org']),
        ]);

        $page = $this->mockClassWithProperties(PageModel::class, ['adminEmail' => '']);
        $page
            ->method('getAbsoluteUrl')
            ->willReturn('https://example.org/feedback.html')
        ;

        $helper = $this->createMock(EventFeedbackHelper::class);
        $helper
            ->method('eventHasValidFeedbackConfiguration')
            ->willReturn($validConfiguration ? true : 'event_feedback_form_not_set')
        ;

        $helper
            ->method('getNotificationId')
            ->willReturn(3)
        ;

        $helper
            ->method('getPage')
            ->willReturn($page)
        ;

        $calendarEventsUtil = $this->createMock(CalendarEventsUtil::class);
        $calendarEventsUtil
            ->method('getMainInstructorName')
            ->willReturn('Anna Muster')
        ;

        $calendarEventsUtil
            ->method('getMainInstructor')
            ->willReturn(null)
        ;

        return new SendEventFeedbackReminderHandler(
            $calendarEventsUtil,
            $framework,
            $helper,
            $this->feedbackReminder,
            new FeedbackToken('Sec!ret12345Ab'),
            $this->notificationCenter,
            null,
            null,
        );
    }
}
