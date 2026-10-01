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

namespace Markocupic\SacEventToolBundle\InstructorPostEventTaskReminder;

/**
 * An event with at least one open task, seen from the perspective of one recipient.
 */
final readonly class OpenTask
{
    public const ROLE_INSTRUCTOR = 'instructor';

    public const ROLE_REGISTRATION_COORDINATOR = 'registration_coordinator';

    /**
     * @param list<TaskItem> $tasks
     */
    public function __construct(
        public int $eventId,
        public string $title,
        public string $eventType,
        public int $endDate,
        public string $role,
        public array $tasks,
    ) {
    }

    public function withRole(string $role): self
    {
        return new self($this->eventId, $this->title, $this->eventType, $this->endDate, $role, $this->tasks);
    }

    public function countTasks(): int
    {
        return \count($this->tasks);
    }
}
