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

namespace Markocupic\SacEventToolBundle\Config;

class TourguideQualification
{
    public const int TOURGUIDE_SAC = 1;

    public const int MOUNTAIN_GUIDE = 2;

    public const int PSYCHOLOGIST = 3;

    public const int SKI_INSTRUCTOR = 4;

    public const int DOCTOR = 5;

    public const int J_AND_S_INSTRUCTOR = 6;

    public const int HIKING_INSTRUCTOR = 7;

    public const int IGKA_INSTRUCTOR = 8;

    public const array ALL = [
        self::TOURGUIDE_SAC,
        self::MOUNTAIN_GUIDE,
        self::PSYCHOLOGIST,
        self::SKI_INSTRUCTOR,
        self::DOCTOR,
        self::J_AND_S_INSTRUCTOR,
        self::HIKING_INSTRUCTOR,
        self::IGKA_INSTRUCTOR,
    ];
}
