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
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Markocupic\SacEventToolBundle\Security\Policy\InvoicePolicyRepository;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Permissions for the tour reports and instructor invoices (tl_calendar_events_instructor_invoice).
 *
 * Subject: the event (HAS_ACCESS, CAN_CREATE) or the invoice (all other attributes).
 * Admins have full access, all other users need a rule in the permission policy
 * (tl_permission_policy, see InvoicePolicyRepository).
 */
class CalendarEventsInstructorInvoiceVoter extends Voter
{
    public const string HAS_ACCESS = 'sacevt_has_access_to_invoice_list';

    public const string CAN_CREATE = 'sacevt_can_create_invoice';

    public const string CAN_UPDATE = 'sacevt_can_update_invoice';

    public const string CAN_DELETE = 'sacevt_can_delete_invoice';

    public const string CAN_DOWNLOAD = 'sacevt_can_download_tour_report_and_invoice';

    public const string CAN_SEND = 'sacevt_can_send_tour_report_and_invoice';

    /**
     * The flags of the permission policy (tl_permission_policy.calendar_events_instructor_invoice_rules).
     */
    private const array POLICY_FLAGS = [
        self::HAS_ACCESS => 'has_access',
        self::CAN_CREATE => 'can_create',
        self::CAN_UPDATE => 'can_update',
        self::CAN_DELETE => 'can_delete',
        self::CAN_DOWNLOAD => 'can_download',
        self::CAN_SEND => 'can_send',
    ];

    private Adapter $calendarEventsModel;

    private Adapter $eventOrganizerModel;

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        private readonly InvoicePolicyRepository $policyRepository,
    ) {
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->eventOrganizerModel = $this->framework->getAdapter(EventOrganizerModel::class);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::POLICY_FLAGS[$attribute]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // The user must be logged in to the back end
        if (!$user instanceof BackendUser) {
            return false;
        }

        $event = match ($attribute) {
            self::HAS_ACCESS, self::CAN_CREATE => $subject instanceof CalendarEventsModel ? $subject : null,
            default => $subject instanceof CalendarEventsInstructorInvoiceModel ? $this->calendarEventsModel->findById($subject->pid) : null,
        };

        // Wrong subject (e.g. the record does not exist) or the event does not exist (anymore)
        if (null === $event) {
            return false;
        }

        // Sending the tour report requires a filled in report form and an organizer with
        // rapport notifications enabled. This also applies to admins.
        if (self::CAN_SEND === $attribute && (!$event->filledInEventReportForm || !$this->isRapportNotificationEnabled($event))) {
            return false;
        }

        // Admins always have full access
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $instructorIds = array_map('intval', $this->calendarEventsUtil->getInstructorsAsArray($event));

        return $this->policyRepository->loadPolicy()->allows($token, (int) $user->id, $subject, $instructorIds, self::POLICY_FLAGS[$attribute]);
    }

    /**
     * Check if rapport notification is enabled for one of the organizers of the event.
     */
    private function isRapportNotificationEnabled(CalendarEventsModel $event): bool
    {
        $organizerIds = StringUtil::deserialize($event->organizers, true);

        if (empty($organizerIds)) {
            return false;
        }

        $organizers = $this->eventOrganizerModel->findByIds($organizerIds);

        if (null === $organizers) {
            return false;
        }

        foreach ($organizers as $organizer) {
            if ($organizer->enableRapportNotification) {
                return true;
            }
        }

        return false;
    }
}
