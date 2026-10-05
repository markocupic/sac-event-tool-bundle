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

namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Cron;

use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Messenger\Message\SendEventCompletionReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\OpenTaskProvider;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\ReminderLog;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\ReminderSchedule;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * For every calendar with the event completion reminder enabled:
 * finds all recipients (instructors, registration coordinators) with open tasks
 * in due events and dispatches one SendEventCompletionReminderMessage
 * per (recipient, calendar), unless the interval since the last notification has not expired yet.
 *
 * Runs twice a night; the second run only sends what the first one missed
 * (the interval check prevents duplicates).
 *
 * See docs/features/event-completion-reminder.md
 */
#[AsCronJob('*/1 * * * *')]
readonly class EventCompletionReminderCron
{
    private const string STOP_WATCH_EVENT = 'event_completion_reminder_cron';

    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
        private MessageBusInterface $messageBus,
        private OpenTaskProvider $openTaskProvider,
        private ReminderLog $reminderLog,
        private LoggerInterface|null $contaoCronLogger,
    ) {
    }

    public function __invoke(): void
    {
        // Measure the whole run to see whether it gets close to max_execution_time
        $stopwatchEvent = (new Stopwatch())->start(self::STOP_WATCH_EVENT);

        $this->framework->initialize();

        $calendarIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_calendar WHERE sendEventCompletionReminder = 1 AND eventCompletionReminderNotification > 0',
        );

        $calendarAdapter = $this->framework->getAdapter(CalendarModel::class);
        $now = new \DateTimeImmutable();
        $count = 0;
        $calendarCount = 0;

        foreach ($calendarIds as $calendarId) {
            $calendar = $calendarAdapter->findById((int) $calendarId);

            if (null === $calendar) {
                continue;
            }

            ++$calendarCount;
            $intervalDays = (int) $calendar->eventCompletionReminderInterval;

            foreach ($this->openTaskProvider->getRecipientIdsWithOpenTasks($calendar, $now) as $userId) {
                if (!ReminderSchedule::isNotificationDue($this->reminderLog->getLastSentAt($userId, (int) $calendar->id), $intervalDays, $now)) {
                    continue;
                }

                ++$count;
                $this->messageBus->dispatch(new SendEventCompletionReminderMessage($userId, (int) $calendar->id));
            }
        }

        $duration = round($stopwatchEvent->stop()->getDuration() / 1000, 2);

        // The messages are handled (and the notifications sent) asynchronously by SendEventCompletionReminderHandler
        $this->contaoCronLogger?->info(\sprintf(
            'Event completion reminder cron: checked %d calendar(s) and dispatched %d message(s) in %s s.',
            $calendarCount,
            $count,
            $duration,
        ));
    }
}
