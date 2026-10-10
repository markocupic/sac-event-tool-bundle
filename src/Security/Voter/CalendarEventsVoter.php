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
use Markocupic\SacEventToolBundle\EventReleaseLevel\EventReleaseLevelPermissionRules;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides what a back end user may do with an event (subject: the event ID).
 *
 * - Events that are not assigned to a release level: every back end user is granted access
 *   (except CAN_VIEW_PARTICIPANT_EVENT_HISTORY: admins, instructors and registration coordinator only).
 * - Admins are granted access (except when changing the release level, see
 *   EventReleaseLevelTransitionVoter).
 * - All other users need a permission rule of the release level of the event
 *   (tl_event_release_level_policy.permissionRules, see EventReleaseLevelPermissionRules).
 */
class CalendarEventsVoter extends Voter
{
    public const string CAN_DELETE_EVENT = 'sacevt_can_delete_event';

    public const string CAN_WRITE_EVENT = 'sacevt_can_write_event';

    public const string CAN_CUT_EVENT = 'sacevt_can_cut_event';

    public const string CAN_UPGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_upgrade_event_release_level';

    public const string CAN_DOWNGRADE_EVENT_RELEASE_LEVEL = 'sacevt_can_downgrade_event_release_level';

    public const string CAN_ADMINISTER_EVENT_REGISTRATIONS = 'sacevt_can_administer_event_registrations';

    /**
     * May the user see the event history of the participants (see Feature\ParticipantEventHistory)?
     * The access period after the event is checked by ParticipantEventHistoryVoter.
     */
    public const string CAN_VIEW_PARTICIPANT_EVENT_HISTORY = 'sacevt_can_view_participant_event_history';

    private const array EVENT_PERMISSIONS_ALL = [
        self::CAN_DELETE_EVENT,
        self::CAN_WRITE_EVENT,
        self::CAN_CUT_EVENT,
        self::CAN_UPGRADE_EVENT_RELEASE_LEVEL,
        self::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL,
        self::CAN_ADMINISTER_EVENT_REGISTRATIONS,
        self::CAN_VIEW_PARTICIPANT_EVENT_HISTORY,
    ];

    private Adapter $calendarEventsModel;

    private Adapter $eventReleaseLevelPolicyModel;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        private readonly EventReleaseLevelPermissionRules $permissionRules,
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
     * - if a permission rule of the release level grants "can_upgrade_release_level" or
     *   "can_downgrade_release_level" to the user.
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

        return $this->permissionRules->isGranted(
            $level,
            $isUpgrade ? EventReleaseLevelPermissionRules::FLAG_UPGRADE_RELEASE_LEVEL : EventReleaseLevelPermissionRules::FLAG_DOWNGRADE_RELEASE_LEVEL,
            fn (string $party): bool => $this->isParty($party, $user, $event),
            fn (int $groupId): bool => $this->security->isGranted('contao_user.groups', $groupId),
        );
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::EVENT_PERMISSIONS_ALL, true);
    }

    /**
     * @throws \Exception
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, Vote|null $vote = null): bool
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

        if (null === $releaseLevel) {
            // The event history of the participants contains personal data: without a release level,
            // only admins, the instructors and the registration coordinator of the event have access
            if (self::CAN_VIEW_PARTICIPANT_EVENT_HISTORY === $attribute) {
                return $this->accessDecisionManager->decide($token, ['ROLE_ADMIN']) || $this->isInstructor($user, $event) || $this->isRegistrationCoordinator($user, $event);
            }

            // Grant access to all users if the event is not assigned to a release level
            return true;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $isGranted = fn (string $flag): bool => $this->permissionRules->isGranted(
            $releaseLevel,
            $flag,
            fn (string $party): bool => $this->isParty($party, $user, $event),
            fn (int $groupId): bool => $this->accessDecisionManager->decide($token, ['contao_user.groups'], $groupId),
        );

        return match ($attribute) {
            self::CAN_DELETE_EVENT => $isGranted(EventReleaseLevelPermissionRules::FLAG_DELETE_EVENT),
            self::CAN_WRITE_EVENT => $isGranted(EventReleaseLevelPermissionRules::FLAG_WRITE_EVENT),
            self::CAN_CUT_EVENT => $isGranted(EventReleaseLevelPermissionRules::FLAG_CUT_EVENT),
            // Non-admins are denied access before the registration period has started
            self::CAN_ADMINISTER_EVENT_REGISTRATIONS => $this->hasRegistrationPeriodStarted($event) && $isGranted(EventReleaseLevelPermissionRules::FLAG_ADMINISTER_EVENT_REGISTRATIONS),
            self::CAN_VIEW_PARTICIPANT_EVENT_HISTORY => $isGranted(EventReleaseLevelPermissionRules::FLAG_VIEW_PARTICIPANT_EVENT_HISTORY),
            default => throw new \LogicException(\sprintf('You vote on a unsupported attribute "%s"!', $attribute)),
        };
    }

    /**
     * Upgrade or downgrade by one level (the arrows in the event list): Grant access...
     * - to all users, if the event is not assigned to a release level
     * - if the user may switch the event to the next or previous level, see EventReleaseLevelTransitionVoter
     *   (release level system of the event type, permission rules of the release level and time rules of the calendar).
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

    private function hasRegistrationPeriodStarted(CalendarEventsModel $event): bool
    {
        if (!$event->setRegistrationPeriod) {
            return true;
        }

        return $event->registrationStartDate + $this->regStartTimeOffset <= time();
    }

    /**
     * Is the user the given party (see EventReleaseLevelPermissionRules::PARTY_*) of the event?
     */
    private function isParty(string $party, BackendUser $user, CalendarEventsModel $event): bool
    {
        return match ($party) {
            EventReleaseLevelPermissionRules::PARTY_EVENT_AUTHOR => $this->isAuthor($user, $event),
            EventReleaseLevelPermissionRules::PARTY_MAIN_INSTRUCTOR => $this->isMainInstructor($user, $event),
            EventReleaseLevelPermissionRules::PARTY_EVENT_INSTRUCTORS => $this->isInstructor($user, $event),
            EventReleaseLevelPermissionRules::PARTY_REGISTRATION_COORDINATOR => $this->isRegistrationCoordinator($user, $event),
            default => false,
        };
    }

    private function isAuthor(BackendUser $user, CalendarEventsModel $event): bool
    {
        return (int) $user->id === (int) $event->author;
    }

    /**
     * The main instructor is the first instructor of the event (tl_calendar_events.mainInstructor).
     */
    private function isMainInstructor(BackendUser $user, CalendarEventsModel $event): bool
    {
        return (int) $event->mainInstructor > 0 && (int) $user->id === (int) $event->mainInstructor;
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
}
