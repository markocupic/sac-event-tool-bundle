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

namespace Markocupic\SacEventToolBundle\Tests\Feature\MemberToUserSync\Command;

use Markocupic\SacEventToolBundle\Feature\MemberToUserSync\Command\MemberToUserSyncCommand;
use Markocupic\SacEventToolBundle\Feature\MemberToUserSync\MemberToUserSync;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class MemberToUserSyncCommandTest extends TestCase
{
    public function testRunsTheSyncAndShowsTheSummary(): void
    {
        $tester = new CommandTester(new MemberToUserSyncCommand($this->createSync([
            'log' => ['Synced tl_user with tl_member. Updated tl_user (Anna Muster [SAC Member-ID: 123]).'],
            'processed' => 12,
            'updates' => 1,
            'disabled' => 0,
            'duration' => 0.2,
            'with_error' => false,
            'exception' => '',
        ])));

        $this->assertSame(Command::SUCCESS, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Updated tl_user (Anna Muster', $display);
        $this->assertMatchesRegularExpression('/Processed\s+12/', $display);
    }

    public function testFailsIfTheSyncFailed(): void
    {
        $tester = new CommandTester(new MemberToUserSyncCommand($this->createSync([
            'log' => [],
            'processed' => 3,
            'updates' => 0,
            'disabled' => 0,
            'duration' => 0.1,
            'with_error' => true,
            'exception' => 'Deadlock found',
        ])));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Deadlock found', $tester->getDisplay());
    }

    private function createSync(array $syncLog): MemberToUserSync
    {
        $sync = $this->createMock(MemberToUserSync::class);
        $sync
            ->expects($this->once())
            ->method('run')
        ;

        $sync
            ->method('getSyncLog')
            ->willReturn($syncLog)
        ;

        return $sync;
    }
}
