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
    public const string GROUP_JUGEND_UND_SPORT = 'J+S';

    public const string GROUP_JUGEND = 'Jugend';

    private const int MAX_AGE_JUGEND_UND_SPORT = 20;

    private const int MAX_AGE_JUGEND = 22;

    public static function getAgeGroup(int $dateOfBirthTimestamp, int $referenceYear): string
    {
        $age = self::calculateAgeAtEndOfYear($dateOfBirthTimestamp, $referenceYear);

        return match (true) {
            $age <= self::MAX_AGE_JUGEND_UND_SPORT => self::GROUP_JUGEND_UND_SPORT,
            $age <= self::MAX_AGE_JUGEND => self::GROUP_JUGEND,
            default => '',
        };
    }

    private static function calculateAgeAtEndOfYear(int $dateOfBirthTimestamp, int $referenceYear): int
    {
        $birthDate = (new \DateTimeImmutable())->setTimestamp($dateOfBirthTimestamp);
        $referenceYearEnd = new \DateTimeImmutable("$referenceYear-12-31");

        return $referenceYearEnd->diff($birthDate)->y;
    }
}
