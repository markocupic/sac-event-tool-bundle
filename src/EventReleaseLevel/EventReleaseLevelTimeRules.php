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

namespace Markocupic\SacEventToolBundle\EventReleaseLevel;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;

/**
 * Checks the time rules of the calendar for a release level transition. The
 * rules apply to non-admins only, this is up to the caller (see
 * EventReleaseLevelTransitionVoter).
 */
class EventReleaseLevelTimeRules
{
    private Adapter $eventReleaseLevelPolicyModel;

    public function __construct(private readonly ContaoFramework $framework)
    {
        $this->eventReleaseLevelPolicyModel = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
    }

    /**
     * Returns the violated time rule or null if the event may be shifted to the
     * target level. Keeping the current level never violates a rule.
     */
    public function getViolation(CalendarEventsModel $event, EventReleaseLevelPolicyModel $targetLevel): EventReleaseLevelTimeRuleViolation|null
    {
        $currentLevel = $this->eventReleaseLevelPolicyModel->findById($event->eventReleaseLevel);

        if (null === $currentLevel || (int) $currentLevel->id === (int) $targetLevel->id) {
            return null;
        }

        $calendar = $event->getRelated('pid');

        if (null === $calendar) {
            return null;
        }

        // Upgrading above the initial level is only allowed if the event start date
        // is within the valid time period of the calendar.
        $minLevel = $this->eventReleaseLevelPolicyModel->findMinLevelByEventId($event->id);

        if (
            null !== $minLevel
            && (int) $minLevel->id !== (int) $targetLevel->id
            && $targetLevel->level > $currentLevel->level
            && $calendar->enableEventStartDateValidation
            && ($event->startDate < $calendar->validTimePeriodStart || $event->startDate > $calendar->validTimePeriodStop)
        ) {
            return EventReleaseLevelTimeRuleViolation::StartDateOutsideValidTimePeriod;
        }

        // The top level is locked until the time limit of the calendar.
        $maxLevel = $this->eventReleaseLevelPolicyModel->findMaxLevelByEventId($event->id);

        if (
            null !== $maxLevel
            && (int) $maxLevel->id === (int) $targetLevel->id
            && $calendar->enableMaxEventReleaseLevelProtection
            && time() < $calendar->maxEventReleaseLevelTimeLimit
        ) {
            return EventReleaseLevelTimeRuleViolation::MaxLevelLocked;
        }

        return null;
    }
}
