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

namespace Markocupic\SacEventToolBundle\Messenger\MessageHandler;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder\OpenTask;
use Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder\OpenTaskProvider;
use Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder\ReminderLog;
use Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder\ReminderSchedule;
use Markocupic\SacEventToolBundle\Messenger\Message\SendInstructorPostEventTaskReminderMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Twig\Environment as Twig;

/**
 * Sends ONE notification to ONE recipient (instructor or registration coordinator)
 * with the open post-event tasks of all due events of ONE calendar.
 *
 * Everything is re-checked here, because the state may have changed since the cron
 * dispatched the message (task completed, user disabled, cron ran twice, ...).
 *
 * The log entry is written BEFORE sending: better one missing reminder than a duplicate one.
 * Delivery errors are logged, not retried.
 */
#[AsMessageHandler]
readonly class SendInstructorPostEventTaskReminderHandler
{
    public const TEMPLATE_HTML = '@MarkocupicSacEventTool/Email/InstructorPostEventTaskReminder/task_list.html.twig';

    public const TEMPLATE_TEXT = '@MarkocupicSacEventTool/Email/InstructorPostEventTaskReminder/task_list.txt.twig';

    public function __construct(
        private ContaoFramework $framework,
        private LockFactory $lockFactory,
        private NotificationCenter $notificationCenter,
        private OpenTaskProvider $openTaskProvider,
        private ReminderLog $reminderLog,
        private RouterInterface $router,
        private Twig $twig,
        private string $sacevtLocale,
        private LoggerInterface|null $contaoErrorLogger,
    ) {
    }

    public function __invoke(SendInstructorPostEventTaskReminderMessage $message): void
    {
        $this->framework->initialize();

        $userId = $message->getUserId();
        $calendarId = $message->getCalendarId();

        $calendar = $this->framework->getAdapter(CalendarModel::class)->findById($calendarId);

        // Calendar deleted or feature disabled in the meantime
        if (null === $calendar || !$calendar->sendInstructorPostEventTaskReminder) {
            return;
        }

        $notificationId = (int) $calendar->instructorPostEventTaskReminderNotification;

        if ($notificationId < 1) {
            return;
        }

        // User deleted, disabled or without email address
        $user = $this->openTaskProvider->getRecipient($userId);

        if (null === $user) {
            return;
        }

        // Serialize check, log and send for this (user, calendar) pair
        $lock = $this->lockFactory->createLock(self::class.'-'.$userId.'-'.$calendarId, 120);

        if (!$lock->acquire()) {
            return; // Another worker is handling this pair right now
        }

        try {
            $now = new \DateTimeImmutable();
            $intervalDays = (int) $calendar->instructorPostEventTaskReminderInterval;

            // Protects against duplicate messages (e.g. the cron ran twice)
            if (!ReminderSchedule::isNotificationDue($this->reminderLog->getLastSentAt($userId, $calendarId), $intervalDays, $now)) {
                return;
            }

            $openTasks = $this->openTaskProvider->getOpenTasks($userId, $calendar, $now);

            // All tasks done in the meantime: no notification
            if (empty($openTasks)) {
                return;
            }

            $openTaskCount = array_sum(array_map(static fn (OpenTask $openTask): int => $openTask->countTasks(), $openTasks));
            $eventIds = array_map(static fn (OpenTask $openTask): int => $openTask->eventId, $openTasks);
            $reminderCount = $this->reminderLog->countSent($userId, $calendarId) + 1;

            $tokens = $this->getTokens($user, $calendar, $openTasks, $openTaskCount, $reminderCount);

            // Log first: better one missing reminder than a duplicate one
            $logId = $this->reminderLog->add($userId, $calendarId, $notificationId, $now->getTimestamp(), $openTaskCount, $eventIds);

            $receipts = $this->notificationCenter->sendNotification($notificationId, $tokens, $this->sacevtLocale);

            if ($receipts->wereAllDelivered()) {
                $this->reminderLog->markAsDelivered($logId);

                return;
            }

            foreach ($receipts as $receipt) {
                if (!$receipt->wasDelivered()) {
                    $this->contaoErrorLogger?->error(\sprintf(
                        'Instructor post-event task reminder for user ID %d and calendar ID %d could not be delivered: %s',
                        $userId,
                        $calendarId,
                        $receipt->getException()?->getMessage() ?? 'Unknown error.',
                    ));
                }
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * @param list<OpenTask> $openTasks
     *
     * @return array<string, mixed>
     */
    private function getTokens(UserModel $user, CalendarModel $calendar, array $openTasks, int $openTaskCount, int $reminderCount): array
    {
        $email = trim((string) $user->email);
        $firstname = $this->decode((string) $user->firstname);
        $lastname = $this->decode((string) $user->lastname);
        $name = trim($firstname.' '.$lastname);

        return [
            'recipient_email' => $email,
            'instructor_email' => $email,
            'instructor_firstname' => $firstname,
            'instructor_lastname' => $lastname,
            'instructor_name' => '' !== $name ? $name : $this->decode((string) $user->name),
            'calendar_title' => $this->decode((string) $calendar->title),
            'task_list_html' => $this->twig->render(self::TEMPLATE_HTML, ['open_tasks' => $openTasks]),
            'task_list_text' => trim($this->twig->render(self::TEMPLATE_TEXT, ['open_tasks' => $openTasks])),
            'open_task_count' => $openTaskCount,
            'event_count' => \count($openTasks),
            'first_offset_days' => (int) $calendar->instructorPostEventTaskReminderFirstOffset,
            'interval_days' => (int) $calendar->instructorPostEventTaskReminderInterval,
            'lookback_days' => (int) $calendar->instructorPostEventTaskReminderLookback,
            'reminder_count' => $reminderCount,
            'link_my_events_dashboard' => $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /**
     * Contao stores text input-encoded (e.g. "&amp;").
     */
    private function decode(string $value): string
    {
        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
