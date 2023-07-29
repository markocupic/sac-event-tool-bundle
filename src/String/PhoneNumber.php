<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\String;

class PhoneNumber
{
    public static function beautify(string $strNumber = ''): string
    {
        // Remove whitespaces
        $strNumber = preg_replace('/\s+/', '', $strNumber);

        if ('' !== $strNumber) {
            // Remove country code
            $strNumber = str_replace('+41', '', $strNumber);
            $strNumber = str_replace('0041', '', $strNumber);

            // Add a leading zero, if there is no f.ex 41
            if (!str_starts_with($strNumber, '0') && 9 === \strlen($strNumber)) {
                $strNumber = '0'.$strNumber;
            }

            // Search for 0799871234 and replace it with 079 987 12 34
            $pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';

            if (preg_match($pattern, $strNumber)) {
                $replace = '$1$2 $3 $4 $5';
                $strNumber = preg_replace($pattern, $replace, $strNumber);
            }
        }

        return $strNumber;
    }
}
