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

namespace Markocupic\SacEventToolBundle\Tests\Feature\InstructorPostEventTaskReminder\Task;

use Contao\CalendarEventsModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\Task\TourReportTask;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TourReportTaskTest extends ContaoTestCase
{
    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(string $eventType, bool $expected): void
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['eventType' => $eventType]);

        $this->assertSame($expected, $this->createTask()->supports($event));
    }

    public static function supportsProvider(): iterable
    {
        yield 'tour' => ['tour', true];
        yield 'last minute tour' => ['lastMinuteTour', true];
        yield 'course' => ['course', false];
        yield 'general event' => ['generalEvent', false];
    }

    public function testIsOpenWithoutReport(): void
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['eventType' => 'tour', 'filledInEventReportForm' => false]);

        $this->assertTrue($this->createTask()->isOpen($event));
    }

    public function testIsDoneWithReport(): void
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['eventType' => 'tour', 'filledInEventReportForm' => true]);

        $this->assertFalse($this->createTask()->isOpen($event));
    }

    public function testUrlPointsToTheTourReportForm(): void
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 42, 'eventType' => 'tour']);

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with(
                'contao_backend',
                ['do' => 'calendar', 'table' => 'tl_calendar_events', 'act' => 'edit', 'call' => 'writeTourReport', 'id' => 42],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://example.org/contao?report')
        ;

        $this->assertSame('https://example.org/contao?report', $this->createTask($router)->getUrl($event));
    }

    private function createTask(RouterInterface|null $router = null): TourReportTask
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id)
        ;

        return new TourReportTask($router ?? $this->createMock(RouterInterface::class), $translator);
    }
}
