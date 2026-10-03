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

namespace Markocupic\SacEventToolBundle\Feature\MemberToUserSync\Command;

use Markocupic\SacEventToolBundle\Feature\MemberToUserSync\MemberToUserSync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Syncs tl_member -> tl_user manually, the same way as MemberToUserSyncCron.
 *
 * Usage:
 *   php vendor/bin/contao-console sacevt:member-to-user:sync
 *   php vendor/bin/contao-console sacevt:member-to-user:sync -v # with the detailed log lines
 *
 * See docs/features/member-to-user-sync.md
 */
#[AsCommand(
    name: 'sacevt:member-to-user:sync',
    description: 'Sync the backend users (tl_user) with the member data (tl_member).',
)]
class MemberToUserSyncCommand extends Command
{
    public function __construct(private readonly MemberToUserSync $memberToUserSync)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Sync tl_member -> tl_user');

        $this->memberToUserSync->run();
        $syncLog = $this->memberToUserSync->getSyncLog();

        // Optionally print the detailed log lines (-v)
        if ($output->isVerbose() && [] !== $syncLog['log']) {
            $io->section('Log');
            $io->listing($syncLog['log']);
        }

        $io->section('Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Processed', (string) $syncLog['processed']],
                ['Updates', (string) $syncLog['updates']],
                ['Disabled (sacMemberId set to 0)', (string) $syncLog['disabled']],
                ['Duration', $syncLog['duration'].' s'],
            ],
        );

        if ($syncLog['with_error']) {
            $io->error(\sprintf('The sync failed and has been rolled back: %s', $syncLog['exception']));

            return Command::FAILURE;
        }

        $io->success('Successfully synced tl_member -> tl_user.');

        return Command::SUCCESS;
    }
}
