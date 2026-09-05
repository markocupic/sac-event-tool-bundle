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

namespace Markocupic\SacEventToolBundle\Tests\String\Validator;

use Markocupic\SacEventToolBundle\String\Validator\SwissTopoLV95Validator;
use PHPUnit\Framework\TestCase;

final class SwissTopoLV95ValidatorTest extends TestCase
{
    /**
     * @dataProvider provideValidCoords
     */
    public function testIsValidSwissTopoCoordsWithValidValues(string $coords): void
    {
        $this->assertTrue(SwissTopoLV95Validator::isValid($coords));
    }

    /**
     * @dataProvider provideInvalidCoords
     */
    public function testIsValidSwissTopoCoordsWithInvalidValues(string $coords): void
    {
        $this->assertFalse(SwissTopoLV95Validator::isValid($coords));
    }

    public static function provideValidCoords(): iterable
    {
        return [
            'Typical coordinate' => ['2600000,1200000'],
            'Just inside the lower bounds' => ['2420001,1000001'],
            'Just inside the upper bounds' => ['2929999,1349999'],
        ];
    }

    public static function provideInvalidCoords(): iterable
    {
        return [
            'Easting on the lower bound (exclusive)' => ['2420000,1200000'],
            'Easting on the upper bound (exclusive)' => ['2930000,1200000'],
            'Northing on the lower bound (exclusive)' => ['2600000,1000000'],
            'Northing on the upper bound (exclusive)' => ['2600000,1350000'],
            'Easting below the range' => ['2300000,1200000'],
            'Northing above the range' => ['2600000,1360000'],
            'Easting and northing swapped' => ['1200000,2600000'],
            'Whitespace after the comma' => ['2600000, 1200000'],
            'Leading whitespace' => [' 2600000,1200000'],
            'Trailing whitespace' => ['2600000,1200000 '],
            'Trailing newline' => ["2600000,1200000\n"],
            'Only one value' => ['2600000'],
            'More than two values' => ['2600000,1200000,700000'],
            'Wrong separator' => ['2600000;1200000'],
            'Too few digits' => ['260000,120000'],
            'Too many digits' => ['26000000,12000000'],
            'Decimal values' => ['2600000.5,1200000.5'],
            'Negative sign' => ['-2600000,1200000'],
            'Empty string' => [''],
            'Alphabetic string' => ['abc,def'],
        ];
    }
}
