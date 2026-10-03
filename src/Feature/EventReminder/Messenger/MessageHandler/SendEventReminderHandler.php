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

namespace Markocupic\SacEventToolBundle\Feature\EventReminder\Messenger\MessageHandler;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Events;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Messenger\Message\SendEventReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Participant;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Person;
use Markocupic\SacEventToolBundle\Feature\EventReminder\PersonProvider;
use Markocupic\SacEventToolBundle\Feature\EventReminder\RecipientResolver;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;

/**
 * Sends ONE reminder email per event (to save email quota):
 * To/Reply-To: registration coordinator or main instructor (##recipient_to##),
 * CC: all other active instructors (##recipient_cc##),
 * BCC: all accepted participants (##recipient_bcc##).
 *
 * The "sent" flag is set before sending, so a reminder is delivered at most once.
 * Delivery errors are logged, not retried.
 *
 * Deliberately not "readonly" so it can be mocked in unit tests (EventReminderCommandTest).
 */
#[AsMessageHandler]
class SendEventReminderHandler
{
    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly NotificationCenter $notificationCenter,
        private readonly PersonProvider $personProvider,
        private readonly RecipientResolver $recipientResolver,
        private readonly TranslatorInterface $translator,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoErrorLogger,
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

        if (null === $calendar || !$calendar->sendEventReminder) {
            return;
        }

        $notificationId = (int) ($calendar->eventReminderNotification ?? 0);

        if ($notificationId < 1) {
            return;
        }

        $participants = $this->personProvider->getParticipants($event);

        if (empty($participants)) {
            return;
        }

        $tokens = $this->getTokens($event, $calendar, $participants);

        if (empty($tokens['recipient_to'])) {
            // Not a transient error, retrying will not help
            $this->contaoErrorLogger?->error(\sprintf('Event reminder for event ID %d not sent: no recipient (main instructor or registration coordinator) with email address.', $event->id));

            return;
        }

        // Set the flag first: better one missing reminder than a duplicate one
        $affected = $this->connection->update(
            'tl_calendar_events',
            ['eventReminderSentAt' => time()],
            ['id' => $event->id, 'eventReminderSentAt' => 0],
        );

        if (0 === $affected) {
            return; // Another worker was faster
        }

        $receipts = $this->notificationCenter->sendNotification($notificationId, $tokens, $this->sacevtLocale);

        if ($receipts->wereAllDelivered()) {
            return;
        }

        foreach ($receipts as $receipt) {
            if (!$receipt->wasDelivered()) {
                $this->contaoErrorLogger?->error(\sprintf(
                    'Event reminder for event ID %d could not be delivered: %s',
                    $event->id,
                    $receipt->getException()?->getMessage() ?? 'Unknown event reminder error.',
                ));
            }
        }
    }

    /**
     * @param list<Participant> $participants
     */
    private function getTokens(CalendarEventsModel $event, CalendarModel $calendar, array $participants): array
    {
        $eventsAdapter = $this->framework->getAdapter(Events::class);

        $instructors = $this->personProvider->getInstructors($event);
        $mainInstructor = $this->recipientResolver->getMainInstructor($instructors, $this->personProvider->getFlaggedMainInstructor($event));
        $coInstructors = $this->recipientResolver->getCoInstructors($instructors, $mainInstructor);
        $registrationCoordinator = $this->personProvider->getRegistrationCoordinator($event);
        $contact = $this->recipientResolver->getContact($mainInstructor, $registrationCoordinator);
        $recipients = $this->recipientResolver->resolve($contact, $instructors, array_map(static fn (Participant $p): string => $p->email, $participants));

        $tokens = [];

        foreach ($event->row() as $key => $value) {
            $snake = $this->camelToSnake($key);
            $tokens["event_$snake"] = $value;
            $tokens["event_raw_$snake"] = $value;
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
        $tokens['instructors_names'] = implode(', ', array_map(static fn (Person $p): string => $p->name, $instructors));
        $tokens['instructors_email'] = implode(',', array_map(static fn (Person $p): string => $p->email, $instructors));
        $tokens['co_instructors_names'] = implode(', ', array_map(static fn (Person $p): string => $p->name, $coInstructors));
        $tokens['co_instructors_email'] = implode(',', array_map(static fn (Person $p): string => $p->email, $coInstructors));
        $tokens['participants_names'] = implode(', ', array_map(static fn (Participant $p): string => $p->getName(), $participants));
        $tokens['participants_email'] = implode(',', array_unique(array_map(static fn (Participant $p): string => strtolower(trim($p->email)), $participants)));
        $tokens['participants_count'] = \count($participants);
        $tokens['registration_coordinator_name'] = $registrationCoordinator?->name ?? '';
        $tokens['registration_coordinator_email'] = $registrationCoordinator?->email ?? '';
        $tokens['recipient_to'] = $recipients->to;
        $tokens['recipient_cc'] = $recipients->getCcAsString();
        $tokens['recipient_bcc'] = $recipients->getBccAsString();
        $tokens['reminder_offset_days'] = $calendar->eventReminderOffset;

        return array_map(static fn ($item) => \is_string($item) ? StringUtil::revertInputEncoding($item) : $item, $tokens);
    }

    private function camelToSnake(string $input): string
    {
        return new UnicodeString($input)->snake()->toString();
    }
}
