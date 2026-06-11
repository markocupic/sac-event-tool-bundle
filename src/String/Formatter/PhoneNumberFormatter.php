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

namespace Markocupic\SacEventToolBundle\String\Formatter;

class PhoneNumberFormatter
{
    // National significant number (NSN): 9 digits, e.g., 79 987 12 34
    private const int NSN_LENGTH = 9;

    /**
     * Formats a Swiss phone number into the human-readable national format.
     *
     * Mobile / landline (NSN 79..., 44...):
     *   +41 79 987 12 34 => 079 987 12 34
     *   0041 79 987 12 34 => 079 987 12 34
     *   +41 (0)79 987 1234 => 079 987 12 34
     *   079-987-12-34 => 079 987 12 34
     *   044 668 18 00 => 044 668 18 00
     *
     * Service numbers (08xx / 09xx) are grouped 4-3-3:
     *   0800 123 456 => 0800 123 456
     *
     * The input may contain spaces, dashes, slashes, dots or parentheses; all
     * non-digit characters (except a leading "+") are ignored.
     *
     * If the input cannot be parsed as a valid Swiss number, the ORIGINAL,
     * untouched input string is returned (no partial mangling).
     */
    public static function format(string $phoneNumber = ''): string
    {
        $original = $phoneNumber;

        // Keep digits only, preserving a single leading "+" (international prefix).
        $hasPlus = str_starts_with(ltrim($phoneNumber), '+');
        $digits = preg_replace('/\D+/', '', $phoneNumber);

        if (null === $digits || '' === $digits) {
            return $original;
        }

        $nsn = self::extractNsn($hasPlus ? '+'.$digits : $digits);

        if (null === $nsn) {
            // Not a valid Swiss number -> return the input unchanged.
            return $original;
        }

        return self::group('0'.$nsn);
    }

    /**
     * Extracts the 9-digit national significant number (NSN) from any supported
     * input notation or returns null if the input is not a valid Swiss number.
     */
    private static function extractNsn(string $input): string|null
    {
        // Strip the country code, anchored to the start of the string only.
        $rest = match (true) {
            str_starts_with($input, '+41') => substr($input, 3),
            str_starts_with($input, '0041') => substr($input, 4),
            // Bare country code without a prefix, e.g., 41799871234
            str_starts_with($input, '41') && self::NSN_LENGTH + 2 === \strlen($input) => substr($input, 2),
            // Already in national format: drop the trunk "0".
            str_starts_with($input, '0') => substr($input, 1),
            default => $input,
        };

        // Drop an optional informational "0" left over from the +41 (0)79... notation.
        if (self::NSN_LENGTH < \strlen($rest) && str_starts_with($rest, '0')) {
            $rest = substr($rest, 1);
        }

        if (1 !== preg_match('/^\d{'.self::NSN_LENGTH.'}$/', $rest)) {
            return null;
        }

        return $rest;
    }

    /**
     * Groups a 10-digit national number into the conventional spacing.
     */
    private static function group(string $national): string
    {
        // Service numbers 08xx / 09xx are grouped 4-3-3: 0800 123 456
        if (str_starts_with($national, '08') || str_starts_with($national, '09')) {
            return preg_replace('/^(\d{4})(\d{3})(\d{3})$/', '$1 $2 $3', $national);
        }

        // Mobile / landline grouped 3-3-2-2: 079 987 12 34
        return preg_replace('/^(\d{3})(\d{3})(\d{2})(\d{2})$/', '$1 $2 $3 $4', $national);
    }
}
