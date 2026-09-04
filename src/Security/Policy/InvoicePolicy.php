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

final readonly class InvoicePolicy
{
    /**
     * @param array<InvoicePolicyRule> $rules
     */
    public function __construct(private array $rules)
    {
    }

    public function allows(int $userId, CalendarEventsInstructorInvoiceModel|CalendarEventsModel $model, array $eventInstructorIds, string $requiredFlag): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->hasFlag($requiredFlag)) {
                continue;
            }

            if ($model instanceof CalendarEventsInstructorInvoiceModel) {
                if ($rule->matchesInvoiceOwner($userId, $model)) {
                    return true;
                }
            }

            if ($rule->matchesInstructor($userId, $eventInstructorIds)) {
                return true;
            }

            if ($rule->matchesGroup()) {
                return true;
            }
        }

        return false;
    }
}
