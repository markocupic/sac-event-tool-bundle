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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationDatabaseSync\Command;

use Markocupic\SacEventToolBundle\Feature\EventRegistrationDatabaseSync\SyncEventRegistrationDatabase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Syncs the member data (tl_member) into the event registrations (tl_calendar_events_member)
 * manually, the same way as EventRegistrationDatabaseSyncCron.
 *
 * Usage:
 *   php vendor/bin/contao-console sacevt:event-registration-database:sync
 *   php vendor/bin/contao-console sacevt:event-registration-database:sync -v # with the detailed log lines
 *
 * See docs/features/event-registration-database-sync.md
 */
#[AsCommand(
    name: 'sacevt:event-registration-database:sync',
    description: 'Sync the event registrations (tl_calendar_events_member) with the member data (tl_member).',
)]
class EventRegistrationDatabaseSyncCommand extends Command
{
    public function __construct(private readonly SyncEventRegistrationDatabase $syncEventRegistrationDatabase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Sync tl_member -> tl_calendar_events_member');

        $syncLog = $this->syncEventRegistrationDatabase->run();

        // Optionally print the detailed log lines (-v)
        if ($output->isVerbose() && [] !== $syncLog['log']) {
            $io->section('Log');
            $io->listing($syncLog['log']);
        }

        $io->section('Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Processed members', (string) $syncLog['processed_members']],
                ['Processed registrations', (string) $syncLog['processed_registrations']],
                ['Updates', (string) $syncLog['updates']],
                ['Duration', $syncLog['duration'].' s'],
            ],
        );

        if ($syncLog['with_error']) {
            $io->error(\sprintf('The sync failed and has been rolled back: %s', implode(' ', $syncLog['exceptions'])));

            return Command::FAILURE;
        }

        $io->success('Successfully synced tl_member -> tl_calendar_events_member.');

        return Command::SUCCESS;
    }
}
