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

final readonly class InvoicePolicyRule
{
    public function __construct(
        private array $flags,
        private bool $appliesToInvoiceOwners,
        private bool $appliesToInstructors,
        private int|null $groupId,
        private AccessDecisionManagerInterface $accessDecisionManager,
        private TokenInterface $token,
    ) {
    }

    public function hasFlag(string $requiredFlag): bool
    {
        return \in_array($requiredFlag, $this->flags, true);
    }

    public function matchesInvoiceOwner(int $userId, CalendarEventsInstructorInvoiceModel $invoice): bool
    {
        return $this->appliesToInvoiceOwners && $userId === $invoice->userPid;
    }

    public function matchesInstructor(int $userId, array $eventInstructorIds): bool
    {
        return $this->appliesToInstructors && \in_array($userId, $eventInstructorIds, true);
    }

    public function matchesGroup(): bool
    {
        if (empty($this->groupId)) {
            return false;
        }

        return $this->accessDecisionManager->decide($this->token, ['contao_user.groups'], $this->groupId);
    }
}
