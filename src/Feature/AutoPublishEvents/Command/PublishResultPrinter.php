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

use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CalendarRunner;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Candidate;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\PublishResult;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\SkippedEvent;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console output of a PublishResult, shared by the preview and the run command.
 * A dry run is worded in the future tense ("werden"), a real run in the past tense ("wurden").
 */
final class PublishResultPrinter
{
    public static function print(SymfonyStyle $io, PublishResult $result): void
    {
        $io->section(\sprintf('Kalender ID %d "%s"', $result->calendarId, $result->calendarTitle));

        $date = date('d.m.Y H:i', $result->dueDate);

        if ([] === $result->getPublished()) {
            $io->text($result->dryRun
                ? \sprintf('Am %s wird kein Event hochgestuft.', $date)
                : \sprintf('Es wurde kein Event hochgestuft (Stichtag %s).', $date));
        }

        // A calendar can contain events with different release level systems: one list per level change
        foreach (self::groupByLevelChange($result->getPublished()) as [$fromLevel, $toLevel, $candidates]) {
            $io->text($result->dryRun
                ? \sprintf('Am %s werden folgende Events von FS %d auf FS %d hochgestuft und veröffentlicht:', $date, $fromLevel, $toLevel)
                : \sprintf('Folgende Events wurden von FS %d auf FS %d hochgestuft und veröffentlicht:', $fromLevel, $toLevel));

            $io->listing(array_map(
                static fn (Candidate $candidate): string => \sprintf('Event ID %d %s', $candidate->eventId, $candidate->title),
                $candidates,
            ));
        }

        if ([] !== $result->getSkipped()) {
            $io->text($result->dryRun ? 'Folgende Events werden nicht hochgestuft:' : 'Folgende Events wurden nicht hochgestuft:');
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
    private static function groupByLevelChange(array $candidates): array
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
            SkippedEvent::REASON_CHANGED_MEANWHILE => 'Der Event wurde in der Zwischenzeit geändert.',
            SkippedEvent::REASON_ERROR => 'Fehler: '.$skippedEvent->detail,
            default => CalendarRunner::describeReason($skippedEvent),
        };
    }
}
