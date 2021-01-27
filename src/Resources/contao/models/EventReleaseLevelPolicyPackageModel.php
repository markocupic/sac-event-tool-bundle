<?php

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Contao;

/**
 * Class EventReleaseLevelPolicyPackageModel
 */
class EventReleaseLevelPolicyPackageModel extends Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_event_release_level_policy_package';

	/**
	 * @param $eventId
	 */
	public function findReleaseLevelPolicyPackageModelByEventId($eventId)
	{
		$objEvent = CalendarEventsModel::findByPk($eventId);

		if ($objEvent === null)
		{
			return null;
		}

		$objEventType = EventTypeModel::findByAlias($objEvent->eventType);

		if ($objEventType === null)
		{
			return null;
		}

		if ($objEventType->levelAccessPermissionPackage > 0)
		{
			return self::findByPk($objEventType->levelAccessPermissionPackage);
		}
	}
}
