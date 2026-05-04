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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

final readonly class InvoicePolicyRepository
{
    public function __construct(
        private Connection $connection,
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    public function loadPolicy(TokenInterface $token): InvoicePolicy
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_permission_policy WHERE identifier = ?',
            ['calendar_events_instructor_invoice'],
        );

        $rules = [];

        foreach ($rows as $row) {
            $ruleSets = StringUtil::deserialize($row['calendar_events_instructor_invoice_rules'], true);

            foreach ($ruleSets as $rule) {
                $flags = StringUtil::deserialize($rule['flags'], true);

                $rules[] = new InvoicePolicyRule(
                    flags: $flags,
                    appliesToInvoiceOwners: !empty($rule['invoice_owners']),
                    appliesToInstructors: !empty($rule['event_instructors']),
                    groupId: !empty($rule['group']) ? (int) $rule['group'] : null,
                    accessDecisionManager: $this->accessDecisionManager,
                    token: $token,
                );
            }
        }

        return new InvoicePolicy($rules);
    }
}
