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

namespace Markocupic\SacEventToolBundle\Tests\Feature\AutoPublishEvents\DataContainer;

use Contao\Config;
use Contao\Date;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\DataContainer\Calendar;

final class CalendarTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TL_LANG']['tl_calendar']['autoPublishEventsStatusPending'] = 'pending';
        $GLOBALS['TL_LANG']['tl_calendar']['autoPublishEventsStatusExecuted'] = 'executed at %s for %s';
        $GLOBALS['TL_LANG']['tl_calendar']['autoPublishEventsStatusExecutedForOtherDate'] = 'other: executed at %s for %s';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_LANG']['tl_calendar']);

        parent::tearDown();
    }

    public function testNoRunYet(): void
    {
        $this->assertSame('pending', $this->createCalendar()->getStatusText(['autoPublishEventsDate' => 200]));
    }

    public function testExecutedForTheCurrentDueDate(): void
    {
        $text = $this->createCalendar()->getStatusText([
            'autoPublishEventsDate' => 200,
            'autoPublishEventsExecutedAt' => 300,
            'autoPublishEventsExecutedForDate' => 200,
        ]);

        $this->assertSame('executed at #300 for #200', $text);
    }

    public function testExecutedForAnOtherDueDate(): void
    {
        $text = $this->createCalendar()->getStatusText([
            'autoPublishEventsDate' => 500,
            'autoPublishEventsExecutedAt' => 300,
            'autoPublishEventsExecutedForDate' => 200,
        ]);

        $this->assertSame('other: executed at #300 for #200', $text);
    }

    private function createCalendar(): Calendar
    {
        $config = $this->mockAdapter(['get']);
        $config
            ->method('get')
            ->willReturn('d.m.Y H:i')
        ;

        $date = $this->mockAdapter(['parse']);
        $date
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $timestamp): string => '#'.$timestamp)
        ;

        return new Calendar($this->mockContaoFramework([Config::class => $config, Date::class => $date]));
    }
}
