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

namespace Markocupic\SacEventToolBundle\Tests\Feature\AutoPublishEvents\Command;

use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CalendarRunner;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Candidate;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Command\AutoPublishEventsPreviewCommand;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\PublishResult;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\SkippedEvent;
use Markocupic\SacEventToolBundle\Util\ReleaseLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AutoPublishEventsPreviewCommandTest extends ContaoTestCase
{
    public function testShowsTheEventsOfEveryUpcomingCalendarAsDryRun(): void
    {
        $dueDate = mktime(18, 0, 0, 12, 12, 2026);

        $runner = $this->createMock(CalendarRunner::class);
        $runner
            ->method('findUpcomingCalendarIds')
            ->willReturn([12])
        ;

        $runner
            ->expects($this->once())
            ->method('run')
            ->with(12, true)
            ->willReturnCallback(
                static function () use ($dueDate): PublishResult {
                    $result = new PublishResult(12, 'Kurse 2027', true, $dueDate);
                    $result->addPublished(new Candidate(12, 12, 'Sidelhorn', new ReleaseLevel(13, 3, 'FS 3'), new ReleaseLevel(14, 4, 'FS 4')));
                    $result->addPublished(new Candidate(13, 12, 'Pilatus', new ReleaseLevel(13, 3, 'FS 3'), new ReleaseLevel(14, 4, 'FS 4')));
                    // Other release level system in the same calendar
                    $result->addPublished(new Candidate(20, 12, 'Lawinenkurs', new ReleaseLevel(22, 2, 'FS 2'), new ReleaseLevel(23, 3, 'FS 3')));
                    $result->addSkipped(new SkippedEvent(14, 'Titlis', SkippedEvent::REASON_OUTSIDE_VALID_TIME_PERIOD));

                    return $result;
                },
            )
        ;

        $tester = new CommandTester(new AutoPublishEventsPreviewCommand($this->mockContaoFramework(), $runner));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));

        // Remove the line breaks and padding of the console output
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());

        $this->assertStringContainsString('Kalender ID 12 "Kurse 2027"', $display);
        $this->assertStringContainsString('Am 12.12.2026 18:00 werden folgende Events von FS 3 auf FS 4 hochgestuft und veröffentlicht:', $display);
        $this->assertStringContainsString('* Event ID 12 Sidelhorn * Event ID 13 Pilatus', $display);
        $this->assertStringContainsString('von FS 2 auf FS 3', $display);
        $this->assertStringContainsString('Event ID 14 Titlis: Das Startdatum liegt ausserhalb der Kalender-Zeitspanne.', $display);
    }

    public function testNoUpcomingCalendar(): void
    {
        $runner = $this->createMock(CalendarRunner::class);
        $runner
            ->method('findUpcomingCalendarIds')
            ->willReturn([])
        ;

        $runner
            ->expects($this->never())
            ->method('run')
        ;

        $tester = new CommandTester(new AutoPublishEventsPreviewCommand($this->mockContaoFramework(), $runner));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Kein Kalender mit einem Stichtag in der Zukunft.', $tester->getDisplay());
    }
}
