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

namespace Markocupic\SacEventToolBundle\Tests\String\Normalizer;

use Markocupic\SacEventToolBundle\String\Normalizer\SwissTopoLV95Normalizer;
use PHPUnit\Framework\TestCase;

final class SwissTopoLV95NormalizerTest extends TestCase
{
    /**
     * A valid (or normalizable) input is returned as a clean "easting,northing" string.
     *
     * @dataProvider provideNormalizableCoords
     */
    public function testNormalizeReturnsNormalizedCoords(string $input, string $expected): void
    {
        $this->assertSame($expected, SwissTopoLV95Normalizer::normalize($input));
    }

    /**
     * Anything that cannot be turned into a valid LV95 coordinate yields an empty string.
     *
     * @dataProvider provideUnsanitizableCoords
     */
    public function testNormalizeReturnsEmptyStringForInvalidInput(string $input): void
    {
        $this->assertSame('', SwissTopoLV95Normalizer::normalize($input));
    }

    public static function provideNormalizableCoords(): iterable
    {
        return [
            'Already normalized LV95' => ['2600000,1200000', '2600000,1200000'],
            'Whitespace is stripped' => ['2600000, 1200000', '2600000,1200000'],
            'Non-numeric labels and spaces are stripped' => [' E 2600000 , N 1200000 ', '2600000,1200000'],
            'Float values are rounded to the nearest integer' => ['2600000.4,1200000.6', '2600000,1200001'],
            'CH1903 six digit coordinates are converted to LV95' => ['600000,200000', '2600000,1200000'],
            'CH1903 conversion keeps the easting distinct' => ['500000,200000', '2500000,1200000'],
            'CH1903 float coordinates are rounded and converted' => ['600000.5,200000.5', '2600001,1200001'],
        ];
    }

    public static function provideUnsanitizableCoords(): iterable
    {
        return [
            'Missing comma' => ['2600000'],
            'More than one comma' => ['2600000,1200000,700000'],
            'Easting with more than one dot' => ['2.600.000,1200000'],
            'Northing with more than one dot' => ['2600000,1.200.000'],
            'Empty easting' => [',1200000'],
            'Empty northing' => ['2600000,'],
            'Empty string' => [''],
            'Only non-numeric characters' => ['abc'],
            'Out of range' => ['9999999,1200000'],
            'Swapped easting and northing' => ['1200000,2600000'],
            'Converted CH1903 easting stays below the valid range' => ['100000,200000'],
            'Easting of exactly 1000000 is not converted and stays out of range' => ['1000000,200000'],
        ];
    }
}
