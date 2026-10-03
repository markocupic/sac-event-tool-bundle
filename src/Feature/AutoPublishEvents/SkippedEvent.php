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

namespace Markocupic\SacEventToolBundle\Feature\AutoPublishEvents;

/**
 * An event that was not published, with the reason.
 */
final readonly class SkippedEvent
{
    // The event's release level does not belong to the release level system of its event type
    // (e.g. the event type was changed or has no release level system anymore).
    public const string REASON_INVALID_RELEASE_LEVEL = 'invalid_release_level';

    // Calendar setting "enableEventStartDateValidation": the start date is outside the valid time period.
    public const string REASON_OUTSIDE_VALID_TIME_PERIOD = 'outside_valid_time_period';

    // Release level or published state changed between the query and the update.
    public const string REASON_CHANGED_MEANWHILE = 'changed_meanwhile';

    // An exception occurred, see $detail.
    public const string REASON_ERROR = 'error';

    public function __construct(
        public int $eventId,
        public string $title,
        public string $reason,
        public string $detail = '',
    ) {
    }
}
