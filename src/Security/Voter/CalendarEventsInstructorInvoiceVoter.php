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
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Markocupic\SacEventToolBundle\Security\Policy\InvoicePolicyRepository;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CalendarEventsInstructorInvoiceVoter extends Voter
{
    public const string HAS_ACCESS = 'sacevt_has_access_to_invoice_list';

    public const string CAN_CREATE = 'sacevt_can_create_invoice';

    public const string CAN_UPDATE = 'sacevt_can_update_invoice';

    public const string CAN_DELETE = 'sacevt_can_delete_invoice';

    public const string CAN_DOWNLOAD = 'sacevt_can_download_tour_report_and_invoice';

    public const string CAN_SEND = 'sacevt_can_send_tour_report_and_invoice';

    private const array EVENT_PERMISSIONS_ALL = [
        self::HAS_ACCESS,
        self::CAN_CREATE,
        self::CAN_UPDATE,
        self::CAN_DELETE,
        self::CAN_DOWNLOAD,
        self::CAN_SEND,
    ];

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly InvoicePolicyRepository $policyRepository,
    ) {
    }

    protected function supports($attribute, $subject): bool
    {
        return \in_array(
            $attribute,
            self::EVENT_PERMISSIONS_ALL,
            true,
        );
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof BackendUser) {
            // the user must be logged in; if not, deny access
            return false;
        }

        return match ($attribute) {
            self::HAS_ACCESS => $this->hasAccess($token, $subject),
            self::CAN_CREATE => $this->canCreate($token, $subject),
            self::CAN_UPDATE => $this->canUpdate($token, $subject),
            self::CAN_DELETE => $this->canDelete($token, $subject),
            self::CAN_DOWNLOAD => $this->canDownload($token, $subject),
            self::CAN_SEND => $this->canSend($token, $subject),
            default => throw new \LogicException(\sprintf('You vote on a unsupported attribute "%s"!', $attribute)),
        };
    }

    private function hasAccess(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        return $this->isGranted($token, $calEvent, 'has_access');
    }

    private function canCreate(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        return $this->isGranted($token, $calEvent, 'can_create');
    }

    private function canUpdate(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        return $this->isGranted($token, $calEvent, 'can_update');
    }

    private function canDelete(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        return $this->isGranted($token, $calEvent, 'can_delete');
    }

    private function canDownload(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        return $this->isGranted($token, $calEvent, 'can_download');
    }

    private function canSend(TokenInterface $token, CalendarEventsModel $calEvent): bool
    {
        // Report form must be filled out
        if (!$calEvent->filledInEventReportForm) {
            return false;
        }

        // Organizer must have enabled rapport notification
        if (!$this->isRapportNotificationEnabled($calEvent)) {
            return false;
        }

        return $this->isGranted($token, $calEvent, 'can_send');
    }

    private function isGranted(TokenInterface $token, CalendarEventsModel $event, string $requiredFlag): bool
    {
        // Admins always have full access
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $policy = $this->policyRepository->loadPolicy($token);

        $eventInstructorIds = array_map(
            'intval',
            $this->calendarEventsUtil->getInstructorsAsArray($event),
        );

        $user = $token->getUser();

        return $policy->allows($user->id, $eventInstructorIds, $requiredFlag);
    }

    /**
     * Check if rapport notification is enabled on the event.
     */
    private function isRapportNotificationEnabled(CalendarEventsModel $event): bool
    {
        $organizerIds = StringUtil::deserialize($event->organizers, true);

        if (empty($organizerIds)) {
            return false;
        }

        $organizers = EventOrganizerModel::findByIds($organizerIds);

        if (null === $organizers) {
            return false;
        }

        while ($organizers->next()) {
            if ($organizers->enableRapportNotification) {
                return true;
            }
        }

        return false;
    }
}
