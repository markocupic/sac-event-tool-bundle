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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides whether the user may shift an event from its current release level to
 * the target level (subject: EventReleaseLevelTransition).
 *
 * - Keeping the current level is always allowed.
 * - Admins may shift the event to every level.
 * - Non-admins must respect the time rules of the calendar (see
 *   EventReleaseLevelTimeRules) and need the permission of every level they pass
 *   (see CalendarEventsVoter::canChangeReleaseLevel()).
 *
 * Whether the target level belongs to the release level system of the event is
 * not checked here, see EventReleaseLevelUtil::validateEventReleaseLevelTransition().
 */
class EventReleaseLevelTransitionVoter extends Voter
{
    public const string CAN_SWITCH_TO_EVENT_RELEASE_LEVEL = 'sacevt_can_switch_to_event_release_level';

    private Adapter $eventReleaseLevelPolicyModel;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly CalendarEventsVoter $calendarEventsVoter,
        private readonly ContaoFramework $framework,
        private readonly EventReleaseLevelTimeRules $timeRules,
    ) {
        $this->eventReleaseLevelPolicyModel = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
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
