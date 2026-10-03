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

namespace Markocupic\SacEventToolBundle\Feature\AutoPublishEvents;

/**
 * Result of one run for one calendar.
 * In a dry run, $published contains the events that would be published.
 */
final class PublishResult
{
    /**
     * @var list<Candidate>
     */
    private array $published = [];

    /**
     * @var list<SkippedEvent>
     */
    private array $skipped = [];

    public function __construct(
        public readonly int $calendarId,
        public readonly string $calendarTitle,
        public readonly bool $dryRun = false,
        public readonly int $dueDate = 0,
    ) {
    }

    public function addPublished(Candidate $candidate): void
    {
        $this->published[] = $candidate;
    }

    public function addSkipped(SkippedEvent $skippedEvent): void
    {
        $this->skipped[] = $skippedEvent;
    }

    /**
     * @return list<Candidate>
     */
    public function getPublished(): array
    {
        return $this->published;
    }

    /**
     * @return list<SkippedEvent>
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    public function countErrors(): int
    {
        return \count(array_filter($this->skipped, static fn (SkippedEvent $skippedEvent): bool => SkippedEvent::REASON_ERROR === $skippedEvent->reason));
    }
}
