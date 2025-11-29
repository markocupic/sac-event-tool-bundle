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

namespace Markocupic\SacEventToolBundle\Util;

class AgeGroupUtil
{
    public const GROUP_JUGEND_UND_SPORT = 'J+S';

    public const GROUP_JUGEND = 'Jugend';

    public static function getAgeGroup(int $dateOfBirthTimestamp, int $year): string
    {
        $age = self::getAgeAtEndOfYear($dateOfBirthTimestamp, $year);

        return match (true) {
            $age <= 20 => self::GROUP_JUGEND_UND_SPORT,
            $age <= 22 => self::GROUP_JUGEND,
            default => '',
        };
    }

    private static function getAgeAtEndOfYear(int $dateOfBirthTimestamp, int $year): int
    {
        $birthDate = (new \DateTimeImmutable())->setTimestamp($dateOfBirthTimestamp);
        $endOfYear = new \DateTimeImmutable("$year-12-31");

        return $endOfYear->diff($birthDate)->y;
    }
}
