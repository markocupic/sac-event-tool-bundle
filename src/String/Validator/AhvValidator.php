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

namespace Markocupic\SacEventToolBundle\String\Validator;

final class AhvValidator
{
    /**
     * Total number of digits in a valid AHV number.
     */
    public const int LENGTH = 13;

    /**
     * Swiss AHV number prefix.
     */
    public const string PREFIX = '756';

    /**
     * Validate a single AHV number.
     *
     * @param bool $strict If true, dots are required (756.XXXX.XXXX.XY).
     */
    public static function validate(int|string $ahvNumber, bool $strict = true): bool
    {
        // Always work with strings
        $ahvNumber = (string) $ahvNumber;

        if (!self::hasValidFormat($ahvNumber, $strict)) {
            return false;
        }

        $digits = self::digits($ahvNumber);

        // Must be exactly 13 digits
        if (self::LENGTH !== \strlen($digits)) {
            return false;
        }

        // Compare last digit with computed checksum
        return self::checksum($digits) === (int) $digits[self::LENGTH - 1];
    }

    /**
     * Check the textual format (fully anchored, prefix enforced).
     *
     * @param bool $strict If true, dots are required (756.XXXX.XXXX.XY).
     */
    private static function hasValidFormat(string $ahvNumber, bool $strict = true): bool
    {
        // Strict: 756.1234.5678.90
        // Loose: 7561234567890 or with optional dots
        $prefix = preg_quote(self::PREFIX, '/');

        $pattern = $strict
            ? '/^'.$prefix.'(?:\.\d{4}){2}\.\d{2}$/'
            : '/^'.$prefix.'(?:\d{10}|(?:\.\d{4}){2}\.\d{2})$/';

        return 1 === preg_match($pattern, $ahvNumber);
    }

    /**
     * Compute the EAN-13 check digit from the first 12 digits.
     *
     * @param string $digits A string of exactly 13 digits
     */
    private static function checksum(string $digits): int
    {
        $sum = 0;

        // Only first 12 digits are used for checksum
        for ($i = 0; $i < self::LENGTH - 1; ++$i) {
            $n = (int) $digits[$i];
            $weight = 0 === $i % 2 ? 1 : 3;
            $sum += $n * $weight;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Strip everything but digits.
     */
    private static function digits(string $ahvNumber): string
    {
        return preg_replace('/\D+/', '', $ahvNumber) ?? '';
    }
}
