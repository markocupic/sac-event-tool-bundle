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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\Messenger\MessageHandler;

use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\Messenger\Message\SendEventRegistrationReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\PendingEvent;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\PendingRegistrationProvider;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\ReminderLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Terminal42\NotificationCenterBundle\NotificationCenter;
use Twig\Environment as Twig;

/**
 * Sends ONE reminder to ONE user with the unconfirmed registrations of all his upcoming events of ONE calendar.
 *
 * Everything is re-checked here, because the state may have changed since the cron
 * dispatched the message (registrations processed, user disabled, cron ran twice, ...).
 *
 * The log entry is written BEFORE sending: better one missing reminder than a duplicate one.
 * Delivery errors are logged, not retried.
 */
#[AsMessageHandler]
readonly class SendEventRegistrationReminderHandler
{
    public const TEMPLATE = '@MarkocupicSacEventTool/Email/EventRegistrationReminder/registrations.txt.twig';

    public function __construct(
        private ContaoFramework $framework,
        private LockFactory $lockFactory,
        private NotificationCenter $notificationCenter,
        private PendingRegistrationProvider $pendingRegistrationProvider,
        private ReminderLog $reminderLog,
        private Twig $twig,
        private string $sacevtLocale,
        private LoggerInterface|null $contaoErrorLogger,
    ) {
    }

    public function __invoke(SendEventRegistrationReminderMessage $message): void
    {
        $this->framework->initialize();

        $userId = $message->getUserId();
        $calendarId = $message->getCalendarId();

        $calendar = $this->framework->getAdapter(CalendarModel::class)->findById($calendarId);

        // Calendar deleted or reminder disabled in the meantime
        if (null === $calendar || !$calendar->enableInstructorReminderNotification) {
            return;
        }

        $notificationId = (int) $calendar->sendReminderNotification;
        $intervalDays = (int) $calendar->sendReminderEach;

        if ($notificationId < 1 || $intervalDays < 1) {
            return;
        }

        // User deleted, disabled or without email address
        $user = $this->pendingRegistrationProvider->getRecipient($userId);

        if (null === $user) {
            return;
        }

        // Serialize check, log and send for this (user, calendar) pair
        $lock = $this->lockFactory->createLock(self::class.'-'.$userId.'-'.$calendarId, 120);

        if (!$lock->acquire()) {
            return; // Another worker is handling this pair right now
        }

        try {
            $now = time();

            // Protects against duplicate messages (e.g. the cron ran twice)
            if (!ReminderLog::isReminderDue($this->reminderLog->getLastSentAt($userId, $calendarId), $intervalDays, $now)) {
                return;
            }

            $pendingEvents = $this->pendingRegistrationProvider->getPendingEvents($userId, $calendar, $now);

            // Registrations processed in the meantime: no reminder
            if (empty($pendingEvents)) {
                return;
            }

            $tokens = $this->getTokens($user, $calendar, $pendingEvents);

            // Log first: better one missing reminder than a duplicate one
            $this->reminderLog->logReminder($userId, $calendarId, (string) $user->name, $intervalDays, $now);

            $receipts = $this->notificationCenter->sendNotification($notificationId, $tokens, $user->language ?: $this->sacevtLocale);

            foreach ($receipts as $receipt) {
                if (!$receipt->wasDelivered()) {
                    $this->contaoErrorLogger?->error(\sprintf(
                        'Event registration reminder for user ID %d and calendar ID %d could not be delivered: %s',
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
     * @param list<PendingEvent> $pendingEvents
     *
     * @return array<string, mixed>
     */
    private function getTokens(UserModel $user, CalendarModel $calendar, array $pendingEvents): array
    {
        return [
            'admin_email' => (string) $this->framework->getAdapter(Config::class)->get('adminEmail'),
            'instructor_email' => trim((string) $user->email),
            'instructor_firstname' => (string) $user->firstname,
            'instructor_lastname' => (string) $user->lastname,
            'instructor_name' => (string) $user->name,
            'registrations' => trim($this->twig->render(self::TEMPLATE, ['events' => $pendingEvents])),
            'send_reminder_each' => (int) $calendar->sendReminderEach,
        ];
    }
}
