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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventOperations\EventListener;

use Contao\CalendarEventsModel;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ContaoBackendEventSearch\Event\BuildSearchHitOperationsEvent;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventListener\BackendEventSearchOperationsListener;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperation;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperationsBuilder;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperationState;
use Twig\Environment;

final class BackendEventSearchOperationsListenerTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The event search is an optional dependency
        if (!class_exists(BuildSearchHitOperationsEvent::class)) {
            $this->markTestSkipped('markocupic/contao-backend-event-search is not installed.');
        }
    }

    public function testRendersTheOperationsMenuOfTheEvent(): void
    {
        $calendarEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 5, 'title' => 'Piz Palü']);

        $builder = $this->createMock(EventOperationsBuilder::class);
        $builder
            ->expects($this->once())
            ->method('build')
            ->with($calendarEvent, 'sac_calendar')
            ->willReturn($this->createOperations())
        ;

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->once())
            ->method('render')
            ->with(
                '@MarkocupicSacEventTool/Backend/EventOperations/operations_menu.html.twig',
                $this->callback(
                    static fn (array $context): bool => true === $context['with_portal']
                        && 'Piz Palü' === $context['menu_title']
                        && array_keys($context['operations']) === array_map(static fn (string $name): string => 'sacevt-op--'.$name, EventOperationsBuilder::MENU_OPERATIONS),
                ),
            )
            ->willReturn('<div class="sacevt-op-menu-item"></div>')
        ;

        $listener = new BackendEventSearchOperationsListener($this->mockFramework($calendarEvent), $builder, $twig);

        $event = new BuildSearchHitOperationsEvent(5, ['id' => 5, 'title' => 'Piz Palü'], 'sac_calendar');
        $listener($event);

        $this->assertSame('<div class="sacevt-op-menu-item"></div>', $event->getOperationsHtml());

        // The operation "edit-event" is denied in createOperations(): the title is not linked
        $this->assertTrue($event->hasEditUrl());
        $this->assertNull($event->getEditUrl());
        $this->assertSame(EventOperationsBuilder::OPERATION_EDIT_EVENT, $event->getEditTitle());
    }

    public function testKeepsTheDefaultOperationsIfTheEventDoesNotExist(): void
    {
        $builder = $this->createMock(EventOperationsBuilder::class);
        $builder
            ->expects($this->never())
            ->method('build')
        ;

        $listener = new BackendEventSearchOperationsListener($this->mockFramework(null), $builder, $this->createMock(Environment::class));

        $event = new BuildSearchHitOperationsEvent(999, ['id' => 999], 'calendar');
        $listener($event);

        $this->assertNull($event->getOperationsHtml());
    }

    public function testIgnoresOtherEvents(): void
    {
        $builder = $this->createMock(EventOperationsBuilder::class);
        $builder
            ->expects($this->never())
            ->method('build')
        ;

        $listener = new BackendEventSearchOperationsListener($this->mockFramework(null), $builder, $this->createMock(Environment::class));
        $listener(new \stdClass());
    }

    private function mockFramework(CalendarEventsModel|null $calendarEvent): mixed
    {
        $stringUtil = $this->mockAdapter(['decodeEntities', 'restoreBasicEntities']);
        $stringUtil
            ->method('decodeEntities')
            ->willReturnArgument(0)
        ;

        $stringUtil
            ->method('restoreBasicEntities')
            ->willReturnArgument(0)
        ;

        return $this->mockContaoFramework([
            CalendarEventsModel::class => $this->mockConfiguredAdapter(['findById' => $calendarEvent]),
            StringUtil::class => $stringUtil,
        ]);
    }

    /**
     * @return array<string, EventOperation>
     */
    private function createOperations(): array
    {
        $operations = [];

        foreach ([EventOperationsBuilder::OPERATION_EDIT_EVENT, ...EventOperationsBuilder::MENU_OPERATIONS] as $name) {
            $operations[$name] = new EventOperation($name, EventOperationState::Denied, $name, $name, 'fa-solid');
        }

        return $operations;
    }
}
