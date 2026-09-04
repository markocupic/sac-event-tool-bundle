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

namespace Markocupic\SacEventToolBundle\Command;

use Markocupic\SacEventToolBundle\Database\SyncMember\SyncLogger;
use Markocupic\SacEventToolBundle\Database\SyncMemberDatabase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirror/Update tl_member from SAC Member Database Zentralverband Bern.
 * Unidirectional sync: SAC Member Database Zentralverband Bern -> tl_member.
 *
 * Usage:
 *   php bin/console sac:member-database:sync
 */
#[AsCommand(
    name: 'sacevt:member-database:sync',
    description: 'Sync the Contao member database (tl_member) with the SAC member database of the Zentralverband Bern.',
)]
class SyncMemberDatabaseCommand extends Command
{
    public function __construct(private readonly SyncMemberDatabase $syncMemberDatabase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('SAC member database sync');

        try {
            // Run the database sync
            $syncLogger = new SyncLogger();
            $this->syncMemberDatabase->run($syncLogger);

            $e = $syncLogger->getException();

            if ($e) {
                throw $e;
            }
        } catch (\Throwable $e) {
            $io->error(\sprintf('The member database sync failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        // Optionally print the detailed log lines (-v)
        if ($output->isVerbose() && $syncLogger->hasMessages()) {
            $io->section('Log');
            $io->listing($syncLogger->getUpdateMessages());
            $io->listing($syncLogger->getInsertMessages());
            $io->listing($syncLogger->getDisabledMessages());
        }

        $io->section('Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Processed', (string) $syncLogger->toArray()['countProcessed']],
                ['Updates', (string) $syncLogger->toArray()['countUpdates']],
                ['Inserts', (string) $syncLogger->toArray()['countInserts']],
                ['Disabled', (string) $syncLogger->toArray()['countDisabled']],
                ['Duration', $syncLogger->getDuration().' s'],
            ],
        );

        $io->success('Successfully executed the db sync.');

        return Command::SUCCESS;
    }
}
