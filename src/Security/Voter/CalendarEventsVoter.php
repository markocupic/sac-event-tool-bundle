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

namespace Markocupic\SacEventToolBundle\Security\Voter;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class CalendarEventsVoter extends Voter
{
    public const CAN_DELETE_EVENT = 'sacevt_can_delete_event';
    public const CAN_WRITE_EVENT = 'sacevt_can_write_event';
    public const CAN_UPGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_upgrade_event_release_level';
    public const CAN_DOWNGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_downgrade_event_release_level';

    // Adapters
    private Adapter $calendarEvent;
    private Adapter $calendarEventsHelper;
    private Adapter $eventReleaseLevelPolicy;
    private Adapter $stringUtil;

    private CalendarEventsModel|null $event = null;
    private BackendUser|null $user = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
    ) {
        // Adapters
        $this->calendarEvent = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->eventReleaseLevelPolicy = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    /**
     * Grant switch-release-level-access (upgrade/downgrade)...
     * - to all users, if there is no release package assigned to the calendar (tl_calendar).
     * - do not allow downgrading if the event release level is on the first level
     * - do not allow upgrading if the event release level is on the last level
     * but allow upgrading or downgrading...
     * - to admins
     * - to permitted event-authors (-> tl_event_release_level_policy.allowWriteAccessToAuthor
     * - to permitted event-instructors (-> tl_event_release_level_policy.allowWriteAccessToInstructors
     * - to "super-users" (-> tl_event_release_level_policy.groupReleaseLevelPerm).
     *
     * @throws \Exception
     */
    public function canChangeReleaseLevel(CalendarEventsModel $eventsModel, BackendUser $user, EventReleaseLevelPolicyModel $eventReleaseLevelPolicyModel, string $direction): bool
    {
        if ('up' !== $direction && 'down' !== $direction) {
            throw new \Exception(sprintf('Direction must be "up" or "down" "%s" given!', $direction));
        }

        if ('up' === $direction) {
            if ($eventReleaseLevelPolicyModel === $eventReleaseLevelPolicyModel::findLastLevelByEventId($eventsModel->id)) {
                return false;
            }
        } else {
            if ($eventReleaseLevelPolicyModel === $eventReleaseLevelPolicyModel::findFirstLevelByEventId($eventsModel->id)) {
                return false;
            }
        }

        // Allow switching release level to admins.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $arrEventInstructors = $this->calendarEventsHelper->getInstructorsAsArray($eventsModel);

        if ((int) $user->id === (int) $eventsModel->author || \in_array($user->id, $arrEventInstructors, false)) {
            if ($eventReleaseLevelPolicyModel->allowWriteAccessToAuthor) {
                if ('up' === $direction) {
                    // User is author or instructor and is allowed to upgrade the event
                    if ($eventReleaseLevelPolicyModel->allowSwitchingToNextLevel) {
                        return true;
                    }
                } else {
                    if ($eventReleaseLevelPolicyModel->allowSwitchingToPrevLevel) {
                        return true;
                    }
                }
            }
        }

        // Check if the user is member in an allowed group
        $arrAllowedGroups = $this->stringUtil->deserialize($eventReleaseLevelPolicyModel->groupReleaseLevelPerm, true);
        $arrUserGroups = $this->stringUtil->deserialize($user->groups, true);

        foreach ($arrAllowedGroups as $v) {
            if (!empty($v['group']) && \in_array($v['group'], $arrUserGroups, false)) {
                $arrPerm = isset($v['permissions']) && \is_array($v['permissions']) ? $v['permissions'] : [];

                if ('up' === $direction) {
                    if (\in_array('canRelLevelUp', $arrPerm, true)) {
                        // User is author or instructor and is allowed to upgrade the event
                        return true;
                    }
                } else {
                    if (\in_array('canRelLevelDown', $arrPerm, true)) {
                        // User is author or instructor and is allowed to downgrade the event
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function supports($attribute, $subject): bool
    {
        return \in_array(
            $attribute,
            [
                self::CAN_WRITE_EVENT,
                self::CAN_DELETE_EVENT,
                self::CAN_UPGRADE_EVENT_RELEASE_LEVEL,
                self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL,
            ],
            true
        );
    }

    /**
     * @param $subject
     *
     * @throws \Exception
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $this->user = $token->getUser();

        if (!$this->user instanceof BackendUser) {
            // the user must be logged in; if not, deny access
            return false;
        }

        $this->event = $this->calendarEvent->findByPk($subject);

        if (null === $this->event) {
            return false;
        }

        switch ($attribute) {
            case self::CAN_DELETE_EVENT:
                return $this->canDeleteEvent();

            case self::CAN_WRITE_EVENT:
                return $this->canWriteEvent();

            case self::CAN_UPGRADE_EVENT_RELEASE_LEVEL:
            case self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL:
                return $this->canSwitchReleaseLevel($attribute);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * Grant delete-access...
     * - to all users, if there is no release package assigned to the calendar (tl_calendar).
     * - to admins
     * - to permitted event-authors (-> tl_event_release_level_policy.allowDeleteAccessToAuthor
     * - to permitted event-instructors (-> tl_event_release_level_policy.allowDeleteAccessToInstructors
     * - to "super-users" (-> tl_event_release_level_policy.groupEventPerm).
     *
     * @throws \Exception
     */
    private function canDeleteEvent(): bool
    {
        if (!empty($this->event->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findByPk($this->event->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(sprintf($msg, $this->event->id));
            }
        } else {
            // Grant delete-access if the event is not assigned to a release level.
            return true;
        }

        // Allow deletion to admins.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($releaseLevelPolicy->allowDeleteAccessToAuthor) {
            if ((int) $this->user->id === (int) $this->event->author) {
                // Grant delete-access if...
                // authors have delete-access
                // and
                // the user has the role "author" on the current event
                return true;
            }
        }

        $arrEventInstructors = $this->calendarEventsHelper->getInstructorsAsArray($this->event);

        if ($releaseLevelPolicy->allowDeleteAccessToInstructors) {
            if (\in_array($this->user->id, $arrEventInstructors, false)) {
                // Grant delete-access if...
                // instructors have delete-access
                // and
                // the user has the role "instructor" on the current event
                return true;
            }
        }

        // Check if the user is member in an allowed group
        $arrAllowedGroups = $this->stringUtil->deserialize($releaseLevelPolicy->groupEventPerm, true);
        $arrUserGroups = $this->stringUtil->deserialize($this->user->groups, true);

        foreach ($arrAllowedGroups as $v) {
            if (!empty($v['group']) && \in_array($v['group'], $arrUserGroups, false)) {
                $arrPerm = isset($v['permissions']) && \is_array($v['permissions']) ? $v['permissions'] : [];

                if (\in_array('canDeleteEvent', $arrPerm, true)) {
                    // Grant delete-access to "super-users"
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Grant write-access...
     * - to all users, if there is no release package assigned to the calendar (tl_calendar).
     * - to admins
     * - to permitted event-authors (-> tl_event_release_level_policy.allowWriteAccessToAuthor
     * - to permitted event-instructors (-> tl_event_release_level_policy.allowWriteAccessToInstructors
     * - to "super-users" (-> tl_event_release_level_policy.groupEventPerm).
     *
     * @throws \Exception
     */
    private function canWriteEvent(): bool
    {
        if (!empty($this->event->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findByPk($this->event->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(sprintf($msg, $this->event->id));
            }
        } else {
            // Grant write- or write-access if the event is not assigned to a release level.
            return true;
        }

        // Allow deletion to admins.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($releaseLevelPolicy->allowWriteAccessToAuthor) {
            if ((int) $this->user->id === (int) $this->event->author) {
                // Grant write-access if...
                // authors have write-access
                // and
                // the user has the role "author" on the current event
                return true;
            }
        }

        $arrEventInstructors = $this->calendarEventsHelper->getInstructorsAsArray($this->event);

        if ($releaseLevelPolicy->allowWriteAccessToInstructors) {
            if (\in_array($this->user->id, $arrEventInstructors, false)) {
                // Grant write-access if...
                // instructors have write-access
                // and
                // the user has the role "instructor" on the current event
                return true;
            }
        }

        // Check if the user is member in an allowed group
        $arrAllowedGroups = $this->stringUtil->deserialize($releaseLevelPolicy->groupEventPerm, true);
        $arrUserGroups = $this->stringUtil->deserialize($this->user->groups, true);

        foreach ($arrAllowedGroups as $v) {
            if (!empty($v['group']) && \in_array($v['group'], $arrUserGroups, false)) {
                $arrPerm = isset($v['permissions']) && \is_array($v['permissions']) ? $v['permissions'] : [];

                if (\in_array('canWriteEvent', $arrPerm, true)) {
                    // Grant write-access to "super-users"
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Grant switch-release-level-access (upgrade/downgrade)...
     * - to all users, if there is no release package assigned to the calendar (tl_calendar).
     * - to admins
     * - to permitted event-authors (-> tl_event_release_level_policy.allowWriteAccessToAuthor
     * - to permitted event-instructors (-> tl_event_release_level_policy.allowWriteAccessToInstructors
     * - to "super-users" (-> tl_event_release_level_policy.groupReleaseLevelPerm).
     *
     * @throws \Exception
     */
    private function canSwitchReleaseLevel(string $attribute): bool
    {
        if (!empty($this->event->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findByPk($this->event->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(sprintf($msg, $this->event->id));
            }
        } else {
            // Grant write- or write-access if the event is not assigned to a release level.
            return true;
        }

        if (self::CAN_UPGRADE_EVENT_RELEASE_LEVEL === $attribute) {
            $direction = 'up';
        } elseif (self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL === $attribute) {
            $direction = 'down';
        } else {
            throw new \LogicException(sprintf('$attribute should be either "%s" or "%s" "%s" given.', self::CAN_UPGRADE_EVENT_RELEASE_LEVEL, self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL, $attribute));
        }

        return $this->canChangeReleaseLevel($this->event, $this->user, $releaseLevelPolicy, $direction);
    }
}
