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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CalendarEventsVoter extends Voter
{
    public const CAN_DELETE_EVENT = 'sacevt_can_delete_event';
    public const CAN_WRITE_EVENT = 'sacevt_can_write_event';
    public const CAN_CUT_EVENT = 'sacevt_can_cut_event';
    public const CAN_UPGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_upgrade_event_release_level';
    public const CAN_DOWNGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_downgrade_event_release_level';
    public const CAN_ADMINISTER_EVENT_REGISTRATIONS = 'sacevt_can_administer_event_registrations';

    private const EVENT_PERMISSIONS_ALL = [
        self::CAN_DELETE_EVENT,
        self::CAN_WRITE_EVENT,
        self::CAN_CUT_EVENT,
        self::CAN_UPGRADE_EVENT_RELEASE_LEVEL,
        self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL,
        self::CAN_ADMINISTER_EVENT_REGISTRATIONS,
    ];

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
     * - to permitted event-authors --> tl_event_release_level_policy.allowWriteAccessToAuthor
     * - to permitted event-instructors --> tl_event_release_level_policy.allowWriteAccessToInstructors
     * - to "super-users" --> tl_event_release_level_policy.groupReleaseLevelPerm.
     *
     * @throws \Exception
     */
    public function canChangeReleaseLevel(CalendarEventsModel $eventsModel, BackendUser $user, EventReleaseLevelPolicyModel $eventReleaseLevelPolicyModel, string $direction): bool
    {
        if ('up' !== $direction && 'down' !== $direction) {
            throw new \Exception(sprintf('Direction must be "up" or "down" "%s" given!', $direction));
        }

        if ('up' === $direction) {
            if ($eventReleaseLevelPolicyModel === $eventReleaseLevelPolicyModel::findHighestLevelByEventId($eventsModel->id)) {
                return false;
            }
        } else {
            if ($eventReleaseLevelPolicyModel === $eventReleaseLevelPolicyModel::findLowestLevelByEventId($eventsModel->id)) {
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

        // Check if the user is member of an allowed group
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
            self::EVENT_PERMISSIONS_ALL,
            true,
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

        return match ($attribute) {
            self::CAN_DELETE_EVENT => $this->canDeleteEvent(),
            self::CAN_WRITE_EVENT => $this->canWriteEvent(),
            self::CAN_CUT_EVENT => $this->canCutEvent(),
            self::CAN_UPGRADE_EVENT_RELEASE_LEVEL, self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL => $this->canSwitchReleaseLevel($attribute),
            self::CAN_ADMINISTER_EVENT_REGISTRATIONS => $this->canAdministerEventRegistrations(),
            default => throw new \LogicException(sprintf('You vote on a unsupported attribute "%s"!', $attribute)),
        };
    }

    /**
     * Grant delete-access...
     * - to all users, if there is no release package assigned to the calendar (tl_calendar).
     * - to admins
     * - to permitted event-authors --> tl_event_release_level_policy.allowDeleteAccessToAuthor
     * - to permitted event-instructors --> tl_event_release_level_policy.allowDeleteAccessToInstructors
     * - to "super-users" --> tl_event_release_level_policy.groupEventPerm.
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

        // Check if the user is member of an allowed group
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
     * Grant cut-access...
     * - to all users, if there is no release package assigned to the calendar (tl_calendar).
     * - to admins
     * - to permitted event-authors --> tl_event_release_level_policy.allowCutAccessToAuthor
     * - to permitted event-instructors --> tl_event_release_level_policy.allowCutAccessToInstructors
     * - to "super-users" --> tl_event_release_level_policy.groupEventPerm.
     *
     * @throws \Exception
     */
    private function canCutEvent(): bool
    {
        if (!empty($this->event->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findByPk($this->event->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(sprintf($msg, $this->event->id));
            }
        } else {
            // Grant cut-access if the event is not assigned to a release level.
            return true;
        }

        // Allow cut event to admins.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($releaseLevelPolicy->allowCutAccessToAuthor) {
            if ((int) $this->user->id === (int) $this->event->author) {
                // Grant cut-access if...
                // authors have cut-access
                // and
                // the user has the role "author" on the current event
                return true;
            }
        }

        $arrEventInstructors = $this->calendarEventsHelper->getInstructorsAsArray($this->event);

        if ($releaseLevelPolicy->allowCutAccessToInstructors) {
            if (\in_array($this->user->id, $arrEventInstructors, false)) {
                // Grant cut-access if...
                // instructors have cut-access
                // and
                // the user has the role "instructor" on the current event
                return true;
            }
        }

        // Check if the user is member of an allowed group
        $arrAllowedGroups = $this->stringUtil->deserialize($releaseLevelPolicy->groupEventPerm, true);
        $arrUserGroups = $this->stringUtil->deserialize($this->user->groups, true);

        foreach ($arrAllowedGroups as $v) {
            if (!empty($v['group']) && \in_array($v['group'], $arrUserGroups, false)) {
                $arrPerm = isset($v['permissions']) && \is_array($v['permissions']) ? $v['permissions'] : [];

                if (\in_array('canCutEvent', $arrPerm, true)) {
                    // Grant cut-access to "super-users"
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
     * - to permitted event-authors --> tl_event_release_level_policy.allowWriteAccessToAuthor
     * - to permitted event-instructors --> tl_event_release_level_policy.allowWriteAccessToInstructors
     * - to "super-users" --> tl_event_release_level_policy.groupEventPerm.
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

        // Allow write-access to admins.
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

        // Check if the user is member of an allowed group
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
     * Allow to administer event registrations (means the user is allowed to add new event registrations too)...
     * - if the event is not assigned to an event release level
     * - to all admins (regardless of the current time)
     * - to allowed instructors if the registrations start date has expired
     * - to allowed authors if the registrations start date has expired
     * - if user is charged to do the registration admin work (tl_calendar_events.registrationGoesTo)
     * - to allowed super-users.
     *
     * @throws \Exception
     */
    private function canAdministerEventRegistrations(): bool
    {
        if (!empty($this->event->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findByPk($this->event->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(sprintf($msg, $this->event->id));
            }
        } else {
            // Grant access if the event is not assigned to a release level.
            return true;
        }

        // Grant action to admins.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($this->event->setRegistrationPeriod && $this->event->registrationStartDate > time()) {
            return false;
        }

        if ($releaseLevelPolicy->allowAdministerEventRegistrationsToAuthors) {
            if ((int) $this->user->id === (int) $this->event->author) {
                // Grant action if...
                // if authors are allowed
                // and
                // the user has the role "author" on the current event
                return true;
            }
        }

        $arrEventInstructors = $this->calendarEventsHelper->getInstructorsAsArray($this->event);

        if ($releaseLevelPolicy->allowAdministerEventRegistrationsToInstructors) {
            if (\in_array($this->user->id, $arrEventInstructors, true)) {
                // Grant action if...
                // instructors are allowed
                // and
                // the user has the role "instructor" on the current event
                return true;
            }
        }

        if (!empty($this->event->registrationGoesTo)) {
            if ($this->user->id === $this->event->registrationGoesTo) {
                return true;
            }
        }

        // Check if the user is member of an allowed group
        $arrAllowedGroups = $this->stringUtil->deserialize($releaseLevelPolicy->groupEventPerm, true);
        $arrUserGroups = $this->stringUtil->deserialize($this->user->groups, true);

        foreach ($arrAllowedGroups as $v) {
            if (!empty($v['group']) && \in_array($v['group'], $arrUserGroups, false)) {
                $arrPerm = isset($v['permissions']) && \is_array($v['permissions']) ? $v['permissions'] : [];

                if (\in_array('canAdministerEventRegistrations', $arrPerm, true)) {
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
     * - to permitted event-authors --> tl_event_release_level_policy.allowWriteAccessToAuthor
     * - to permitted event-instructors --> tl_event_release_level_policy.allowWriteAccessToInstructors
     * - to "super-users" --> tl_event_release_level_policy.groupReleaseLevelPerm.
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
