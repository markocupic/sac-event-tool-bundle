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

namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder;

use Contao\CalendarEventsModel;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Task\PostEventTaskInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Asks every registered task building block whether it applies to an event and is still open.
 *
 * Deliberately not "readonly" so it can be mocked in unit tests.
 */
class TaskEvaluator
{
    /**
     * @var list<PostEventTaskInterface>
     */
    private readonly array $tasks;

    /**
     * @param iterable<PostEventTaskInterface> $tasks sorted by priority (highest first)
     */
    public function __construct(#[AutowireIterator(PostEventTaskInterface::TAG)] iterable $tasks)
    {
        $this->tasks = array_values([...$tasks]);
    }

    /**
     * @return list<TaskItem> the open tasks of the event, in priority order
     */
    public function getOpenTasks(CalendarEventsModel $event): array
    {
        $items = [];

        foreach ($this->tasks as $task) {
            if (!$task->supports($event)) {
                continue;
            }

            if (!$task->isOpen($event)) {
                continue;
            }

            $items[] = new TaskItem($task->getName(), $task->getLabel(), $task->getUrl($event));
        }

        return $items;
    }

    /**
     * @return list<string> the names of all registered tasks, in priority order
     */
    public function getTaskNames(): array
    {
        return array_map(static fn (PostEventTaskInterface $task): string => $task->getName(), $this->tasks);
    }
}
