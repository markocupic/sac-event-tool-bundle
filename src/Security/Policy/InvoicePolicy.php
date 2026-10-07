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

use Contao\CalendarEventsModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

final readonly class InvoicePolicy
{
    /**
     * @param list<InvoicePolicyRule> $rules
     */
    public function __construct(
        private array $rules,
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    /**
     * @param list<int> $eventInstructorIds
     */
    public function allows(TokenInterface $token, int $userId, CalendarEventsInstructorInvoiceModel|CalendarEventsModel $model, array $eventInstructorIds, string $requiredFlag): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->hasFlag($requiredFlag)) {
                continue;
            }

            if ($model instanceof CalendarEventsInstructorInvoiceModel && $rule->matchesInvoiceOwner($userId, $model)) {
                return true;
            }

            if ($rule->matchesInstructor($userId, $eventInstructorIds)) {
                return true;
            }

            if ($rule->matchesGroup($this->accessDecisionManager, $token)) {
                return true;
            }
        }

        return false;
    }
}
