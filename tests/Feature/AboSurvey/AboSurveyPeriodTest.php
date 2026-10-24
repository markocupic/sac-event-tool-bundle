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

namespace Markocupic\SacEventToolBundle\Tests\Feature\AboSurvey;

use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AboSurveyPeriodTest extends TestCase
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
     * @dataProvider stateProvider
     */
    #[DataProvider('stateProvider')]
    public function testGetState(string $start, string $end, string $now, string $expected): void
    {
        $this->assertSame($expected, AboSurveyPeriod::getState(strtotime($start), strtotime($end), strtotime($now)));
    }

    public static function stateProvider(): iterable
    {
        yield 'before the start date' => ['2026-11-01', '2026-11-30', '2026-10-31 23:59:59', AboSurveyPeriod::STATE_NOT_STARTED];
        yield 'start date at midnight' => ['2026-11-01', '2026-11-30', '2026-11-01 00:00:00', AboSurveyPeriod::STATE_OPEN];
        yield 'in the middle' => ['2026-11-01', '2026-11-30', '2026-11-15 12:00', AboSurveyPeriod::STATE_OPEN];
        yield 'last second of the end date' => ['2026-11-01', '2026-11-30', '2026-11-30 23:59:59', AboSurveyPeriod::STATE_OPEN];
        yield 'after the end date' => ['2026-11-01', '2026-11-30', '2026-12-01 00:00:00', AboSurveyPeriod::STATE_CLOSED];
        yield 'start and end on the same day' => ['2026-11-01', '2026-11-01', '2026-11-01 18:00', AboSurveyPeriod::STATE_OPEN];
        yield 'start date with time is ignored' => ['2026-11-01 15:00', '2026-11-30', '2026-11-01 08:00', AboSurveyPeriod::STATE_OPEN];
    }
}
