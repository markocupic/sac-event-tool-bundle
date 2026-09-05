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

namespace Markocupic\SacEventToolBundle\String\Normalizer;

use Markocupic\SacEventToolBundle\String\Validator\SwissTopoLV95Validator;

final class SwissTopoLV95Normalizer
{
    public static function normalize(mixed $coords): string
    {
        // Remove all non-numeric characters except comma and dot
        $coords = preg_replace('/[^0-9\.,]/', '', $coords);

        if ('' === $coords) {
            return '';
        }

        // Ensure exactly one comma is present
        if (1 !== substr_count($coords, ',')) {
            return '';
        }

        [$east, $north] = explode(',', $coords);

        if (substr_count($east, '.') > 1) {
            return '';
        }

        if (substr_count($north, '.') > 1) {
            return '';
        }

        // Ensure both values are numeric (integer or float)
        if (!is_numeric($east) || !is_numeric($north)) {
            return '';
        }

        // Round float values to nearest integer
        $east = (int) round((float) $east);
        $north = (int) round((float) $north);

        // Convert CH1903 to CH1930+ format
        if ($east < 1000000) {
            $east += 2000000;
        }

        if ($north < 1000000) {
            $north += 1000000;
        }

        // Build normalized coordinate string
        $coords = $east.','.$north;

        // Validate final coordinate format and range
        if (!SwissTopoLV95Validator::isValid($coords)) {
            return '';
        }

        return $coords;
    }
}
