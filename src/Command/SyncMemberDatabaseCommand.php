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
    public function __construct(
        private readonly SyncMemberDatabase $syncMemberDatabase,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('SAC member database sync');

        try {
            // Run the database sync
            $this->syncMemberDatabase->run();
        } catch (\Throwable $e) {
            $io->error(\sprintf('The member database sync failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        // Get the log
        $arrLog = $this->syncMemberDatabase->getSyncLog();

        // Optionally print the detailed log lines (-v)
        if ($output->isVerbose() && !empty($arrLog['log'])) {
            $io->section('Log');
            $io->listing((array) $arrLog['log']);
        }

        $io->section('Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Processed', (string) ($arrLog['processed'] ?? 0)],
                ['Inserts', (string) ($arrLog['inserts'] ?? 0)],
                ['Updates', (string) ($arrLog['updates'] ?? 0)],
                ['Duration', ($arrLog['duration'] ?? 0).' s'],
            ],
        );

        if (!empty($arrLog['with_error'])) {
            $io->error(\sprintf(
                'The db sync finished with errors: %s',
                $arrLog['exception'] ?? 'Unknown error.',
            ));

            return Command::FAILURE;
        }

        $io->success('Successfully executed the db sync.');

        return Command::SUCCESS;
    }
}
