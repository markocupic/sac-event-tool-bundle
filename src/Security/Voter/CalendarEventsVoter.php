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

/**
 * Decides what a back end user may do with an event (subject: the event ID).
 *
 * Events that are not assigned to a release level: every back end user is granted access.
 *
 * Events that are assigned to a release level: the permissions are defined in the
 * release level (tl_event_release_level_policy). Admins are granted access
 * (except when changing the release level, see EventReleaseLevelTransitionVoter).
 */
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

    private Adapter $calendarEventsModel;

    private Adapter $eventReleaseLevelPolicyModel;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        #[Autowire('%sacevt.event_registration.config.reg_start_time_offset%')]
        private readonly int $regStartTimeOffset,
    ) {
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->eventReleaseLevelPolicyModel = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
    }

    /**
     * Decides whether the user may shift the event by one level from the given
     * release level ($level) in the given direction ("up" or "down").
     *
     * Deny access...
     * - if the event is already on the highest level (up) or on the lowest level (down)
     * Grant access...
     * - to admins
     * - to authors (allowWriteAccessToAuthor) and instructors (allowWriteAccessToInstructors)
     *   of the event, if the release level allows switching to the next/previous level
     *   (allowSwitchingToNextLevel/allowSwitchingToPrevLevel)
     * - to "super-users" --> tl_event_release_level_policy.groupReleaseLevelPerm (canRelLevelUp/canRelLevelDown).
     *
     * Whether the target level belongs to the release level system of the event
     * type and the time rules of the calendar are checked by EventReleaseLevelTransitionVoter.
     *
     * @throws \Exception
     */
    public function canChangeReleaseLevel(CalendarEventsModel $event, BackendUser $user, EventReleaseLevelPolicyModel $level, string $direction): bool
    {
        if ('up' !== $direction && 'down' !== $direction) {
            throw new \InvalidArgumentException(\sprintf('Direction must be "up" or "down" "%s" given!', $direction));
        }

        $isUpgrade = 'up' === $direction;

        // The event is already on the highest or the lowest level
        $boundaryLevel = $isUpgrade ? $this->eventReleaseLevelPolicyModel->findMaxLevelByEventId($event->id) : $this->eventReleaseLevelPolicyModel->findMinLevelByEventId($event->id);

        if ((int) $level->id === (int) $boundaryLevel?->id) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $canSwitch = $isUpgrade ? $level->allowSwitchingToNextLevel : $level->allowSwitchingToPrevLevel;

        if ($canSwitch && (($level->allowWriteAccessToAuthor && $this->isAuthor($user, $event)) || ($level->allowWriteAccessToInstructors && $this->isInstructor($user, $event)))) {
            return true;
        }

        return $this->hasGroupPermission($user, $level->groupReleaseLevelPerm, $isUpgrade ? 'canRelLevelUp' : 'canRelLevelDown');
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::EVENT_PERMISSIONS_ALL, true);
    }

    /**
     * @throws \Exception
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // The user must be logged in to the back end
        if (!$user instanceof BackendUser) {
            return false;
        }

        $event = $this->calendarEventsModel->findById($subject);

        if (null === $event) {
            return false;
        }

        if (self::CAN_UPGRADE_EVENT_RELEASE_LEVEL === $attribute || self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL === $attribute) {
            return $this->canSwitchReleaseLevel($token, $event, $attribute);
        }

        $releaseLevel = $this->getReleaseLevel($event);

        // Grant access to all users if the event is not assigned to a release level
        if (null === $releaseLevel) {
            return true;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        return match ($attribute) {
            self::CAN_DELETE_EVENT => $this->canDeleteEvent($user, $event, $releaseLevel),
            self::CAN_WRITE_EVENT => $this->canWriteEvent($user, $event, $releaseLevel),
            self::CAN_CUT_EVENT => $this->canCutEvent($user, $event, $releaseLevel),
            self::CAN_ADMINISTER_EVENT_REGISTRATIONS => $this->canAdministerEventRegistrations($user, $event, $releaseLevel),
            default => throw new \LogicException(\sprintf('You vote on a unsupported attribute "%s"!', $attribute)),
        };
    }

    /**
     * Grant delete-access (non-admins)...
     * - to authors --> tl_event_release_level_policy.allowDeleteAccessToAuthor
     * - to instructors --> tl_event_release_level_policy.allowDeleteAccessToInstructors
     * - to "super-users" --> tl_event_release_level_policy.groupEventPerm (canDeleteEvent).
     */
    private function canDeleteEvent(BackendUser $user, CalendarEventsModel $event, EventReleaseLevelPolicyModel $releaseLevel): bool
    {
        return ($releaseLevel->allowDeleteAccessToAuthor && $this->isAuthor($user, $event))
            || ($releaseLevel->allowDeleteAccessToInstructors && $this->isInstructor($user, $event))
            || $this->hasGroupPermission($user, $releaseLevel->groupEventPerm, 'canDeleteEvent');
    }

    /**
     * Grant cut-access (non-admins)...
     * - to authors --> tl_event_release_level_policy.allowCutAccessToAuthor
     * - to instructors --> tl_event_release_level_policy.allowCutAccessToInstructors
     * - to "super-users" --> tl_event_release_level_policy.groupEventPerm (canCutEvent).
     */
    private function canCutEvent(BackendUser $user, CalendarEventsModel $event, EventReleaseLevelPolicyModel $releaseLevel): bool
    {
        return ($releaseLevel->allowCutAccessToAuthor && $this->isAuthor($user, $event))
            || ($releaseLevel->allowCutAccessToInstructors && $this->isInstructor($user, $event))
            || $this->hasGroupPermission($user, $releaseLevel->groupEventPerm, 'canCutEvent');
    }

    /**
     * Grant write-access (non-admins)...
     * - to authors --> tl_event_release_level_policy.allowWriteAccessToAuthor
     * - to instructors --> tl_event_release_level_policy.allowWriteAccessToInstructors
     * - to the user who is charged to do the registration admin work (tl_calendar_events.registrationGoesTo)
     * - to "super-users" --> tl_event_release_level_policy.groupEventPerm (canWriteEvent).
     */
    private function canWriteEvent(BackendUser $user, CalendarEventsModel $event, EventReleaseLevelPolicyModel $releaseLevel): bool
    {
        return ($releaseLevel->allowWriteAccessToAuthor && $this->isAuthor($user, $event))
            || ($releaseLevel->allowWriteAccessToInstructors && $this->isInstructor($user, $event))
            || $this->isRegistrationCoordinator($user, $event)
            || $this->hasGroupPermission($user, $releaseLevel->groupEventPerm, 'canWriteEvent');
    }

    /**
     * Allow to administer event registrations (means the user is allowed to add new
     * event registrations too). Non-admins are denied access before the registration
     * period has started; afterwards access is granted...
     * - to authors --> tl_event_release_level_policy.allowAdministerEventRegistrationsToAuthors
     * - to instructors --> tl_event_release_level_policy.allowAdministerEventRegistrationsToInstructors
     * - to the user who is charged to do the registration admin work (tl_calendar_events.registrationGoesTo)
     * - to "super-users" --> tl_event_release_level_policy.groupEventPerm (canAdministerEventRegistrations).
     */
    private function canAdministerEventRegistrations(BackendUser $user, CalendarEventsModel $event, EventReleaseLevelPolicyModel $releaseLevel): bool
    {
        $registrationStartTime = $event->registrationStartDate + $this->regStartTimeOffset;

        if ($event->setRegistrationPeriod && $registrationStartTime > time()) {
            return false;
        }

        return ($releaseLevel->allowAdministerEventRegistrationsToAuthors && $this->isAuthor($user, $event))
            || ($releaseLevel->allowAdministerEventRegistrationsToInstructors && $this->isInstructor($user, $event))
            || $this->isRegistrationCoordinator($user, $event)
            || $this->hasGroupPermission($user, $releaseLevel->groupEventPerm, 'canAdministerEventRegistrations');
    }

    /**
     * Upgrade or downgrade by one level (the arrows in the event list): Grant access...
     * - to all users, if the event is not assigned to a release level
     * - if the user may switch the event to the next or previous level, see EventReleaseLevelTransitionVoter
     *   (release level system of the event type, permissions of the release level and time rules of the calendar).
     *
     * @throws \Exception
     */
    private function canSwitchReleaseLevel(TokenInterface $token, CalendarEventsModel $event, string $attribute): bool
    {
        $currentLevel = $this->getReleaseLevel($event);

        if (null === $currentLevel) {
            return true;
        }

        $targetLevel = match ($attribute) {
            self::CAN_UPGRADE_EVENT_RELEASE_LEVEL => $this->eventReleaseLevelPolicyModel->findNextLevel($currentLevel->id),
            self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL => $this->eventReleaseLevelPolicyModel->findPrevLevel($currentLevel->id),
            default => throw new \LogicException(\sprintf('$attribute should be either "%s" or "%s" "%s" given.', self::CAN_UPGRADE_EVENT_RELEASE_LEVEL, self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL, $attribute)),
        };

        // The event is already on the highest or the lowest level
        if (null === $targetLevel) {
            return false;
        }

        return $this->accessDecisionManager->decide($token, [EventReleaseLevelTransitionVoter::CAN_SWITCH_TO_EVENT_RELEASE_LEVEL], new EventReleaseLevelTransition($event, $targetLevel));
    }

    /**
     * Returns null if the event is not assigned to a release level.
     *
     * @throws \Exception if the assigned release level does not exist
     */
    private function getReleaseLevel(CalendarEventsModel $event): EventReleaseLevelPolicyModel|null
    {
        if (empty($event->eventReleaseLevel)) {
            return null;
        }

        $releaseLevel = $this->eventReleaseLevelPolicyModel->findById($event->eventReleaseLevel);

        if (null === $releaseLevel) {
            throw new \RuntimeException(\sprintf('Release-level model not found for tl_calendar_events with ID %d.', $event->id));
        }

        return $releaseLevel;
    }

    private function isAuthor(BackendUser $user, CalendarEventsModel $event): bool
    {
        return (int) $user->id === (int) $event->author;
    }

    private function isInstructor(BackendUser $user, CalendarEventsModel $event): bool
    {
        $instructorIds = array_map('intval', $this->calendarEventsUtil->getInstructorsAsArray($event));

        return \in_array((int) $user->id, $instructorIds, true);
    }

    /**
     * The user is charged to do the registration admin work (tl_calendar_events.registrationGoesTo).
     */
    private function isRegistrationCoordinator(BackendUser $user, CalendarEventsModel $event): bool
    {
        return (int) $event->registrationGoesTo > 0 && (int) $user->id === (int) $event->registrationGoesTo;
    }

    /**
     * Checks whether the user is member of a group that has the permission.
     *
     * @param mixed $groupPermissions Serialized multi column wizard value: [['group' => 1, 'permissions' => ['canWriteEvent', ...]], ...]
     */
    private function hasGroupPermission(BackendUser $user, mixed $groupPermissions, string $permission): bool
    {
        $userGroups = StringUtil::deserialize($user->groups, true);

        foreach (StringUtil::deserialize($groupPermissions, true) as $groupPermission) {
            if (empty($groupPermission['group']) || !\in_array($groupPermission['group'], $userGroups, false)) {
                continue;
            }

            $permissions = \is_array($groupPermission['permissions'] ?? null) ? $groupPermission['permissions'] : [];

            if (\in_array($permission, $permissions, true)) {
                return true;
            }
        }

        return false;
    }
}
