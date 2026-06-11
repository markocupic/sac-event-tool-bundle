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

namespace Markocupic\SacEventToolBundle\Tests\String\Formatter;

use Markocupic\SacEventToolBundle\String\Formatter\PhoneNumberFormatter;
use PHPUnit\Framework\TestCase;

class PhoneNumberFormatterTest extends TestCase
{
    /**
     * @dataProvider validNumberProvider
     */
    public function testFormatsValidNumbers(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumberFormatter::format($input));
    }

    public static function validNumberProvider(): array
    {
        return [
            // National format
            'national plain' => ['0799871234', '079 987 12 34'],
            'national spaced' => ['079 987 12 34', '079 987 12 34'],
            'national dashes' => ['079-987-12-34', '079 987 12 34'],
            'national slashes' => ['079/987 12 34', '079 987 12 34'],
            'national dots' => ['079.987.12.34', '079 987 12 34'],
            'landline zurich' => ['044 668 18 00', '044 668 18 00'],

            // International "+41"
            'plus41 spaced' => ['+41 79 987 12 34', '079 987 12 34'],
            'plus41 plain' => ['+41799871234', '079 987 12 34'],
            'plus41 paren zero' => ['+41 (0)79 987 12 34', '079 987 12 34'],
            'plus41 paren zero nospace' => ['+41(0)799871234', '079 987 12 34'],

            // International "0041"
            'numeric spaced' => ['0041 79 987 12 34', '079 987 12 34'],
            'numeric plain' => ['0041799871234', '079 987 12 34'],

            // Bare country code
            'bare 41' => ['41799871234', '079 987 12 34'],

            // Service numbers grouped 4-3-3
            'freephone 0800' => ['0800123456', '0800 123 456'],
            'premium 0900' => ['0900 123 456', '0900 123 456'],
            'shared 0848' => ['0848800800', '0848 800 800'],
        ];
    }

    /**
     * @dataProvider invalidNumberProvider
     */
    public function testReturnsOriginalOnInvalidInput(string $input): void
    {
        // Invalid input must be returned untouched (no partial mangling).
        $this->assertSame($input, PhoneNumberFormatter::format($input));
    }

    public static function invalidNumberProvider(): array
    {
        return [
            'empty' => [''],
            'too short' => ['+41 79'],
            'too long' => ['0799871234567'],
            'letters' => ['keine Nummer'],
            'partial intl' => ['+41 44 12'],
        ];
    }

    public function testIsIdempotent(): void
    {
        $once = PhoneNumberFormatter::format('+41 79 987 12 34');
        $twice = PhoneNumberFormatter::format($once);

        $this->assertSame($once, $twice);
        $this->assertSame('079 987 12 34', $twice);
    }
}