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

namespace Markocupic\SacEventToolBundle\Tests\Util;

use Markocupic\SacEventToolBundle\Util\EventDateUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventDateUtilTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Zurich');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);

        parent::tearDown();
    }

    /**
     * @dataProvider effectiveEndDateProvider
     */
    #[DataProvider('effectiveEndDateProvider')]
    public function testEffectiveEndDate(string $start, string $end, string $eventState, string|null $rescheduled, string|null $expected): void
    {
        $result = EventDateUtil::getEffectiveEndDate(
            strtotime($start),
            strtotime($end),
            $eventState,
            null === $rescheduled ? null : strtotime($rescheduled),
        );

        $this->assertSame(null === $expected ? null : strtotime($expected), $result);
    }

    public static function effectiveEndDateProvider(): iterable
    {
        yield 'normal event: endDate' => ['2026-01-10', '2026-01-11', '', null, '2026-01-11'];
        yield 'fully booked event: endDate' => ['2026-01-10', '2026-01-11', 'event_fully_booked', null, '2026-01-11'];
        yield 'rescheduled without new date: skipped' => ['2026-01-10', '2026-01-11', 'event_rescheduled', null, null];
        yield 'one-day event rescheduled' => ['2026-01-10', '2026-01-10', 'event_rescheduled', '2026-01-24', '2026-01-24'];
        yield 'two-day event rescheduled' => ['2026-01-10', '2026-01-11', 'event_rescheduled', '2026-01-24', '2026-01-25'];
        yield 'two weekends rescheduled' => ['2026-01-10', '2026-01-18', 'event_rescheduled', '2026-02-07', '2026-02-15'];
        yield 'rescheduled across DST change' => ['2026-03-21', '2026-03-22', 'event_rescheduled', '2026-03-28', '2026-03-29'];
        yield 'original period across DST change' => ['2026-10-24', '2026-10-26', 'event_rescheduled', '2026-11-07', '2026-11-09'];
    }

    public function testEffectiveStartDate(): void
    {
        $start = strtotime('2026-01-10');
        $rescheduled = strtotime('2026-01-24');

        $this->assertSame($start, EventDateUtil::getEffectiveStartDate($start, '', null), 'Normal event: startDate');
        $this->assertSame($start, EventDateUtil::getEffectiveStartDate($start, 'event_fully_booked', $rescheduled), 'Not rescheduled: startDate');
        $this->assertSame($rescheduled, EventDateUtil::getEffectiveStartDate($start, 'event_rescheduled', $rescheduled), 'Rescheduled: new start date');
        $this->assertSame($start, EventDateUtil::getEffectiveStartDate($start, 'event_rescheduled', null), 'Rescheduled without new date: startDate');
    }
}
