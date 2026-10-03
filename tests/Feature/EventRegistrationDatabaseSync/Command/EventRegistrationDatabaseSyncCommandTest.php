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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventRegistrationDatabaseSync\Command;

use Markocupic\SacEventToolBundle\Feature\EventRegistrationDatabaseSync\Command\EventRegistrationDatabaseSyncCommand;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationDatabaseSync\SyncEventRegistrationDatabase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class EventRegistrationDatabaseSyncCommandTest extends TestCase
{
    public function testRunsTheSyncAndShowsTheSummary(): void
    {
        $tester = new CommandTester(new EventRegistrationDatabaseSyncCommand($this->createSync([
            'processed_registrations' => 40,
            'processed_members' => 25,
            'updates' => 1,
            'log' => ['Update contact data for event registration ID 7 with member Anna Muster.'],
            'duration' => 1,
            'with_error' => false,
            'exceptions' => [],
        ])));

        $this->assertSame(Command::SUCCESS, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('registration ID 7 with member Anna Muster', $display);
        $this->assertMatchesRegularExpression('/Processed registrations\s+40/', $display);
    }

    public function testFailsIfTheSyncFailed(): void
    {
        $tester = new CommandTester(new EventRegistrationDatabaseSyncCommand($this->createSync([
            'processed_registrations' => 3,
            'processed_members' => 2,
            'updates' => 0,
            'log' => [],
            'duration' => 0,
            'with_error' => true,
            'exceptions' => ['Deadlock found'],
        ])));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Deadlock found', $tester->getDisplay());
    }

    private function createSync(array $syncLog): SyncEventRegistrationDatabase
    {
        $sync = $this->createMock(SyncEventRegistrationDatabase::class);
        $sync
            ->expects($this->once())
            ->method('run')
            ->willReturn($syncLog)
        ;

        return $sync;
    }
}
