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

namespace Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel;

/**
 * The time rules of the calendar that prevent non-admins from changing the
 * release level of an event.
 */
enum EventReleaseLevelTimeRuleViolation
{
    /**
     * Calendar setting "enableEventStartDateValidation": The event start date is
     * outside the valid time period, the event cannot be upgraded above the
     * initial level.
     */
    case StartDateOutsideValidTimePeriod;

    /**
     * Calendar setting "enableMaxEventReleaseLevelProtection": The event cannot be
     * shifted to the top level before "maxEventReleaseLevelTimeLimit".
     */
    case MaxLevelLocked;
}
