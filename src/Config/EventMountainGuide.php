<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Config;

class EventMountainGuide
{
    public const NO_MOUNTAIN_GUIDE = 0;
    public const WITH_MOUNTAIN_GUIDE = 1;
    public const WITH_MOUNTAIN_GUIDE_OFFER = 2;
    public const ALL = [
        self::NO_MOUNTAIN_GUIDE,
        self::WITH_MOUNTAIN_GUIDE,
        self::WITH_MOUNTAIN_GUIDE_OFFER,
    ];
}
