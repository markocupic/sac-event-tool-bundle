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

use Markocupic\SacEventToolBundle\String\Validator\AhvValidator;

final class AhvNumberFormatter
{
    /**
     * Format a 13-digit AHV number as: 756.XXXX.XXXX.XX.
     */
    public static function format(string $ahvInput): string
    {
        // Keep only digits
        $digits = preg_replace('/\D/', '', $ahvInput);

        // Must be exactly 13 digits and start with 756
        if (13 !== \strlen($digits) || !str_starts_with($digits, '756')) {
            throw new \InvalidArgumentException(\sprintf('%s is not a valid AHV number.', $ahvInput));
        }

        // 756.XXXX.XXXX.XX
        $part1 = substr($digits, 0, 3); // 756
        $part2 = substr($digits, 3, 4); // XXXX
        $part3 = substr($digits, 7, 4); // XXXX
        $part4 = substr($digits, 11, 2); // XX

        $ahv = \sprintf('%s.%s.%s.%s', $part1, $part2, $part3, $part4);

        if (!AhvValidator::validate($ahv)) {
            throw new \InvalidArgumentException(\sprintf('%s is not a valid AHV number.', $ahvInput));
        }

        return $ahv;
    }
}
