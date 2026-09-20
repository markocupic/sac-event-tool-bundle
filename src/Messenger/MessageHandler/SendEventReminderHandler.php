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

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Events;
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Messenger\Message\SendEventReminderMessage;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;

/**
 * Sends ONE reminder e-mail per event (to save e-mail quota):
 * To/Reply-To: main instructor, CC: co-instructors, BCC: all accepted participants.
 */
#[AsMessageHandler]
readonly class SendEventReminderHandler
{
    public function __construct(
        private CalendarEventsUtil $calendarEventsUtil,
        private Connection $connection,
        private ContaoFramework $framework,
        private NotificationCenter $notificationCenter,
        private TranslatorInterface $translator,
        private string $sacevtLocale,
        private LoggerInterface|null $contaoErrorLogger,
    ) {
    }

    public function __invoke(SendEventReminderMessage $message): void
    {
        $this->framework->initialize();

        $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($message->getEventId());

        // Event deleted or reminder already sent (e.g., cron ran twice): nothing to do
        if (null === $event || $event->eventReminderSentAt > 0) {
            return;
        }

        $calendar = $this->framework->getAdapter(CalendarModel::class)->findById($event->pid);

        if (!$calendar->sendEventReminder) {
            return;
        }

        $notificationId = (int) ($calendar?->eventReminderNotification ?? 0);

        if ($notificationId < 1) {
            return;
        }

        $participants = $this->getParticipants($event);

        if (empty($participants)) {
            return;
        }

        $tokens = $this->getTokens($event, $calendar, $participants);

        if (empty($tokens['main_instructor_email'])) {
            // Not a transient error, retrying will not help
            $this->contaoErrorLogger?->error(\sprintf('Event reminder for event ID %d not sent: no main instructor with email address.', $event->id));

            return;
        }

        $affected = $this->connection->update(
            'tl_calendar_events',
            ['eventReminderSentAt' => time()],
            ['id' => $event->id, 'eventReminderSentAt' => 0],
        );

        if (0 === $affected) {
            return; // Another worker was faster
        }

        $receipts = $this->notificationCenter->sendNotification($notificationId, $tokens, $this->sacevtLocale);

        $errors = [];

        if (!$receipts->wereAllDelivered()) {
            foreach ($receipts as $receipt) {
                if (!$receipt->wasDelivered()) {
                    $errors[] = $receipt->getException()?->getMessage() ?? 'Unknown event reminder error.';
                }
            }
        }

        foreach ($errors as $error) {
            $this->contaoErrorLogger?->error($error);
        }
    }

    /**
     * @return array<int, array{firstname: string, lastname: string, email: string}>
     */
    private function getParticipants(CalendarEventsModel $event): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT firstname, lastname, email
             FROM tl_calendar_events_member
             WHERE eventId = ? AND stateOfSubscription = ? AND email != ?
             ORDER BY lastname, firstname',
            [$event->id, EventSubscriptionState::SUBSCRIPTION_ACCEPTED, ''],
        );
    }

    private function getTokens(CalendarEventsModel $event, CalendarModel $calendar, array $participants): array
    {
        $userAdapter = $this->framework->getAdapter(UserModel::class);
        $eventsAdapter = $this->framework->getAdapter(Events::class);

        // Instructors: main instructor first, disabled users excluded
        $instructors = [];

        foreach ($this->calendarEventsUtil->getInstructorsAsArray($event) as $userId) {
            $user = $userAdapter->findById($userId);

            if (null !== $user && !empty($user->email)) {
                $instructors[] = $user;
            }
        }

        $mainInstructor = $this->calendarEventsUtil->getMainInstructor($event);

        // Fallback: no main instructor flagged -> take the first instructor
        if (null === $mainInstructor || empty($mainInstructor->email)) {
            $mainInstructor = $instructors[0] ?? null;
        }

        $coInstructors = array_values(array_filter(
            $instructors,
            static fn (UserModel $user): bool => null === $mainInstructor || (int) $user->id !== (int) $mainInstructor->id,
        ));

        $tokens = [];

        $dataEvent = $event->row();

        foreach ($dataEvent as $key => $value) {
            $tokens['event_'.$this->camelToSnake($key)] = $value;
        }

        $tokens['event_event_type_translated'] = $this->translator->trans('MSC.'.$event->eventType, [], 'contao_default');
        $tokens['event_start_date'] = Date::parse('d.m.Y', $event->startDate);
        $tokens['event_end_date'] = Date::parse('d.m.Y', $event->endDate);
        $tokens['event_period'] = $this->calendarEventsUtil->getEventPeriod($event, 'd.m.Y', true, false);
        $tokens['event_duration'] = $this->calendarEventsUtil->getEventDuration($event);
        $tokens['event_meeting_point'] = (string) $event->meetingPoint;
        $tokens['event_leistungen'] = (string) $event->leistungen;
        $tokens['event_deregistration_limit'] = $event->allowDeregistration ? (string) $event->deregistrationLimit : '';
        $tokens['event_link_detail'] = $eventsAdapter->generateEventUrl($event, true);
        $tokens['main_instructor_name'] = $mainInstructor?->name ?? '';
        $tokens['main_instructor_email'] = $mainInstructor?->email ?? '';
        $tokens['main_instructor_phone'] = $mainInstructor?->phone ?? '';
        $tokens['main_instructor_mobile'] = $mainInstructor?->mobile ?? '';
        $tokens['instructors_names'] = implode(', ', array_map(static fn (UserModel $user): string => $user->name, $instructors));
        $tokens['instructors_email'] = implode(',', array_map(static fn (UserModel $user): string => $user->email, $instructors));
        $tokens['co_instructors_names'] = implode(', ', array_map(static fn (UserModel $user): string => $user->name, $coInstructors));
        $tokens['co_instructors_email'] = implode(',', array_map(static fn (UserModel $user): string => $user->email, $coInstructors));
        $tokens['participants_names'] = implode(', ', array_map(static fn (array $p): string => trim($p['firstname'].' '.$p['lastname']), $participants));
        $tokens['participants_email'] = implode(',', array_unique(array_map(static fn (array $p): string => strtolower(trim($p['email'])), $participants)));
        $tokens['participants_count'] = \count($participants);
        $tokens['reminder_offset_days'] = $calendar->eventReminderOffset;

        return array_map(static fn ($item) => \is_string($item) ? StringUtil::revertInputEncoding($item) : $item, $tokens);
    }

    private function camelToSnake(string $input): string
    {
        return new UnicodeString($input)->snake()->toString();
    }
}
