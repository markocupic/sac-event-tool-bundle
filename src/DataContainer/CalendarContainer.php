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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\Database;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

class CalendarContainer
{
    /**
     * Import the back end user object.
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
        private readonly Security $security,
    ) {
    }

    /**
     * Check permissions to edit table tl_calendar_container.
     */
    #[AsCallback(table: 'tl_calendar_container', target: 'config.onload')]
    public function adjustPermissions(): void
    {
		// The oncreate_callback passes $insertId as second argument
		if (func_num_args() == 4)
		{
			$insertId = func_get_arg(1);
		}

		if ($this->security->isGranted('ROLE_ADMIN'))
		{
			return;
		}

		$user = $this->security->getUser();

		// Set root IDs
		if (empty($user->calendar_containers) || !is_array($user->calendar_containers))
		{
			$root = array(0);
		}
		else
		{
			$root = $user->calendar_containers;
		}

		// The calendar is enabled already
		if (isset($insertId) && in_array($insertId, $root))
		{
			return;
		}

		/** @var AttributeBagInterface $objSessionBag */
		$objSessionBag = $this->requestStack->getSession()->getBag('contao_backend');
		$arrNew = $objSessionBag->get('new_records');

		if (isset($insertId) && !empty($arrNew['tl_calendar_container']) && is_array($arrNew['tl_calendar_container']) && in_array($insertId, $arrNew['tl_calendar_container']))
		{
			$db = Database::getInstance();

			// Add the permissions on group level
			if ($user->inherit != 'custom')
			{
				$objGroup = $db->execute("SELECT id, calendar_containers, calendar_containerp FROM tl_user_group WHERE id IN(" . implode(',', array_map('\intval', $user->groups)) . ")");

				while ($objGroup->next())
				{
					$arrCalendarContainerp = StringUtil::deserialize($objGroup->calendar_containerp);

					if (is_array($arrCalendarContainerp) && in_array('create', $arrCalendarContainerp))
					{
						$arrCalendarContainers = StringUtil::deserialize($objGroup->calendar_containers, true);
						$arrCalendarContainers[] = $insertId;

						$db->prepare("UPDATE tl_user_group SET calendar_containers=? WHERE id=?")->execute(serialize($arrCalendarContainers), $objGroup->id);
					}
				}
			}

			// Add the permissions on user level
			if ($user->inherit != 'group')
			{
				$objUser = $db
					->prepare("SELECT calendar_containers, calendar_containerp FROM tl_user WHERE id=?")
					->limit(1)
					->execute($user->id);

				$arrCalendarContainerp = StringUtil::deserialize($objUser->calendar_containerp);

				if (is_array($arrCalendarContainerp) && in_array('create', $arrCalendarContainerp))
				{
					$arrCalendarContainers = StringUtil::deserialize($objUser->calendar_containers, true);
					$arrCalendarContainers[] = $insertId;

					$db->prepare("UPDATE tl_user SET calendar_containers=? WHERE id=?")->execute(serialize($arrCalendarContainers), $user->id);
				}
			}

			// Add the new element to the user object
			$root[] = $insertId;
			$user->calendar_containers = $root;
		}
    }
}
