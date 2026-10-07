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

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;

class EventReleaseLevelPolicyUtil
{
    private Adapter $eventReleaseLevelPolicyModel;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CalendarEventsVoter $calendarEventsVoter,
        private readonly Connection $connection,
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

    /**
     * Returns the levels of the release level system of the event type, highest level first.
     * The levels are sorted by tl_event_release_level_policy.level, gaps (e.g. 1, 2, 5) do not matter.
     * Returns an empty list if the event type does not exist or has no release level system.
     *
     * The release level system belongs to the event type, not to the calendar:
     * tl_calendar_events.eventType (alias) → tl_event_type.levelAccessPermissionPackage
     * → tl_event_release_level_policy_package → tl_event_release_level_policy.
     *
     * Unlike EventReleaseLevelPolicyModel::findMaxLevelByEventId() etc., this method does not write
     * back end messages and can be used in the cron (see Feature\AutoPublishEvents).
     *
     * @return list<ReleaseLevel>
     */
    public function getLevelsByEventType(string $eventType): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.id, p.level, p.title
            FROM tl_event_release_level_policy p
            INNER JOIN tl_event_type t ON t.levelAccessPermissionPackage = p.pid
            WHERE t.alias = ? AND t.levelAccessPermissionPackage > 0
            ORDER BY p.level DESC, p.id DESC',
            [$eventType],
        );

        $levels = [];

        foreach ($rows as $row) {
            $levels[] = new ReleaseLevel((int) $row['id'], (int) $row['level'], (string) $row['title']);
        }

        return $levels;
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
