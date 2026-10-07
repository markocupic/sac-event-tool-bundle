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

namespace Markocupic\SacEventToolBundle\Security\Policy;

use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

/**
 * One rule of tl_permission_policy.calendar_events_instructor_invoice_rules.
 *
 * The rule grants its flags to the invoice owners, to the instructors of the event
 * or to the members of a user group. If several of them are selected, the rule
 * applies to each of them (or).
 */
final readonly class InvoicePolicyRule
{
    /**
     * @param list<string> $flags
     */
    public function __construct(
        private array $flags,
        private bool $appliesToInvoiceOwners,
        private bool $appliesToInstructors,
        private int|null $groupId,
    ) {
    }

    public function hasFlag(string $requiredFlag): bool
    {
        return \in_array($requiredFlag, $this->flags, true);
    }

    public function matchesInvoiceOwner(int $userId, CalendarEventsInstructorInvoiceModel $invoice): bool
    {
        return $this->appliesToInvoiceOwners && $userId === (int) $invoice->userPid;
    }

    /**
     * @param list<int> $eventInstructorIds
     */
    public function matchesInstructor(int $userId, array $eventInstructorIds): bool
    {
        return $this->appliesToInstructors && \in_array($userId, $eventInstructorIds, true);
    }

    public function matchesGroup(AccessDecisionManagerInterface $accessDecisionManager, TokenInterface $token): bool
    {
        if (null === $this->groupId) {
            return false;
        }

        return $accessDecisionManager->decide($token, ['contao_user.groups'], $this->groupId);
    }
}
