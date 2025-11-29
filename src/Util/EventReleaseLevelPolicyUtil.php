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
        $currentPolicyModel = $this->eventReleaseLevelPolicyModel->findById($eventModel->eventReleaseLevel);

        if (null === $currentPolicyModel) {
            return [];
        }

        $downwardLevels = $this->collectAccessibleLevelsDownward($eventModel, $user, $currentPolicyModel);
        $upwardLevels = $this->collectAccessibleLevelsUpward($eventModel, $user, $currentPolicyModel);

        return array_merge(
            array_reverse($downwardLevels),
            [$currentPolicyModel->id],
            $upwardLevels,
        );
    }

    private function collectAccessibleLevelsDownward(CalendarEventsModel $eventModel, BackendUser $user, EventReleaseLevelPolicyModel $startLevel): array
    {
        $accessibleLevels = [];
        $currentLevel = $this->eventReleaseLevelPolicyModel->findById($startLevel->id);

        while (null !== $currentLevel) {
            if (!$this->calendarEventsVoter->canChangeReleaseLevel($eventModel, $user, $currentLevel, 'down')) {
                break;
            }

            $currentLevel = $this->eventReleaseLevelPolicyModel->findPrevLevel($currentLevel->id);

            if (null !== $currentLevel) {
                $accessibleLevels[] = $currentLevel->id;
            }
        }

        return $accessibleLevels;
    }

    private function collectAccessibleLevelsUpward(CalendarEventsModel $eventModel, BackendUser $user, EventReleaseLevelPolicyModel $startLevel): array
    {
        $accessibleLevels = [];
        $currentLevel = $this->eventReleaseLevelPolicyModel->findById($startLevel->id);

        while (null !== $currentLevel) {
            if (!$this->calendarEventsVoter->canChangeReleaseLevel($eventModel, $user, $currentLevel, 'up')) {
                break;
            }

            $currentLevel = $this->eventReleaseLevelPolicyModel->findNextLevel($currentLevel->id);

            if (null !== $currentLevel) {
                $accessibleLevels[] = $currentLevel->id;
            }
        }

        return $accessibleLevels;
    }
}
