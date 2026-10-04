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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventRegistrationCleanup\Command;

use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationCleanup\Command\EventRegistrationCleanupCommand;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationCleanup\EventRegistrationCleanup;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class EventRegistrationCleanupCommandTest extends ContaoTestCase
{
    public function testDryRunListsTheRegistrations(): void
    {
        $cleanup = $this->createMock(EventRegistrationCleanup::class);
        $cleanup
            ->expects($this->once())
            ->method('run')
            ->with(true)
            ->willReturn([
                'anonymized' => [['id' => '1', 'eventId' => '10', 'eventName' => 'Skitour &#40;Pilatus&#41;', 'firstname' => 'Anna', 'lastname' => 'Muster', 'sacMemberId' => '123456', 'contaoMemberId' => '0']],
                'deleted' => [],
            ])
        ;

        $tester = new CommandTester(new EventRegistrationCleanupCommand($this->mockContaoFramework(), $cleanup));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Dry-Run', $display);
        $this->assertStringContainsString('Würden anonymisiert: Anmeldungen gelöschter Mitglieder (1)', $display);
        $this->assertStringContainsString('Skitour (Pilatus)', $display);
        $this->assertStringContainsString('Anna Muster', $display);
        $this->assertStringContainsString('Keine.', $display);
    }
}
