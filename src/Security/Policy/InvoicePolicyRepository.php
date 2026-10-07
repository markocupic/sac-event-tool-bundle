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

use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

final class InvoicePolicyRepository
{
    public const string IDENTIFIER = 'calendar_events_instructor_invoice';

    private InvoicePolicy|null $policy = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    /**
     * The rules are loaded once per request, as the voter is called for every
     * invoice and every button in the list view.
     */
    public function loadPolicy(): InvoicePolicy
    {
        if (null !== $this->policy) {
            return $this->policy;
        }

        $ruleSets = $this->connection->fetchFirstColumn(
            'SELECT calendar_events_instructor_invoice_rules FROM tl_permission_policy WHERE identifier = ?',
            [self::IDENTIFIER],
            [Types::STRING],
        );

        $rules = [];

        foreach ($ruleSets as $ruleSet) {
            foreach (StringUtil::deserialize($ruleSet, true) as $rule) {
                if (!\is_array($rule)) {
                    continue;
                }

                $rules[] = new InvoicePolicyRule(
                    flags: array_values(array_map('strval', StringUtil::deserialize($rule['flags'] ?? null, true))),
                    appliesToInvoiceOwners: !empty($rule['invoice_owners']),
                    appliesToInstructors: !empty($rule['event_instructors']),
                    groupId: !empty($rule['group']) ? (int) $rule['group'] : null,
                );
            }
        }

        return $this->policy = new InvoicePolicy($rules, $this->accessDecisionManager);
    }
}
