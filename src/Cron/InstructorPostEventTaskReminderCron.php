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

namespace Markocupic\SacEventToolBundle\Cron;

use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder\OpenTaskProvider;
use Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder\ReminderLog;
use Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder\ReminderSchedule;
use Markocupic\SacEventToolBundle\Messenger\Message\SendInstructorPostEventTaskReminderMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * For every calendar with the instructor post-event task reminder enabled:
 * finds all recipients (instructors, registration coordinators) with open tasks
 * in due events and dispatches one SendInstructorPostEventTaskReminderMessage
 * per (recipient, calendar), unless the interval since the last notification has not expired yet.
 *
 * Runs twice a night; the second run only sends what the first one missed
 * (the interval check prevents duplicates).
 *
 * See docs/features/instructor-post-event-task-reminder.md
 */
#[AsCronJob('45 3,4 * * *')]
readonly class InstructorPostEventTaskReminderCron
{
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
        $this->framework->initialize();

        $calendarIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_calendar WHERE sendInstructorPostEventTaskReminder = 1 AND instructorPostEventTaskReminderNotification > 0',
        );

        $calendarAdapter = $this->framework->getAdapter(CalendarModel::class);
        $now = new \DateTimeImmutable();
        $count = 0;

        foreach ($calendarIds as $calendarId) {
            $calendar = $calendarAdapter->findById((int) $calendarId);

            if (null === $calendar) {
                continue;
            }

            $intervalDays = (int) $calendar->instructorPostEventTaskReminderInterval;

            foreach ($this->openTaskProvider->getRecipientIdsWithOpenTasks($calendar, $now) as $userId) {
                if (!ReminderSchedule::isNotificationDue($this->reminderLog->getLastSentAt($userId, (int) $calendar->id), $intervalDays, $now)) {
                    continue;
                }

                ++$count;
                $this->messageBus->dispatch(new SendInstructorPostEventTaskReminderMessage($userId, (int) $calendar->id));
            }
        }

        // The messages are handled (and the notifications sent) asynchronously by SendInstructorPostEventTaskReminderHandler
        $this->contaoCronLogger?->info(\sprintf('Instructor post-event task reminder cron: dispatched %d message(s).', $count));
    }
}
