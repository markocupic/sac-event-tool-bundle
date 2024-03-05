<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Security\Voter;

use Contao\CalendarBundle\Security\ContaoCalendarPermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\CoreBundle\Security\DataContainer\DeleteAction;
use Contao\CoreBundle\Security\DataContainer\ReadAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
use Contao\CoreBundle\Security\Voter\DataContainer\AbstractDataContainerVoter;
use Markocupic\SacEventToolBundle\Security\SacEventToolPermissions;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

/**
 * @internal
 */
class CalendarContainerAccessVoter extends AbstractDataContainerVoter
{
    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function getTable(): string
    {
        return 'tl_calendar_container';
    }

    protected function hasAccess(TokenInterface $token, CreateAction|DeleteAction|ReadAction|UpdateAction $action): bool
    {
        if (!$this->accessDecisionManager->decide($token, [ContaoCalendarPermissions::USER_CAN_ACCESS_MODULE])) {
            return false;
        }

        return match (true) {
            $action instanceof CreateAction => $this->accessDecisionManager->decide($token, [SacEventToolPermissions::USER_CAN_CREATE_CALENDAR_CONTAINERS]),
            $action instanceof ReadAction => true,
            $action instanceof UpdateAction => $this->accessDecisionManager->decide($token, [SacEventToolPermissions::USER_CAN_EDIT_CALENDAR_CONTAINERS], $action->getCurrentId()),
            $action instanceof DeleteAction => $this->accessDecisionManager->decide($token, [SacEventToolPermissions::USER_CAN_EDIT_CALENDAR_CONTAINERS], $action->getCurrentId())
                && $this->accessDecisionManager->decide($token, [SacEventToolPermissions::USER_CAN_DELETE_CALENDAR_CONTAINERS]),
        };
    }
}
