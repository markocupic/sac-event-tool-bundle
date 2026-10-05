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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\Cron;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\Messenger\Message\SendEventRegistrationReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\PendingRegistrationProvider;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\ReminderLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * For every calendar with the registration reminder enabled: finds all users with
 * unconfirmed registrations older than the calendar setting and dispatches one
 * SendEventRegistrationReminderMessage per (user, calendar), unless the interval
 * since the last reminder has not expired yet.
 *
 * Cron schedule: sacevt.feature.event_registration_reminder.cron_schedule (default "30 4,5 * * *",
 * twice in the morning; the second run only sends what the first one missed, the interval check
 * prevents duplicates). The tag "contao.cronjob" is added in MarkocupicSacEventToolExtension.
 *
 * Can be disabled globally with sacevt.feature.event_registration_reminder.disable.
 *
 * See docs/features/event-registration-reminder.md
 */
readonly class EventRegistrationReminderCron
{
    private const string STOP_WATCH_EVENT = 'event_registration_reminder_cron';

    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
        private MessageBusInterface $messageBus,
        private PendingRegistrationProvider $pendingRegistrationProvider,
        private ReminderLog $reminderLog,
        private LoggerInterface|null $contaoCronLogger,
        #[Autowire(param: 'sacevt.feature.event_registration_reminder.disable')]
        private bool $disable,
    ) {
    }

    public function __invoke(): void
    {
        if ($this->disable) {
            return;
        }

        // Measure the whole run to see whether it gets close to max_execution_time
        $stopwatchEvent = (new Stopwatch())->start(self::STOP_WATCH_EVENT);

        $this->framework->initialize();

        $calendarIds = $this->connection->fetchFirstColumn(
            "SELECT id FROM tl_calendar WHERE enableInstructorReminderNotification = 1 AND sendFirstReminderAfter > 0 AND sendReminderEach > 0 AND sendReminderNotification != '' AND sendReminderNotification != '0'",
        );

        $calendarAdapter = $this->framework->getAdapter(CalendarModel::class);
        $now = time();
        $count = 0;

        foreach ($calendarIds as $calendarId) {
            $calendar = $calendarAdapter->findById((int) $calendarId);

            if (null === $calendar) {
                continue;
            }

            foreach ($this->pendingRegistrationProvider->getRecipientIds($calendar, $now) as $userId) {
                if (!ReminderLog::isReminderDue($this->reminderLog->getLastSentAt($userId, (int) $calendar->id), (int) $calendar->sendReminderEach, $now)) {
                    continue;
                }

                ++$count;
                $this->messageBus->dispatch(new SendEventRegistrationReminderMessage($userId, (int) $calendar->id));
            }
        }

        $duration = round($stopwatchEvent->stop()->getDuration() / 1000, 2);

        // The messages are handled (and the notifications sent) asynchronously by SendEventRegistrationReminderHandler
        $this->contaoCronLogger?->info(\sprintf(
            'Event registration reminder cron: checked %d calendar(s) and dispatched %d message(s) in %s s.',
            \count($calendarIds),
            $count,
            $duration,
        ));
    }
}
