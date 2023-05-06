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

namespace Markocupic\SacEventToolBundle\Util;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;

class EventReleaseLevelPolicyUtil
{
    private Adapter $eventReleaseLevelPolicyModel;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CalendarEventsVoter $calendarEventsVoter,
    ) {
        $this->eventReleaseLevelPolicyModel = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
    }

    /**
     * !!! Not used method at the moment.
     *
     * Returns an array of IDS of all accessible event release level policies.
     *
     * @throws \Exception
     */
    public function getAccessibleReleaseLevels(CalendarEventsModel $eventModel, BackendUser $user): array
    {
        $allowedIDS = [];

        $currentEventReleaseLevelPolicyModel = $this->eventReleaseLevelPolicyModel->findByPk($eventModel->eventReleaseLevel);

        if (null === $currentEventReleaseLevelPolicyModel) {
            return $allowedIDS;
        }

        // Test downwards
        $prevLevel = $this->eventReleaseLevelPolicyModel->findByPk($currentEventReleaseLevelPolicyModel->id);

        $stop = false;

        do {
            if (null === $prevLevel) {
                $stop = true;
                continue;
            }

            if ($this->calendarEventsVoter->canChangeReleaseLevel($eventModel, $user, $prevLevel, 'down')) {
                $prevLevel = $this->eventReleaseLevelPolicyModel->findPrevLevel($prevLevel->id);
                $allowedIDS[] = $prevLevel->id;
            } else {
                $stop = true;
            }
        } while (true !== $stop);

        $allowedIDS = array_reverse($allowedIDS);

        // Add the current release level
        $allowedIDS[] = $currentEventReleaseLevelPolicyModel->id;

        // Test upwards
        $nextLevel = $this->eventReleaseLevelPolicyModel->findByPk($currentEventReleaseLevelPolicyModel->id);

        $stop = false;

        do {
            if (null === $nextLevel) {
                $stop = true;
                continue;
            }

            if ($this->calendarEventsVoter->canChangeReleaseLevel($eventModel, $user, $nextLevel, 'up')) {
                $nextLevel = $this->eventReleaseLevelPolicyModel->findNextLevel($nextLevel->id);
                $allowedIDS[] = $nextLevel->id;
            } else {
                $stop = true;
            }
        } while (true !== $stop);

        return $allowedIDS;
    }
}
