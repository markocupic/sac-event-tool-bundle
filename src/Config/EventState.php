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

class EventState
{
    public const STATE_FULLY_BOOKED = 'event_fully_booked';
    public const STATE_CANCELED = 'event_canceled';
    public const STATE_RESCHEDULED = 'event_rescheduled';
    public const ALL = [
        self::STATE_FULLY_BOOKED,
        self::STATE_CANCELED,
        self::STATE_RESCHEDULED,
    ];
}
