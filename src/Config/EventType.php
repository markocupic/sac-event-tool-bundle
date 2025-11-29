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

class EventType
{
    public const string COURSE = 'course';

    public const string TOUR = 'tour';

    public const string LAST_MINUTE_TOUR = 'lastMinuteTour';

    public const string GENERAL_EVENT = 'generalEvent';

    public const array ALL = [
        self::COURSE,
        self::TOUR,
        self::LAST_MINUTE_TOUR,
        self::GENERAL_EVENT,
    ];
}
