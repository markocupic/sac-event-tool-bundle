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

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CalendarEventsVoter extends Voter
{
    public const string CAN_DELETE_EVENT = 'sacevt_can_delete_event';

    public const string CAN_WRITE_EVENT = 'sacevt_can_write_event';

    public const string CAN_CUT_EVENT = 'sacevt_can_cut_event';

    public const string CAN_UPGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_upgrade_event_release_level';

    public const string CAN_DOWNGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_downgrade_event_release_level';

    public const string CAN_ADMINISTER_EVENT_REGISTRATIONS = 'sacevt_can_administer_event_registrations';

    private const array EVENT_PERMISSIONS_ALL = [
        self::CAN_DELETE_EVENT,
        self::CAN_WRITE_EVENT,
        self::CAN_CUT_EVENT,
        self::CAN_UPGRADE_EVENT_RELEASE_LEVEL,
        self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL,
        self::CAN_ADMINISTER_EVENT_REGISTRATIONS,
    ];

    // Adapters
    private Adapter $calendarEvent;

    private Adapter $eventReleaseLevelPolicy;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        #[Autowire('%sacevt.event_registration.config.reg_start_time_offset%')]
        private readonly int $regStartTimeOffset,
    ) {
        // Adapters
        $this->calendarEvent = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->eventReleaseLevelPolicy = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
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
            throw new \Exception(\sprintf('Direction must be "up" or "down" "%s" given!', $direction));
        }

        if ('up' === $direction) {
            if ((int) $eventReleaseLevelPolicyModel->id === (int) $eventReleaseLevelPolicyModel::findMaxLevelByEventId($eventsModel->id)?->id) {
                return false;
            }
        } else {
            if ((int) $eventReleaseLevelPolicyModel->id === (int) $eventReleaseLevelPolicyModel::findMinLevelByEventId($eventsModel->id)?->id) {
                return false;
            }
        }

        // Allow switching release level to admins.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $arrEventInstructors = $this->calendarEventsUtil->getInstructorsAsArray($eventsModel);

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
        $arrAllowedGroups = StringUtil::deserialize($eventReleaseLevelPolicyModel->groupReleaseLevelPerm, true);
        $arrUserGroups = StringUtil::deserialize($user->groups, true);

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
     * @throws \Exception
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof BackendUser) {
            // the user must be logged in; if not, deny access
            return false;
        }

        $calEvent = $this->calendarEvent->findById($subject);

        if (null === $calEvent) {
            return false;
        }

        return match ($attribute) {
            self::CAN_DELETE_EVENT => $this->canDeleteEvent($token, $calEvent),
            self::CAN_WRITE_EVENT => $this->canWriteEvent($token, $calEvent),
            self::CAN_CUT_EVENT => $this->canCutEvent($token, $calEvent),
            self::CAN_UPGRADE_EVENT_RELEASE_LEVEL => $this->canSwitchReleaseLevel($token, $calEvent, $attribute),
            self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL => $this->canSwitchReleaseLevel($token, $calEvent, $attribute),
            self::CAN_ADMINISTER_EVENT_REGISTRATIONS => $this->canAdministerEventRegistrations($token, $calEvent),
            default => throw new \LogicException(\sprintf('You vote on a unsupported attribute "%s"!', $attribute)),
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
    private function canDeleteEvent(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        $user = $token->getUser();

        if (!empty($calEvent->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findById($calEvent->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(\sprintf($msg, $calEvent->id));
            }
        } else {
            // Grant delete-access if the event is not assigned to a release level.
            return true;
        }

        // Allow deletion to admins.
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        if ($releaseLevelPolicy->allowDeleteAccessToAuthor) {
            if ((int) $user->id === (int) $calEvent->author) {
                // Grant delete-access if... authors have delete-access and the user has the role
                // "author" on the current event
                return true;
            }
        }

        $arrEventInstructors = $this->calendarEventsUtil->getInstructorsAsArray($calEvent);

        if ($releaseLevelPolicy->allowDeleteAccessToInstructors) {
            if (\in_array($user->id, $arrEventInstructors, false)) {
                // Grant delete-access if... instructors have delete-access and the user has the
                // role "instructor" on the current event
                return true;
            }
        }

        // Check if the user is member of an allowed group
        $arrAllowedGroups = StringUtil::deserialize($releaseLevelPolicy->groupEventPerm, true);
        $arrUserGroups = StringUtil::deserialize($user->groups, true);

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
    private function canCutEvent(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        /** @var BackendUser $user */
        $user = $token->getUser();

        if (!empty($calEvent->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findById($calEvent->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(\sprintf($msg, $calEvent->id));
            }
        } else {
            // Grant cut-access if the event is not assigned to a release level.
            return true;
        }

        // Allow cut event to admins.
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        if ($releaseLevelPolicy->allowCutAccessToAuthor) {
            if ((int) $user->id === (int) $calEvent->author) {
                // Grant cut-access if... authors have cut-access and the user has the role
                // "author" on the current event
                return true;
            }
        }

        $arrEventInstructors = $this->calendarEventsUtil->getInstructorsAsArray($calEvent);

        if ($releaseLevelPolicy->allowCutAccessToInstructors) {
            if (\in_array($user->id, $arrEventInstructors, false)) {
                // Grant cut-access if... instructors have cut-access and the user has the role
                // "instructor" on the current event
                return true;
            }
        }

        // Check if the user is member of an allowed group
        $arrAllowedGroups = StringUtil::deserialize($releaseLevelPolicy->groupEventPerm, true);
        $arrUserGroups = StringUtil::deserialize($user->groups, true);

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
     * - if user is charged to do the registration admin work (tl_calendar_events.registrationGoesTo)
     * - to "super-users" --> tl_event_release_level_policy.groupEventPerm.
     *
     * @throws \Exception
     */
    private function canWriteEvent(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        $user = $token->getUser();

        if (!empty($calEvent->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findById($calEvent->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(\sprintf($msg, $calEvent->id));
            }
        } else {
            // Grant write- or write-access if the event is not assigned to a release level.
            return true;
        }

        // Allow write-access to admins.
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        if ($releaseLevelPolicy->allowWriteAccessToAuthor) {
            if ((int) $user->id === (int) $calEvent->author) {
                // Grant write-access if... authors have write-access and the user has the role
                // "author" on the current event
                return true;
            }
        }

        $arrEventInstructors = $this->calendarEventsUtil->getInstructorsAsArray($calEvent);

        if ($releaseLevelPolicy->allowWriteAccessToInstructors) {
            if (\in_array($user->id, $arrEventInstructors, false)) {
                // Grant write-access if... instructors have write-access and the user has the
                // role "instructor" on the current event
                return true;
            }
        }

        if (!empty($calEvent->registrationGoesTo)) {
            if ($user->id === $calEvent->registrationGoesTo) {
                return true;
            }
        }

        // Check if the user is member of an allowed group
        $arrAllowedGroups = StringUtil::deserialize($releaseLevelPolicy->groupEventPerm, true);
        $arrUserGroups = StringUtil::deserialize($user->groups, true);

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
     * Allow to administer event registrations (means the user is allowed to add new
     * event registrations too)...
     * - if the event is not assigned to an event release level
     * - to all admins (regardless of the current time)
     * - to allowed instructors if the registrations start date has expired
     * - to allowed authors if the registrations start date has expired
     * - if user is charged to do the registration admin work (tl_calendar_events.registrationGoesTo)
     * - to allowed super-users.
     *
     * @throws \Exception
     */
    private function canAdministerEventRegistrations(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        $user = $token->getUser();

        if (!empty($calEvent->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findById($calEvent->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(\sprintf($msg, $calEvent->id));
            }
        } else {
            // Grant access if the event is not assigned to a release level.
            return true;
        }

        // Grant action to admins.
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $regStartTime = $calEvent->registrationStartDate + $this->regStartTimeOffset;

        if ($calEvent->setRegistrationPeriod && $regStartTime > time()) {
            return false;
        }

        if ($releaseLevelPolicy->allowAdministerEventRegistrationsToAuthors) {
            if ((int) $user->id === (int) $calEvent->author) {
                // Grant action if... if authors are allowed and the user has the role "author"
                // on the current event
                return true;
            }
        }

        $arrEventInstructors = $this->calendarEventsUtil->getInstructorsAsArray($calEvent);

        if ($releaseLevelPolicy->allowAdministerEventRegistrationsToInstructors) {
            if (\in_array($user->id, $arrEventInstructors, true)) {
                // Grant action if... instructors are allowed and the user has the role
                // "instructor" on the current event
                return true;
            }
        }

        if (!empty($calEvent->registrationGoesTo)) {
            if ($user->id === $calEvent->registrationGoesTo) {
                return true;
            }
        }

        // Check if the user is member of an allowed group
        $arrAllowedGroups = StringUtil::deserialize($releaseLevelPolicy->groupEventPerm, true);
        $arrUserGroups = StringUtil::deserialize($user->groups, true);

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
    private function canSwitchReleaseLevel(TokenInterface $token, CalendarEventsModel $calEvent, string $direction): bool
    {
        $user = $token->getUser();

        if (!empty($calEvent->eventReleaseLevel)) {
            $releaseLevelPolicy = $this->eventReleaseLevelPolicy->findById($calEvent->eventReleaseLevel);

            if (null === $releaseLevelPolicy) {
                $msg = 'Release-level model not found for tl_calendar_events with ID %d.';

                throw new \Exception(\sprintf($msg, $calEvent->id));
            }
        } else {
            // Grant write- or write-access if the event is not assigned to a release level.
            return true;
        }

        if (self::CAN_UPGRADE_EVENT_RELEASE_LEVEL === $direction) {
            $direct = 'up';
        } elseif (self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL === $direction) {
            $direct = 'down';
        } else {
            throw new \LogicException(\sprintf('$direction should be either "%s" or "%s" "%s" given.', self::CAN_UPGRADE_EVENT_RELEASE_LEVEL, self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL, $direction));
        }

        return $this->canChangeReleaseLevel($calEvent, $user, $releaseLevelPolicy, $direct);
    }
}
