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

namespace Markocupic\SacEventToolBundle\Tests\Feature\InstructorPostEventTaskReminder;

use Contao\CalendarEventsModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\Task\PostEventTaskInterface;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\TaskEvaluator;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\TaskItem;

final class TaskEvaluatorTest extends ContaoTestCase
{
    public function testReturnsOnlySupportedAndOpenTasksInGivenOrder(): void
    {
        $evaluator = new TaskEvaluator([
            $this->createTask('first', supports: true, open: true),
            $this->createTask('not_supported', supports: false, open: true),
            $this->createTask('done', supports: true, open: false),
            $this->createTask('second', supports: true, open: true),
        ]);

        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 42]);

        $items = $evaluator->getOpenTasks($event);

        $this->assertSame(['first', 'second'], array_map(static fn (TaskItem $item): string => $item->name, $items));
        $this->assertSame('Label first', $items[0]->label);
        $this->assertSame('https://example.org/first/42', $items[0]->url);
    }

    public function testDoesNotCallIsOpenForUnsupportedTasks(): void
    {
        $task = $this->createMock(PostEventTaskInterface::class);
        $task
            ->method('supports')
            ->willReturn(false)
        ;

        $task
            ->expects($this->never())
            ->method('isOpen')
        ;

        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 42]);

        $evaluator = new TaskEvaluator([$task]);

        $this->assertSame([], $evaluator->getOpenTasks($event));
    }

    public function testReturnsTaskNames(): void
    {
        $evaluator = new TaskEvaluator(new \ArrayIterator([
            $this->createTask('a', true, true),
            $this->createTask('b', true, true),
        ]));

        $this->assertSame(['a', 'b'], $evaluator->getTaskNames());
    }

    private function createTask(string $name, bool $supports, bool $open): PostEventTaskInterface
    {
        $task = $this->createMock(PostEventTaskInterface::class);
        $task
            ->method('getName')
            ->willReturn($name)
        ;

        $task
            ->method('supports')
            ->willReturn($supports)
        ;

        $task
            ->method('isOpen')
            ->willReturn($open)
        ;

        $task
            ->method('getLabel')
            ->willReturn('Label '.$name)
        ;
        $task
            ->method('getUrl')
            ->willReturnCallback(static fn (CalendarEventsModel $event): string => 'https://example.org/'.$name.'/'.$event->id);

        return $task;
    }
}
