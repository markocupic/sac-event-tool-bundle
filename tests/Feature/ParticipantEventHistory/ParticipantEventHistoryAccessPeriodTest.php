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

namespace Markocupic\SacEventToolBundle\Tests\Feature\ParticipantEventHistory;

use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\ParticipantEventHistoryAccessPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParticipantEventHistoryAccessPeriodTest extends TestCase
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
     * @dataProvider accessEndProvider
     */
    #[DataProvider('accessEndProvider')]
    public function testAccessEnd(string $start, string $end, string $eventState, string|null $rescheduled, int $days, string|null $expected): void
    {
        $accessEnd = ParticipantEventHistoryAccessPeriod::getAccessEnd(
            strtotime($start),
            strtotime($end),
            $eventState,
            null === $rescheduled ? null : strtotime($rescheduled),
            $days,
        );

        $this->assertSame(null === $expected ? null : strtotime($expected), $accessEnd);
    }

    public static function accessEndProvider(): iterable
    {
        yield 'one-day tour' => ['2026-01-10 07:00', '2026-01-10 18:00', '', null, 14, '2026-01-24 23:59:59'];
        yield 'two-day tour' => ['2026-01-10 07:00', '2026-01-11 18:00', '', null, 14, '2026-01-25 23:59:59'];
        yield 'fully booked tour' => ['2026-01-10 07:00', '2026-01-10 18:00', 'event_fully_booked', '2026-02-01', 14, '2026-01-24 23:59:59'];
        yield 'rescheduled one-day tour' => ['2026-01-10 07:00', '2026-01-10 18:00', 'event_rescheduled', '2026-01-24', 14, '2026-02-07 23:59:59'];
        yield 'rescheduled two-day tour' => ['2026-01-10 07:00', '2026-01-11 18:00', 'event_rescheduled', '2026-01-24', 14, '2026-02-08 23:59:59'];
        yield 'rescheduled without new date: unlimited' => ['2026-01-10 07:00', '2026-01-10 18:00', 'event_rescheduled', null, 14, null];
        yield 'unlimited' => ['2026-01-10 07:00', '2026-01-10 18:00', '', null, 0, null];
        yield 'across DST change' => ['2026-03-20 07:00', '2026-03-20 18:00', '', null, 14, '2026-04-03 23:59:59'];
    }

    public function testDefaultAccessPeriodIs30Days(): void
    {
        $this->assertSame(30, ParticipantEventHistoryAccessPeriod::ACCESS_DAYS_AFTER_EVENT_END);
        $this->assertSame(
            strtotime('2026-02-09 23:59:59'),
            ParticipantEventHistoryAccessPeriod::getAccessEnd(strtotime('2026-01-10 07:00'), strtotime('2026-01-10 18:00'), '', null),
        );
    }
}
