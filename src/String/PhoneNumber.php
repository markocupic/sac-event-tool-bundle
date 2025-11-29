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

namespace Markocupic\SacEventToolBundle\String;

class PhoneNumber
{
    private const SWISS_COUNTRY_CODE_PLUS = '+41';

    private const SWISS_COUNTRY_CODE_NUMERIC = '0041';

    private const SWISS_NUMBER_LENGTH = 9;

    // Swiss phone number without countrycode and whitespaces: 0799871234
    private const SWISS_FORMAT_PATTERN = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';

    /**
     * Formats a given phone number string according to Swiss phone number standards:
     * +41799871234 => 079 987 12 34
     * 0041799871234 => 079 987 12 34
     * 0799871234 => 079 987 12 34
     *
     * - Removes any whitespace in the input string.
     * - Strips the Swiss country code from the phone number.
     * - Adds a leading zero if the phone number doesn't start with one and has the expected Swiss number length.
     * - Formats the phone number into a more human-readable format by adding spaces at appropriate positions.
     */
    public function beautify(string $phoneNumber = ''): string
    {
        // Remove whitespaces
        $phoneNumber = preg_replace('/\s+/', '', $phoneNumber);

        if ('' !== $phoneNumber) {
            // Remove country code
            $phoneNumber = str_replace(self::SWISS_COUNTRY_CODE_PLUS, '', $phoneNumber);
            $phoneNumber = str_replace(self::SWISS_COUNTRY_CODE_NUMERIC, '', $phoneNumber);

            // Add a leading zero, if there is no f.ex 41
            if (!str_starts_with($phoneNumber, '0') && self::SWISS_NUMBER_LENGTH === \strlen($phoneNumber)) {
                $phoneNumber = '0'.$phoneNumber;
            }

            if (preg_match(self::SWISS_FORMAT_PATTERN, $phoneNumber)) {
                $replace = '$1$2 $3 $4 $5';

                // Search for 0799871234 and replace it with 079 987 12 34
                $phoneNumber = preg_replace(self::SWISS_FORMAT_PATTERN, $replace, $phoneNumber);
            }
        }

        return $phoneNumber;
    }
}
