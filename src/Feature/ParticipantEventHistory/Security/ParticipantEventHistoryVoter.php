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

namespace Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\Security;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\ParticipantEventHistoryAccessPeriod;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * May the user see the event history of the participant of a registration?
 * Subject: the ID of the registration (tl_calendar_events_member.id).
 *
 * - The registration must have a SAC member ID (no history for guests).
 * - Admins are always granted access, also after the access period.
 * - Other users need the permission CalendarEventsVoter::CAN_VIEW_PARTICIPANT_EVENT_HISTORY
 *   on the event of the registration (permission rules of the release level) and the access
 *   period must not have expired (see ParticipantEventHistoryAccessPeriod).
 */
class ParticipantEventHistoryVoter extends Voter
{
    public const string CAN_VIEW_PARTICIPANT_EVENT_HISTORY_OF_REGISTRATION = 'sacevt_can_view_participant_event_history_of_registration';

    private Adapter $calendarEventsMemberModel;

    private Adapter $calendarEventsModel;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly ContaoFramework $framework,
    ) {
        $this->calendarEventsMemberModel = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::CAN_VIEW_PARTICIPANT_EVENT_HISTORY_OF_REGISTRATION === $attribute && is_numeric($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, Vote|null $vote = null): bool
    {
        if (!$token->getUser() instanceof BackendUser) {
            return false;
        }

        $registration = $this->calendarEventsMemberModel->findById((int) $subject);

        if (null === $registration || (int) $registration->sacMemberId < 1) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $event = $this->calendarEventsModel->findById((int) $registration->eventId);

        if (null === $event) {
            return false;
        }

        if (!$this->accessDecisionManager->decide($token, [CalendarEventsVoter::CAN_VIEW_PARTICIPANT_EVENT_HISTORY], (int) $event->id)) {
            return false;
        }

        return ParticipantEventHistoryAccessPeriod::isOpen($event, new \DateTimeImmutable());
    }
}
