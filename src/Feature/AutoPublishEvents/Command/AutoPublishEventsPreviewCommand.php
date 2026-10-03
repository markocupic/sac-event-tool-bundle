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

namespace Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CalendarRunner;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Candidate;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\PublishResult;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\SkippedEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists all calendars with the auto publish enabled and a due date in the future (sorted by due date)
 * and shows which events would be promoted and published on the due date, based on the current state
 * of the events. Changes nothing.
 *
 * Publishing itself is done by the cron only (AutoPublishEventsCron).
 *
 * Usage:
 *   php vendor/bin/contao-console sacevt:auto-publish-events:preview
 *
 * See docs/features/auto-publish-events.md
 */
#[AsCommand(
    name: 'sacevt:auto-publish-events:preview',
    description: 'Shows which events will be promoted to the highest release level and published on the due date of each calendar. Changes nothing.',
)]
class AutoPublishEventsPreviewCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CalendarRunner $calendarRunner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        $io->title('Events automatisch veröffentlichen: Vorschau');

        $calendarIds = $this->calendarRunner->findUpcomingCalendarIds(new \DateTimeImmutable());

        if ([] === $calendarIds) {
            $io->text('Kein Kalender mit einem Stichtag in der Zukunft.');

            return Command::SUCCESS;
        }

        foreach ($calendarIds as $calendarId) {
            $this->printResult($io, $this->calendarRunner->run($calendarId, true));
        }

        $io->note('Massgebend ist der Stand der Events am Stichtag. Events, die bis dahin noch hoch- oder herabgestuft werden, sind in dieser Vorschau nicht berücksichtigt.');

        return Command::SUCCESS;
    }

    private function printResult(SymfonyStyle $io, PublishResult $result): void
    {
        $io->section(\sprintf('Kalender ID %d "%s"', $result->calendarId, $result->calendarTitle));

        $date = date('d.m.Y H:i', $result->dueDate);

        if ([] === $result->getPublished()) {
            $io->text(\sprintf('Am %s wird kein Event hochgestuft.', $date));
        }

        // A calendar can contain events with different release level systems: one list per level change
        foreach ($this->groupByLevelChange($result->getPublished()) as [$fromLevel, $toLevel, $candidates]) {
            $io->text(\sprintf('Am %s werden folgende Events von FS %d auf FS %d hochgestuft und veröffentlicht:', $date, $fromLevel, $toLevel));
            $io->listing(array_map(
                static fn (Candidate $candidate): string => \sprintf('Event ID %d %s', $candidate->eventId, $candidate->title),
                $candidates,
            ));
        }

        if ([] !== $result->getSkipped()) {
            $io->text('Folgende Events werden nicht hochgestuft:');
            $io->listing(array_map(
                static fn (SkippedEvent $skippedEvent): string => \sprintf('Event ID %d %s: %s', $skippedEvent->eventId, $skippedEvent->title, self::describeReason($skippedEvent)),
                $result->getSkipped(),
            ));
        }
    }

    /**
     * @param list<Candidate> $candidates
     *
     * @return list<array{0: int, 1: int, 2: list<Candidate>}>
     */
    private function groupByLevelChange(array $candidates): array
    {
        $groups = [];

        foreach ($candidates as $candidate) {
            $key = $candidate->currentLevel->level.'-'.$candidate->targetLevel->level;
            $groups[$key] ??= [$candidate->currentLevel->level, $candidate->targetLevel->level, []];
            $groups[$key][2][] = $candidate;
        }

        return array_values($groups);
    }

    private static function describeReason(SkippedEvent $skippedEvent): string
    {
        return match ($skippedEvent->reason) {
            SkippedEvent::REASON_INVALID_RELEASE_LEVEL => 'Die Freigabestufe gehört nicht zum Freigabestufen-System des Event-Typs.',
            SkippedEvent::REASON_OUTSIDE_VALID_TIME_PERIOD => 'Das Startdatum liegt ausserhalb der Kalender-Zeitspanne.',
            default => CalendarRunner::describeReason($skippedEvent),
        };
    }
}
