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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists the pending feedback requests (not yet dispatched, not expired), oldest sending date first.
 */
#[AsCommand(name: 'sacevt:event-feedback:reminders', description: 'Lists the pending event feedback requests.')]
class EventFeedbackRemindersCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();

        $io = new SymfonyStyle($input, $output);
        $io->title('Pending event feedback requests');

        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.executionDate, r.expiration, m.firstname, m.lastname, m.sacMemberId, e.id AS eventId, e.title
            FROM tl_event_feedback_reminder r
            LEFT JOIN tl_calendar_events_member m ON m.id = r.pid
            LEFT JOIN tl_calendar_events e ON e.id = m.eventId
            WHERE r.dispatched = 0 AND r.expiration > ?
            ORDER BY r.executionDate, r.id',
            [time()],
        );

        if (empty($rows)) {
            $io->success('No pending feedback requests.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Sending date', 'Expires', 'Participant', 'Event'],
            array_map(
                static fn (array $row): array => [
                    date('d.m.Y H:i', (int) $row['executionDate']),
                    date('d.m.Y', (int) $row['expiration']),
                    trim($row['firstname'].' '.$row['lastname']).($row['sacMemberId'] ? ' ['.$row['sacMemberId'].']' : ''),
                    \sprintf('%s (ID %d)', html_entity_decode((string) $row['title']), $row['eventId']),
                ],
                $rows,
            ),
        );

        return Command::SUCCESS;
    }
}
