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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventFeedback\Cron;

use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\Cron\SendFeedbackRemindersCron;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\EventFeedbackHelper;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackReminder;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\Messenger\Message\SendEventFeedbackReminderMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SendFeedbackRemindersCronTest extends ContaoTestCase
{
    public function testDispatchesDueAndClaimedRemindersOnly(): void
    {
        $now = time();

        $feedbackReminder = $this->createMock(FeedbackReminder::class);
        $feedbackReminder
            ->expects($this->once())
            ->method('deleteObsolete')
        ;

        $feedbackReminder
            ->method('findPending')
            ->willReturn([
                // Due: sending date 2 minutes ago, delay 60 s
                ['id' => 1, 'executionDate' => $now - 120, 'configuration' => 'default'],
                // Not yet due: sending date 30 seconds ago, delay 60 s
                ['id' => 2, 'executionDate' => $now - 30, 'configuration' => 'default'],
                // Due, but claimed by another process in the meantime
                ['id' => 3, 'executionDate' => $now - 120, 'configuration' => 'default'],
                // Due: unknown configuration, no delay
                ['id' => 4, 'executionDate' => $now - 30, 'configuration' => null],
            ])
        ;

        $feedbackReminder
            ->method('claim')
            ->willReturnCallback(static fn (int $id): bool => 3 !== $id)
        ;

        $helper = $this->createMock(EventFeedbackHelper::class);
        $helper
            ->method('getConfigurationByName')
            ->willReturnCallback(static fn (string $name): array|null => 'default' === $name ? ['send_reminder_execution_delay' => 60] : null)
        ;

        $dispatched = [];

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->method('dispatch')
            ->willReturnCallback(
                static function (SendEventFeedbackReminderMessage $message) use (&$dispatched): Envelope {
                    $dispatched[] = $message->getReminderId();

                    return new Envelope($message);
                },
            )
        ;

        $cron = new SendFeedbackRemindersCron($this->mockContaoFramework(), $helper, $feedbackReminder, $messageBus, null);
        $cron();

        $this->assertSame([1, 4], $dispatched);
    }
}
