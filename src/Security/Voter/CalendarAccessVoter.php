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

use Contao\CalendarBundle\Security\ContaoCalendarPermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\CoreBundle\Security\DataContainer\DeleteAction;
use Contao\CoreBundle\Security\DataContainer\ReadAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
use Contao\CoreBundle\Security\Voter\DataContainer\AbstractDataContainerVoter;
use Contao\CoreBundle\Security\Voter\DataContainer\ParentAccessTrait;
use Markocupic\SacEventToolBundle\Security\SacEventToolPermissions;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

/**
 * Replaces the Contao core voter "contao_calendar.security.calendar_access_voter"
 * (see config/services.yaml), because tl_calendar has the parent table tl_calendar_container.
 *
 * @internal
 */
class CalendarAccessVoter extends AbstractDataContainerVoter
{
    use ParentAccessTrait;

    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function getTable(): string
    {
        return 'tl_calendar';
    }

    protected function hasAccess(TokenInterface $token, CreateAction|DeleteAction|ReadAction|UpdateAction $action): bool
    {
        if (
            !$this->accessDecisionManager->decide($token, [ContaoCalendarPermissions::USER_CAN_ACCESS_MODULE])
            || !$this->hasAccessToParent($token, SacEventToolPermissions::USER_CAN_EDIT_CALENDAR_CONTAINERS, $action)
        ) {
            return false;
        }

        return match (true) {
            $action instanceof CreateAction => $this->accessDecisionManager->decide($token, [ContaoCalendarPermissions::USER_CAN_CREATE_CALENDARS]),
            // Without an ID (e.g. the "children" operation of tl_calendar_container), access to any
            // calendar is sufficient. Do not cast null to 0 like the Contao 5.7 core voter does.
            $action instanceof ReadAction => null === $action->getCurrentId()
                ? $this->accessDecisionManager->decide($token, [ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR])
                : $this->accessDecisionManager->decide($token, [ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR], (int) $action->getCurrentId()),
            $action instanceof UpdateAction => $this->accessDecisionManager->decide($token, [ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR], (int) $action->getCurrentId()),
            $action instanceof DeleteAction => $this->accessDecisionManager->decide($token, [ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR], (int) $action->getCurrentId())
                && $this->accessDecisionManager->decide($token, [ContaoCalendarPermissions::USER_CAN_DELETE_CALENDARS]),
        };
    }
}
