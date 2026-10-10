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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventStats;

use Markocupic\SacEventToolBundle\Feature\EventStats\EventStatsQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventStatsQueryTest extends TestCase
{
    /**
     * @dataProvider ageGroupProvider
     */
    #[DataProvider('ageGroupProvider')]
    public function testGetAgeGroup(int|null $age, string $expected): void
    {
        $this->assertSame($expected, EventStatsQuery::getAgeGroup($age));
    }

    public static function ageGroupProvider(): iterable
    {
        yield [null, 'age_undefined'];
        yield [0, 'age_0_20'];
        yield [20, 'age_0_20'];
        yield [21, 'age_21_30'];
        yield [30, 'age_21_30'];
        yield [31, 'age_31_40'];
        yield [40, 'age_31_40'];
        yield [41, 'age_41_60'];
        yield [60, 'age_41_60'];
        yield [61, 'age_61_80'];
        yield [80, 'age_61_80'];
        yield [81, 'age_81_plus'];
    }

    public function testEveryAgeGroupIsListed(): void
    {
        foreach ([null, 10, 25, 35, 50, 70, 90] as $age) {
            $this->assertContains(EventStatsQuery::getAgeGroup($age), EventStatsQuery::AGE_GROUPS);
        }
    }
}
