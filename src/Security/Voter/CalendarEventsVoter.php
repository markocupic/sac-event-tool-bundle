<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
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
use Contao\EventReleaseLevelPolicyModel;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CalendarEventsVoter extends Voter
{
    public const CAN_DELETE_EVENT = 'sacevt_can_delete_event';
    public const CAN_WRITE_EVENT = 'sacevt_can_write_event';
    public const CAN_UPGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_upgrade_event_release_level';
    public const CAN_DOWNGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_downgrade_event_release_level';

    private ContaoFramework $framework;

    // Adapters
    private Adapter $calendarEvent;
    private Adapter $calendarEventsHelper;
    private Adapter $eventReleaseLevelPolicy;
    private Adapter $stringUtil;

    private ?CalendarEventsModel $event = null;
    private ?BackendUser $user = null;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        // Adapters
        $this->calendarEvent = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->eventReleaseLevelPolicy = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
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
     * - to "super-users" (-> tl_event_release_level_policy.groupReleaseLevelRights).
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
        if ($this->user->admin) {
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

        $arrEventInstructors = $this->calendarEventsHelper
            ->getInstructorsAsArray($this->event, false)
        ;

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
        $arrAllowedGroups = $this->stringUtil->deserialize($releaseLevelPolicy->groupReleaseLevelRights, true);
        $arrUserGroups = $this->stringUtil->deserialize($this->user->groups, true);

        foreach ($arrAllowedGroups as $v) {
            if (\in_array($v['group'], $arrUserGroups, false)) {
                if ($v['canDelete']) {
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
     * - to "super-users" (-> tl_event_release_level_policy.groupReleaseLevelRights).
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
        if ($this->user->admin) {
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

        $arrEventInstructors = $this->calendarEventsHelper
            ->getInstructorsAsArray($this->event, false)
        ;

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
        $arrAllowedGroups = $this->stringUtil->deserialize($releaseLevelPolicy->groupReleaseLevelRights, true);
        $arrUserGroups = $this->stringUtil->deserialize($this->user->groups, true);

        foreach ($arrAllowedGroups as $v) {
            if (\in_array($v['group'], $arrUserGroups, false)) {
                if ($v['canWrite']) {
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
     * - to "super-users" (-> tl_event_release_level_policy.groupReleaseLevelRights).
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
            // Grant delete-access if the event is not assigned to a release level.
            return true;
        }

        // Allow deletion to admins.
        if ($this->user->admin) {
            return true;
        }

        $arrEventInstructors = $this->calendarEventsHelper
            ->getInstructorsAsArray($this->event, false)
        ;

        if ((int) $this->user->id === (int) $this->event->author || \in_array($this->user->id, $arrEventInstructors, false)) {
            if ($releaseLevelPolicy->allowWriteAccessToAuthor) {
                // User is author or instructor and is allowed to upgrade the event
                if (self::CAN_UPGRADE_EVENT_RELEASE_LEVEL === $attribute && $releaseLevelPolicy->allowSwitchingToNextLevel) {
                    return true;
                }
                // User is author or instructor and is allowed to downgrade the event
                if (self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL === $attribute && $releaseLevelPolicy->allowSwitchingToPrevLevel) {
                    return true;
                }
            }
        }

        // Check if the user is member in an allowed group
        $arrAllowedGroups = $this->stringUtil->deserialize($releaseLevelPolicy->groupReleaseLevelRights, true);
        $arrUserGroups = $this->stringUtil->deserialize($this->user->groups, true);

        foreach ($arrAllowedGroups as $v) {
            $arrAllowedGroups[$v['group']] = $v;

            if (\in_array($v['group'], $arrUserGroups, false)) {
                if (self::CAN_UPGRADE_EVENT_RELEASE_LEVEL === $attribute) {
                    if ('up' === $v['releaseLevelRights'] || 'upAndDown' === $v['releaseLevelRights']) {
                        return true;
                    }
                }

                if (self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL === $attribute) {
                    if ('down' === $v['releaseLevelRights'] || 'upAndDown' === $v['releaseLevelRights']) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
