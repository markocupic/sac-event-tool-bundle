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

final class SwissTopoLV95Validator
{
    public const int EASTING_MIN = 2420000;

    public const int EASTING_MAX = 2930000;

    public const int NORTHING_MIN = 1000000;

    public const int NORTHING_MAX = 1350000;

    /**
     * Validates a pair of Swiss LV95 (swisstopo) coordinates given as a single,
     * comma-separated string in the exact form "x,y" (Ostwert,Nordwert),
     * e.g. "2600000,1200000".
     *
     * Rules:
     * - Exactly two 7-digit integers, separated by a single comma.
     * - No whitespace anywhere (not around the comma, not at the edges).
     * - The first value (x, easting) is > 2420000 and < 2930000.
     * - The second value (y, northing) is > 1000000 and < 1350000.
     *
     * Valid values:
     * - "2600000,1200000"
     *
     * Invalid values:
     * - "2600000, 1200000" (contains whitespace)
     * - "1200000,2600000" (easting and northing swapped -> out of range)
     * - "2950000,1200000" (easting not smaller than 2930000)
     * - "2400000,1200000" (easting not greater than 2420000)
     * - "2600000,1400000" (northing not smaller than 1350000)
     * - "2600000,900000" (northing not greater than 1000000)
     * - "2600000" (only one value)
     * - "2600000,1200000,700000" (too many values)
     * - "2600000;1200000" (wrong separator)
     */
    public static function isValid(string $coords): bool
    {
        if (1 !== preg_match('/\A(\d{7}),(\d{7})\z/', $coords, $matches)) {
            return false;
        }

        $easting = (int) $matches[1];
        $northing = (int) $matches[2];

        return match (true) {
            $easting <= self::EASTING_MIN => false,
            $easting >= self::EASTING_MAX => false,
            $northing <= self::NORTHING_MIN => false,
            $northing >= self::NORTHING_MAX => false,
            default => true,
        };
    }
}
