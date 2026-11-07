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
use Markocupic\SacEventToolBundle\EventReleaseLevel\ReleaseLevel;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CalendarRunner;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Candidate;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Command\AutoPublishEventsRunCommand;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\PublishResult;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\SkippedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AutoPublishEventsRunCommandTest extends ContaoTestCase
{
    // 01.01.2025 00:00 UTC
    private const PAST_DUE_DATE = 1735689600;

    /**
     * Calls of CalendarRunner::run(): list of [calendarId, dryRun].
     *
     * @var list<array{0: int, 1: bool}>
     */
    private array $runs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->runs = [];
    }

    public function testNoDueCalendar(): void
    {
        $runner = $this->createRunner(dueCalendarIds: []);

        $tester = $this->execute($runner, [], interactive: false);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Kein Kalender ist fällig.', $tester->getDisplay());
        $this->assertSame([], $this->runs);
    }

    public function testPublishesAllDueCalendarsWithoutInteraction(): void
    {
        $runner = $this->createRunner(dueCalendarIds: [12, 13]);

        $tester = $this->execute($runner, [], interactive: false);
        $display = $this->normalize($tester->getDisplay());

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // First the preview of every calendar, then the real run
        $this->assertSame([[12, true], [13, true], [12, false], [13, false]], $this->runs);

        $this->assertStringContainsString('werden folgende Events von FS 3 auf FS 4 hochgestuft und veröffentlicht:', $display);
        $this->assertStringContainsString('Folgende Events wurden von FS 3 auf FS 4 hochgestuft und veröffentlicht: * Event ID 1 Sidelhorn', $display);
        $this->assertStringContainsString('Folgende Events wurden nicht hochgestuft: * Event ID 2 Titlis: Der Event wurde in der Zwischenzeit geändert.', $display);
        $this->assertStringNotContainsString('Der Stichtag liegt noch in der Zukunft.', $display);
    }

    public function testNothingIsChangedIfTheUserDoesNotConfirm(): void
    {
        $runner = $this->createRunner(dueCalendarIds: [12]);

        $tester = $this->execute($runner, [], interactive: true, inputs: ['no']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame([[12, true]], $this->runs);
        $this->assertStringContainsString('Abgebrochen. Es wurde nichts geändert.', $tester->getDisplay());
    }

    public function testPublishesIfTheUserConfirms(): void
    {
        $runner = $this->createRunner(dueCalendarIds: [12]);

        $tester = $this->execute($runner, [], interactive: true, inputs: ['yes']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame([[12, true], [12, false]], $this->runs);
    }

    public function testRunsOneCalendarBeforeItsDueDate(): void
    {
        $runner = $this->createRunner(dueCalendarIds: [], pendingCalendarIds: [20], dueDate: time() + 86400);
        $runner
            ->expects($this->never())
            ->method('findDueCalendarIds')
        ;

        $tester = $this->execute($runner, ['--calendar' => '20'], interactive: false);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame([[20, true], [20, false]], $this->runs);
        $this->assertStringContainsString('Der Stichtag liegt noch in der Zukunft.', $this->normalize($tester->getDisplay()));
    }

    public function testCalendarThatIsNotPendingIsRejected(): void
    {
        $runner = $this->createRunner(dueCalendarIds: [], pendingCalendarIds: []);

        $tester = $this->execute($runner, ['--calendar' => '20'], interactive: false);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame([], $this->runs);
        $this->assertStringContainsString('Kalender ID 20 kann nicht ausgeführt werden', $this->normalize($tester->getDisplay()));
    }

    public function testInvalidCalendarOption(): void
    {
        $runner = $this->createRunner(dueCalendarIds: []);

        $tester = $this->execute($runner, ['--calendar' => 'abc'], interactive: false);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame([], $this->runs);
    }

    public function testErrorInOneCalendarDoesNotStopTheOthers(): void
    {
        $runner = $this->createRunner(dueCalendarIds: [12, 13], failingCalendarId: 12);

        $tester = $this->execute($runner, [], interactive: false);
        $display = $this->normalize($tester->getDisplay());

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame([[12, true], [13, true], [12, false], [13, false]], $this->runs);
        $this->assertStringContainsString('Kalender ID 12: Database error', $display);
        $this->assertStringContainsString('Es sind Fehler aufgetreten.', $display);
    }

    /**
     * @param list<int> $dueCalendarIds
     * @param list<int> $pendingCalendarIds
     */
    private function createRunner(array $dueCalendarIds, array $pendingCalendarIds = [], int $dueDate = self::PAST_DUE_DATE, int|null $failingCalendarId = null): CalendarRunner&MockObject
    {
        $runner = $this->createMock(CalendarRunner::class);
        $runner
            ->method('findDueCalendarIds')
            ->willReturn($dueCalendarIds)
        ;

        $runner
            ->method('isPending')
            ->willReturnCallback(static fn (int $calendarId): bool => \in_array($calendarId, $pendingCalendarIds, true))
        ;

        $runner
            ->method('run')
            ->willReturnCallback(
                function (int $calendarId, bool $dryRun = false) use ($dueDate, $failingCalendarId): PublishResult {
                    $this->runs[] = [$calendarId, $dryRun];

                    if (!$dryRun && $calendarId === $failingCalendarId) {
                        throw new \RuntimeException('Database error');
                    }

                    $result = new PublishResult($calendarId, 'Touren 2027', $dryRun, $dueDate);
                    $result->addPublished(new Candidate(1, $calendarId, 'Sidelhorn', new ReleaseLevel(13, 3, 'FS 3'), new ReleaseLevel(14, 4, 'FS 4')));

                    if (!$dryRun) {
                        $result->addSkipped(new SkippedEvent(2, 'Titlis', SkippedEvent::REASON_CHANGED_MEANWHILE));
                    }

                    return $result;
                },
            )
        ;

        return $runner;
    }

    /**
     * @param array<string, string> $options
     * @param list<string>          $inputs
     */
    private function execute(CalendarRunner $runner, array $options, bool $interactive, array $inputs = []): CommandTester
    {
        $tester = new CommandTester(new AutoPublishEventsRunCommand($this->mockContaoFramework(), $runner));
        $tester->setInputs($inputs);
        $tester->execute($options, ['interactive' => $interactive]);

        return $tester;
    }

    /**
     * Removes the line breaks and padding of the console output.
     */
    private function normalize(string $display): string
    {
        return preg_replace('/\s+/', ' ', $display);
    }
}
