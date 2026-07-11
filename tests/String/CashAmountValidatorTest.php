<?php

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\Tests\String\Validator;

use Markocupic\SacEventToolBundle\String\Validator\CashAmountValidator;
use PHPUnit\Framework\TestCase;

final class CashAmountValidatorTest extends TestCase
{
    /**
     * @dataProvider provideValidAmounts
     */
    public function testIsValidPositiveAmountWithValidValues(float|int|string $amount): void
    {
        $this->assertTrue(CashAmountValidator::isPositiveCashAmount($amount));
    }

    /**
     * @dataProvider provideInvalidAmounts
     */
    public function testIsValidPositiveAmountWithInvalidValues(mixed $amount): void
    {
        $this->assertFalse(CashAmountValidator::isPositiveCashAmount($amount));
    }

    public static function provideValidAmounts(): iterable
    {
        return [
            'Integer zero' => [0],
            'String zero' => ['0'],
            'Simple integer' => [4],
            'Large integer' => [676767],
            'Float with 1 decimal' => [12.4],
            'String with 1 decimal' => ['12.4'],
            'Float with 2 decimals' => [123.45],
            'String with 2 decimals' => ['123.45'],
        ];
    }

    public static function provideInvalidAmounts(): iterable
    {
        return [
            'Negative integer' => [-123],
            'Negative float' => [-12.45],
            'String with leading zeros' => ['0123'],
            'Float with 3 decimals' => [12.345],
            'String with 3 decimals' => ['12.345'],
            'Alphabetic string' => ['abc'],
            'Alphanumeric string' => ['12.34abc'],
            'Empty string' => [''],
        ];
    }
}
