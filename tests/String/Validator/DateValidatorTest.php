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

use Markocupic\SacEventToolBundle\String\Validator\DateValidator;
use PHPUnit\Framework\TestCase;

final class DateValidatorTest extends TestCase
{
    /**
     * @dataProvider provideValidDates
     */
    public function testIsValidDateWithValidValues(string $value, string $format): void
    {
        $this->assertTrue(DateValidator::isValidDate($value, $format));
    }

    /**
     * @dataProvider provideInvalidDates
     */
    public function testIsValidDateWithInvalidValues(mixed $value, string $format): void
    {
        $this->assertFalse(DateValidator::isValidDate($value, $format));
    }

    /**
     * \DateTime::createFromFormat() is tolerant by design. These cases are
     * pinned down here so a future change of behaviour does not go unnoticed.
     *
     * @dataProvider provideLenientlyParsedDates
     */
    public function testIsValidDateAcceptsLenientlyParsedValues(string $value, string $format): void
    {
        $this->assertTrue(DateValidator::isValidDate($value, $format));
    }

    /**
     * @dataProvider provideValidTimestamps
     */
    public function testIsValidTimestampWithValidValues(int|string $value): void
    {
        $this->assertTrue(DateValidator::isValidTimestamp($value));
    }

    /**
     * @dataProvider provideInvalidTimestamps
     */
    public function testIsValidTimestampWithInvalidValues(mixed $value): void
    {
        $this->assertFalse(DateValidator::isValidTimestamp($value));
    }

    public static function provideValidDates(): iterable
    {
        return [
            'Swiss date format' => ['29.05.2027', 'd.m.Y'],
            'ISO date format' => ['2027-05-29', 'Y-m-d'],
            'Date with time' => ['29.05.2027 14:30', 'd.m.Y H:i'],
            'Date with time and seconds' => ['2027-12-31 23:59:59', 'Y-m-d H:i:s'],
            'Leap day in a leap year' => ['29.02.2024', 'd.m.Y'],
            'Last day of a 31 day month' => ['31.12.2027', 'd.m.Y'],
            'Last day of a 30 day month' => ['30.04.2027', 'd.m.Y'],
            'Unix epoch' => ['01.01.1970', 'd.m.Y'],
            'Date before the unix epoch' => ['31.12.1899', 'd.m.Y'],
            'Reset unparsed fields' => ['2027-05-29', '!Y-m-d'],
            'Time only' => ['14:30', 'H:i'],
            'Midnight' => ['00:00', 'H:i'],
        ];
    }

    public static function provideInvalidDates(): iterable
    {
        return [
            'Day out of range for the month' => ['31.02.2027', 'd.m.Y'],
            'Leap day in a non leap year' => ['29.02.2023', 'd.m.Y'],
            'Day out of range' => ['32.01.2027', 'd.m.Y'],
            'Day zero' => ['00.05.2027', 'd.m.Y'],
            'Month out of range' => ['2027-13-01', 'Y-m-d'],
            'Hour out of range' => ['25:00', 'H:i'],
            'Minute out of range' => ['14:60', 'H:i'],
            'Value does not match the format' => ['2027-05-29', 'd.m.Y'],
            'Format does not match the value' => ['29.05.2027', 'Y-m-d'],
            'Wrong separator' => ['29/05/2027', 'd.m.Y'],
            'Trailing data' => ['29.05.2027 extra', 'd.m.Y'],
            'Incomplete date' => ['29.05.', 'd.m.Y'],
            'Alphabetic string' => ['foo', 'd.m.Y'],
            'Timestamp instead of a date' => ['1811894400', 'd.m.Y'],
            'Whitespace only' => ['   ', 'd.m.Y'],
            'Empty string' => ['', 'd.m.Y'],
            'Null' => [null, 'd.m.Y'],
            'Integer' => [1811894400, 'd.m.Y'],
            'Float' => [1811894400.5, 'd.m.Y'],
            'Boolean true' => [true, 'd.m.Y'],
            'Boolean false' => [false, 'd.m.Y'],
            'Array' => [['29.05.2027'], 'd.m.Y'],
            'DateTime object' => [new \DateTime('2027-05-29'), 'd.m.Y'],
        ];
    }

    public static function provideLenientlyParsedDates(): iterable
    {
        return [
            'Day and month without leading zeros' => ['9.5.2027', 'd.m.Y'],
            'Two digit year for a four digit placeholder' => ['29.05.27', 'd.m.Y'],
        ];
    }

    public static function provideValidTimestamps(): iterable
    {
        return [
            'Integer zero' => [0],
            'String zero' => ['0'],
            'Integer timestamp' => [1811894400],
            'String timestamp' => ['1811894400'],
            'Negative integer timestamp' => [-86400],
            'Negative string timestamp' => ['-86400'],
            'Smallest integer' => [PHP_INT_MIN],
            'Largest integer' => [PHP_INT_MAX],
            'Largest integer as a string' => [(string) PHP_INT_MAX],
        ];
    }

    public static function provideInvalidTimestamps(): iterable
    {
        return [
            'Float' => [1811894400.5],
            'Float without decimals' => [1811894400.0],
            'Float string' => ['1811894400.5'],
            'Exponential notation' => ['1e3'],
            'Leading zeros' => ['0123'],
            'Leading plus sign' => ['+1811894400'],
            'Negative zero' => ['-0'],
            'Leading whitespace' => [' 1811894400'],
            'Trailing whitespace' => ['1811894400 '],
            'Integer overflow' => ['9223372036854775808'],
            'Hexadecimal notation' => ['0x1A'],
            'Numeric separator' => ['1_000'],
            'Formatted date' => ['29.05.2027'],
            'Alphabetic string' => ['abc'],
            'Alphanumeric string' => ['123abc'],
            'Empty string' => [''],
            'Whitespace only' => ['   '],
            'Null' => [null],
            'Boolean true' => [true],
            'Boolean false' => [false],
            'Array' => [[1811894400]],
        ];
    }
}
