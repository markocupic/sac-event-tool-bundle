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

namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Messenger\MessageHandler;

use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Messenger\Message\SendEventCompletionReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\OpenTask;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\OpenTaskProvider;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\ReminderLog;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\ReminderSchedule;
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
readonly class SendEventCompletionReminderHandler
{
    public const TEMPLATE_HTML = '@MarkocupicSacEventTool/Email/EventCompletionReminder/task_list.html.twig';

    public const TEMPLATE_TEXT = '@MarkocupicSacEventTool/Email/EventCompletionReminder/task_list.txt.twig';

    /**
     * Event types without a tour report: the event status (e.g. "canceled") can only be set
     * in the event itself. The to-do list shows a hint with a link to the event for these types.
     */
    public const EVENT_TYPES_WITH_CANCEL_HINT = [EventType::COURSE, EventType::GENERAL_EVENT];

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

    public function __invoke(SendEventCompletionReminderMessage $message): void
    {
        $this->framework->initialize();

        $userId = $message->getUserId();
        $calendarId = $message->getCalendarId();

        $calendar = $this->framework->getAdapter(CalendarModel::class)->findById($calendarId);

        // Calendar deleted or feature disabled in the meantime
        if (null === $calendar || !$calendar->sendEventCompletionReminder) {
            return;
        }

        $notificationId = (int) $calendar->eventCompletionReminderNotification;

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
            $intervalDays = (int) $calendar->eventCompletionReminderInterval;

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
            $logId = $this->reminderLog->logNotification($userId, $calendarId, $notificationId, $now->getTimestamp(), $openTaskCount, $eventIds);

            $receipts = $this->notificationCenter->sendNotification($notificationId, $tokens, $this->sacevtLocale);

            if ($receipts->wereAllDelivered()) {
                $this->reminderLog->markAsDelivered($logId);

                return;
            }

            foreach ($receipts as $receipt) {
                if (!$receipt->wasDelivered()) {
                    $this->contaoErrorLogger?->error(\sprintf(
                        'Event completion reminder for user ID %d and calendar ID %d could not be delivered: %s',
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

        $templateData = [
            'open_tasks' => $openTasks,
            'cancel_hint_urls' => $this->getCancelHintUrls($openTasks),
        ];

        return [
            'recipient_email' => $email,
            'instructor_email' => $email,
            'instructor_firstname' => $firstname,
            'instructor_lastname' => $lastname,
            'instructor_name' => '' !== $name ? $name : $this->decode((string) $user->name),
            'calendar_title' => $this->decode((string) $calendar->title),
            'task_list_html' => $this->twig->render(self::TEMPLATE_HTML, $templateData),
            'task_list_text' => trim($this->twig->render(self::TEMPLATE_TEXT, $templateData)),
            'open_task_count' => $openTaskCount,
            'event_count' => \count($openTasks),
            'first_offset_days' => (int) $calendar->eventCompletionReminderFirstOffset,
            'interval_days' => (int) $calendar->eventCompletionReminderInterval,
            'reminder_count' => $reminderCount,
            'link_my_events_dashboard' => $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /**
     * Link to the event edit form for events without a tour report (courses, general events),
     * so the instructor can set the event status to "canceled" there. Canceled events are skipped
     * by the reminder.
     *
     * @param list<OpenTask> $openTasks
     *
     * @return array<int, string> event id => absolute backend URL
     */
    private function getCancelHintUrls(array $openTasks): array
    {
        $urls = [];

        foreach ($openTasks as $openTask) {
            if (!\in_array($openTask->eventType, self::EVENT_TYPES_WITH_CANCEL_HINT, true)) {
                continue;
            }

            $urls[$openTask->eventId] = $this->router->generate(
                'contao_backend',
                [
                    'do' => 'calendar',
                    'table' => 'tl_calendar_events',
                    'act' => 'edit',
                    'id' => $openTask->eventId,
                ],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        return $urls;
    }

    /**
     * Contao stores text input-encoded (e.g. "&amp;").
     */
    private function decode(string $value): string
    {
        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
