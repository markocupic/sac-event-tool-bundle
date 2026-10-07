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
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel\EventReleaseLevelTimeRules;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyPackageModel;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides whether the user may shift an event from its current release level to
 * the target level (subject: EventReleaseLevelTransition).
 *
 * - The target level must belong to the release level system of the event type
 *   (tl_event_type.levelAccessPermissionPackage). This also applies to admins.
 * - Keeping the current level is always allowed.
 * - Admins may shift the event to every level of the release level system.
 * - Non-admins must respect the time rules of the calendar (see
 *   EventReleaseLevelTimeRules) and need the permission of every level they pass
 *   (see CalendarEventsVoter::canChangeReleaseLevel()).
 */
class EventReleaseLevelTransitionVoter extends Voter
{
    public const string CAN_SWITCH_TO_EVENT_RELEASE_LEVEL = 'sacevt_can_switch_to_event_release_level';

    private Adapter $eventReleaseLevelPolicyModel;

    private Adapter $eventReleaseLevelPolicyPackageModel;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly CalendarEventsVoter $calendarEventsVoter,
        private readonly ContaoFramework $framework,
        private readonly EventReleaseLevelTimeRules $timeRules,
    ) {
        $this->eventReleaseLevelPolicyModel = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
        $this->eventReleaseLevelPolicyPackageModel = $this->framework->getAdapter(EventReleaseLevelPolicyPackageModel::class);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::CAN_SWITCH_TO_EVENT_RELEASE_LEVEL === $attribute && $subject instanceof EventReleaseLevelTransition;
    }

    /**
     * @param EventReleaseLevelTransition $subject
     *
     * @throws \Exception
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof BackendUser) {
            return false;
        }

        $event = $subject->event;
        $targetLevel = $subject->targetLevel;
        // The target level must belong to the release level system of the event type (also for admins)
        $package = $this->eventReleaseLevelPolicyPackageModel->findReleaseLevelPolicyPackageModelByEventId($event->id);

        if (null === $package || (int) $package->id !== (int) $targetLevel->pid) {
            return false;
        }

        $currentLevel = $this->eventReleaseLevelPolicyModel->findById($event->eventReleaseLevel);

        if (null === $currentLevel) {
            return false;
        }

        if ((int) $currentLevel->id === (int) $targetLevel->id) {
            return true;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        if (null !== $this->timeRules->getViolation($event, $targetLevel)) {
            return false;
        }

        // The user needs the permission of every level on the way to the target level.
        $direction = $targetLevel->level > $currentLevel->level ? 'up' : 'down';
        $level = $currentLevel;

        while ((int) $level->id !== (int) $targetLevel->id) {
            if (!$this->calendarEventsVoter->canChangeReleaseLevel($event, $user, $level, $direction)) {
                return false;
            }

            $level = 'up' === $direction ? $this->eventReleaseLevelPolicyModel->findNextLevel($level->id) : $this->eventReleaseLevelPolicyModel->findPrevLevel($level->id);

            // The target level is not part of the release level system of the event
            if (null === $level) {
                return false;
            }
        }

        return true;
    }
}
