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

namespace Markocupic\SacEventToolBundle\Security\Voter;

use Contao\CalendarEventsModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;

/**
 * Subject of EventReleaseLevelTransitionVoter::CAN_SWITCH_TO_EVENT_RELEASE_LEVEL: the event
 * is moved from its current release level to the target level.
 */
final readonly class EventReleaseLevelTransition
{
    public function __construct(
        public CalendarEventsModel $event,
        public EventReleaseLevelPolicyModel $targetLevel,
    ) {
    }
}
