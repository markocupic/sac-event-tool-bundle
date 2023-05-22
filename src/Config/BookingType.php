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

namespace Markocupic\SacEventToolBundle\Config;

class BookingType
{
    public const ONLINE_FORM = 'onlineForm';
    public const MANUALLY = 'manually';
    public const ALL = [
        self::ONLINE_FORM,
        self::MANUALLY,
    ];
}
