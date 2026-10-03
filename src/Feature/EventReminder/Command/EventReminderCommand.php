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

namespace Markocupic\SacEventToolBundle\Feature\EventReminder\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Cron\EventReminderCron;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Messenger\Message\SendEventReminderMessage;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Messenger\MessageHandler\SendEventReminderHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Triggers the event reminder manually.
 *
 * Usage:
 *   php vendor/bin/contao-console sacevt:event-reminder # like the cron: due events, messages for the Messenger worker
 *   php vendor/bin/contao-console sacevt:event-reminder --sync # due events, send immediately (no worker needed)
 *   php vendor/bin/contao-console sacevt:event-reminder --dry-run # only list the due events
 *   php vendor/bin/contao-console sacevt:event-reminder --event=123 # only event 123, regardless of the date, send immediately
 *
 * The handler still checks everything except the date: already sent (eventReminderSentAt),
 * reminder enabled in the calendar, notification set, accepted participants, recipient with email address.
 *
 * See docs/features/event-reminder.md
 */
#[AsCommand(
    name: 'sacevt:event-reminder',
    description: 'Triggers the event reminder: sends the reminder for the due events (or for one event with --event).',
)]
class EventReminderCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventReminderCron $eventReminderCron,
        private readonly MessageBusInterface $messageBus,
        private readonly SendEventReminderHandler $sendEventReminderHandler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('event', null, InputOption::VALUE_REQUIRED, 'Only this event (ID), regardless of the date. Sends immediately.')
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Send immediately instead of dispatching messages for the Messenger worker.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only list the events, send nothing.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        $io->title('Event-Erinnerung');

        $dryRun = (bool) $input->getOption('dry-run');
        $singleEventId = null !== $input->getOption('event') ? (int) $input->getOption('event') : null;

        // A single event is always sent immediately, so the result can be shown
        $sync = null !== $singleEventId || $input->getOption('sync');

        $eventIds = null !== $singleEventId ? [$singleEventId] : $this->eventReminderCron->findDueEventIds();

        if ([] === $eventIds) {
            $io->text('Heute ist für keinen Event eine Erinnerung fällig.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->text(null !== $singleEventId ? 'Event:' : 'Folgende Events sind heute fällig:');
            $io->listing(array_map($this->describeEvent(...), $eventIds));

            return Command::SUCCESS;
        }

        if (!$sync) {
            foreach ($eventIds as $eventId) {
                $this->messageBus->dispatch(new SendEventReminderMessage($eventId));
            }

            $io->text('Für folgende Events wurde eine Message an den Messenger-Worker übergeben:');
            $io->listing(array_map($this->describeEvent(...), $eventIds));
            $io->note('Versendet wird, sobald der Messenger-Worker die Messages verarbeitet. Sofort versenden: --sync');

            return Command::SUCCESS;
        }

        $sent = [];
        $notSent = [];

        foreach ($eventIds as $eventId) {
            $sentAtBefore = $this->getSentAt($eventId);

            ($this->sendEventReminderHandler)(new SendEventReminderMessage($eventId));

            // The handler sets eventReminderSentAt right before sending
            if (0 === $sentAtBefore && $this->getSentAt($eventId) > 0) {
                $sent[] = $this->describeEvent($eventId);
            } else {
                $notSent[] = $this->describeEvent($eventId).(null !== $sentAtBefore && $sentAtBefore > 0 ? ': bereits versendet am '.date('d.m.Y H:i', $sentAtBefore) : '');
            }
        }

        if ([] !== $sent) {
            $io->text('Erinnerung versendet:');
            $io->listing($sent);
        }

        if ([] !== $notSent) {
            $io->text('Keine Erinnerung versendet:');
            $io->listing($notSent);
            $io->note('Gründe: bereits versendet, Event nicht gefunden, Erinnerung im Kalender ausgeschaltet, keine Benachrichtigung gewählt, keine akzeptierten Teilnehmer oder keine Kontaktperson mit E-Mail-Adresse (siehe Contao-Log).');
        }

        return Command::SUCCESS;
    }

    private function describeEvent(int $eventId): string
    {
        $title = $this->connection->fetchOne('SELECT title FROM tl_calendar_events WHERE id = ?', [$eventId]);

        if (false === $title) {
            return \sprintf('Event ID %d (nicht gefunden)', $eventId);
        }

        // Titles are stored input-encoded (e.g. &#40; for an opening bracket)
        return \sprintf('Event ID %d %s', $eventId, StringUtil::revertInputEncoding((string) $title));
    }

    private function getSentAt(int $eventId): int|null
    {
        $sentAt = $this->connection->fetchOne('SELECT eventReminderSentAt FROM tl_calendar_events WHERE id = ?', [$eventId]);

        return false === $sentAt ? null : (int) $sentAt;
    }
}
