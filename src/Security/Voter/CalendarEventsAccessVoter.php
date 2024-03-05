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
use Contao\CoreBundle\Security\Voter\DataContainer\ParentAccessTrait;
use Markocupic\SacEventToolBundle\Security\SacEventToolPermissions;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @internal
 */
class CalendarEventsAccessVoter extends AbstractDataContainerVoter
{
	use ParentAccessTrait;

	protected function getTable(): string
	{
		return 'tl_calendar_events';
	}


	protected function hasAccess(TokenInterface $token, CreateAction|DeleteAction|ReadAction|UpdateAction $action): bool
	{
		return $this->accessDecisionManager->decide($token, [ContaoCalendarPermissions::USER_CAN_ACCESS_MODULE])
			&& $this->hasAccessToParent($token, ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR, $action);
	}
}

