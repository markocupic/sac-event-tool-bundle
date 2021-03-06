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

use Markocupic\SacEventToolBundle\CalendarEventsHelper;

/**
 * Class EventReleaseLevelPolicyModel
 */
class EventReleaseLevelPolicyModel extends Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_event_release_level_policy';

	/**
	 * @param $eventReleaseRecordId
	 * @return static|null
	 */
	public static function findNextLevel($eventReleaseRecordId)
	{
		$eventReleaseLevelModel = static::findByPk($eventReleaseRecordId);

		if ($eventReleaseLevelModel !== null)
		{
			$eventReleasePackageModel = $eventReleaseLevelModel->getRelated('pid');

			if ($eventReleasePackageModel !== null)
			{
				$arrColumns = array(static::$strTable . '.pid=?');
				$arrColumns[] = static::$strTable . '.level>?';
				$arrInt = array($eventReleasePackageModel->id, $eventReleaseLevelModel->level);
				$arrOpt = array('order' => 'tl_event_release_level_policy.level ASC');
				$objModel = static::findOneBy($arrColumns, $arrInt, $arrOpt);

				if ($objModel !== null)
				{
					return $objModel;
				}
			}
		}

		return null;
	}

	/**
	 * @param $eventId
	 * @return static|null
	 */
	public static function findFirstLevelByEventId($eventId)
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
			$objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findByPk($objEventType->levelAccessPermissionPackage);
		}

		if ($objEventReleaseLevelPolicyPackageModel === null)
		{
			Message::addError('Datarecord tl_event_release_level_policy_package with ID ' . $objEventType->levelAccessPermissionPackage . ' not found. Error in ' . __METHOD__ . ' Line: ' . __LINE__);

			return null;
		}

		$options = array(
			'order' => 'level ASC',
		);
		$objReleaseLevelModel = self::findOneBy(array('tl_event_release_level_policy.pid=?'), array($objEventReleaseLevelPolicyPackageModel->id), $options);

		if ($objReleaseLevelModel === null)
		{
			Message::addError('No ReleaseLevelModel found for tl_calendar_events with ID ' . $objEvent->id . '. Error in ' . __METHOD__ . ' Line: ' . __LINE__);

			return null;
		}

		return $objReleaseLevelModel;
	}

	/**
	 * @param $eventId
	 * @return static|null
	 */
	public static function findLastLevelByEventId($eventId)
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
			$objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findByPk($objEventType->levelAccessPermissionPackage);
		}

		if ($objEventReleaseLevelPolicyPackageModel === null)
		{
			Message::addError('Datarecord tl_event_release_level_policy_package with ID ' . $objEventType->levelAccessPermissionPackage . ' not found. Error in ' . __METHOD__ . ' Line: ' . __LINE__);

			return null;
		}

		$options = array(
			'order' => 'level DESC',
		);
		$objReleaseLevelModel = self::findOneBy(array('tl_event_release_level_policy.pid=?'), array($objEventReleaseLevelPolicyPackageModel->id), $options);

		if ($objReleaseLevelModel === null)
		{
			Message::addError('No ReleaseLevelModel found for tl_calendar_events with ID ' . $objEvent->id . '. Error in ' . __METHOD__ . ' Line: ' . __LINE__);

			return null;
		}

		return $objReleaseLevelModel;
	}

	/**
	 * Deleting events is allowed for:
	 * - admins on each level
	 * - for super users (defined in each level in tl_event_release_level_policy.groupReleaseLevelRights)
	 * - for authors and (instructors) only on the first level
	 * - for all users, if if there is no release package defined in tl_calendar
	 * @param $userId
	 * @param $eventId
	 * @return bool
	 */
	public static function canDeleteEvent($userId, $eventId)
	{
		$objBackendUser = UserModel::findByPk($userId);

		if ($objBackendUser === null)
		{
			return false;
		}

		$objEvent = CalendarEventsModel::findByPk($eventId);

		if ($objEvent === null)
		{
			return false;
		}

		if ($objEvent->eventReleaseLevel > 0)
		{
			$objReleaseLevelModel = self::findByPk($objEvent->eventReleaseLevel);

			if ($objReleaseLevelModel === null)
			{
				Message::addError('ReleaseLevelModel not found for tl_calendar_events with ID ' . $objEvent->id . '. Error in ' . __METHOD__ . ' Line: ' . __LINE__);

				return false;
			}
		}
		else
		{
			return true;
		}

		$arrGroupsUserBelongsTo = StringUtil::deserialize($objBackendUser->groups, true);
		$arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvent, false);

		$allow = false;

		// Check if user has permission
		if ($objBackendUser->admin)
		{
			$allow = true;
		}
		elseif (static::findPrevLevel($objEvent->eventReleaseLevel) === null && (int) $objBackendUser->id === (int) $objEvent->author && $objReleaseLevelModel->allowWriteAccessToAuthor)
		{
			// The event is on the first level and the user is author and is allowed to write on this level
			$allow = true;
		}
		elseif (static::findPrevLevel($objEvent->eventReleaseLevel) === null && $objReleaseLevelModel->allowWriteAccessToInstructors && \in_array($objBackendUser->id, $arrInstructors, false))
		{
			// The event is on the first level and the user is set as a instructor in the current event
			$allow = true;
		}

		// Check if user is in a group that is permitted
		if ($allow === false)
		{
			$arrGroups = StringUtil::deserialize($objReleaseLevelModel->groupReleaseLevelRights, true);

			foreach ($arrGroups as $k => $v)
			{
				if (\in_array($v['group'], $arrGroupsUserBelongsTo, false))
				{
					if ($v['writeAccess'])
					{
						$allow = true;
						continue;
					}
				}
			}
		}

		return $allow;
	}

	/**
	 * @param $levelId
	 * @return static|null
	 */
	public static function findPrevLevel($levelId)
	{
		$eventReleaseLevelModel = static::findByPk($levelId);

		if ($eventReleaseLevelModel !== null)
		{
			$eventReleasePackageModel = $eventReleaseLevelModel->getRelated('pid');

			if ($eventReleasePackageModel !== null)
			{
				$arrColumns = array(static::$strTable . '.pid=?');
				$arrColumns[] = static::$strTable . '.level<?';
				$arrInt = array($eventReleasePackageModel->id, $eventReleaseLevelModel->level);
				$arrOpt = array('order' => 'tl_event_release_level_policy.level DESC');
				$objModel = static::findOneBy($arrColumns, $arrInt, $arrOpt);

				if ($objModel !== null)
				{
					return $objModel;
				}
			}
		}

		return null;
	}

	/**
	 * Writing/editing an event is allowed for:
	 * - admins on each level
	 * - for super users (defined in each level in tl_event_release_level_policy.groupReleaseLevelRights)
	 * - for authors if he is allowed on the current level
	 * - for instructors if they are allowed  on the current level
	 *
	 * @param $userId
	 * @param $eventId
	 * @return bool
	 */
	public static function hasWritePermission($userId, $eventId)
	{
		$objBackendUser = UserModel::findByPk($userId);

		if ($objBackendUser === null)
		{
			return false;
		}

		$objEvent = CalendarEventsModel::findByPk($eventId);

		if ($objEvent === null)
		{
			return false;
		}

		if ($objEvent->eventReleaseLevel > 0)
		{
			$objReleaseLevelModel = self::findByPk($objEvent->eventReleaseLevel);

			if ($objReleaseLevelModel === null)
			{
				Message::addError('ReleaseLevelModel not found for tl_calendar_events with ID ' . $objEvent->id . '. Error in ' . __METHOD__ . ' Line: ' . __LINE__);

				return false;
			}
		}
		else
		{
			return true;
		}

		$arrGroupsUserBelongsTo = StringUtil::deserialize($objBackendUser->groups, true);
		$arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvent, false);

		$allow = false;

		// Check if user has permission
		if ($objBackendUser->admin)
		{
			$allow = true;
		}
		elseif ((int) $objBackendUser->id === (int) $objEvent->author && $objReleaseLevelModel->allowWriteAccessToAuthor)
		{
			// User is author and is allowed to write on this level
			$allow = true;
		}
		elseif ($objReleaseLevelModel->allowWriteAccessToInstructors && \in_array($objBackendUser->id, $arrInstructors, false))
		{
			// User is set as a instructor in the current event
			$allow = true;
		}

		// Check if user is in a group that is permitted
		if ($allow === false)
		{
			$arrGroups = StringUtil::deserialize($objReleaseLevelModel->groupReleaseLevelRights, true);

			foreach ($arrGroups as $k => $v)
			{
				if (\in_array($v['group'], $arrGroupsUserBelongsTo, false))
				{
					if ($v['writeAccess'])
					{
						$allow = true;
						continue;
					}
				}
			}
		}

		return $allow;
	}

	/**
	 * Switching to the next/prev level is allowed for:
	 * - admins on each level
	 * - for super users (defined in each level in tl_event_release_level_policy.groupReleaseLevelRights)
	 * - for authors if he is allowed
	 * - for instructors if they are allowed
	 *
	 * @param $userId
	 * @param $eventId
	 * @param $direction
	 * @return bool
	 * @throws \Exception
	 */
	public static function allowSwitchingEventReleaseLevel($userId, $eventId, $direction)
	{
		$objBackendUser = UserModel::findByPk($userId);

		if ($objBackendUser === null)
		{
			return false;
		}

		$objEvent = CalendarEventsModel::findByPk($eventId);

		if ($objEvent === null)
		{
			return false;
		}

		if ($objEvent->eventReleaseLevel > 0)
		{
			$objReleaseLevelModel = self::findByPk($objEvent->eventReleaseLevel);

			if ($objReleaseLevelModel === null)
			{
				Message::addError('ReleaseLevelModel not found for tl_calendar_events with ID ' . $objEvent->id . '. Error in ' . __METHOD__ . ' Line: ' . __LINE__);

				return false;
			}
		}
		else
		{
			return true;
		}

		$arrGroupsUserBelongsTo = StringUtil::deserialize($objBackendUser->groups, true);
		$arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvent, false);

		$allow = false;

		// Check if user has permission
		if ($objBackendUser->admin)
		{
			$allow = true;
		}
		elseif ((int) $objBackendUser->id === (int) $objEvent->author && $objReleaseLevelModel->allowWriteAccessToAuthor)
		{
			// User is author and is allowed to switch up/down
			if ($direction === 'up' && $objReleaseLevelModel->allowSwitchingToNextLevel)
			{
				$allow = true;
			}

			if ($direction === 'down' && $objReleaseLevelModel->allowSwitchingToPrevLevel)
			{
				$allow = true;
			}
		}
		elseif (\in_array($objBackendUser->id, $arrInstructors, false))
		{
			// User is set as a instructor in the current event and is allowed to switch up/down
			if ($direction === 'up' && $objReleaseLevelModel->allowSwitchingToNextLevel)
			{
				$allow = true;
			}

			if ($direction === 'down' && $objReleaseLevelModel->allowSwitchingToPrevLevel)
			{
				$allow = true;
			}
		}

		// Check if user is in a group that is permitted
		if ($allow === false)
		{
			$arrGroups = StringUtil::deserialize($objReleaseLevelModel->groupReleaseLevelRights, true);
			$arrAllowedGroups = array();

			foreach ($arrGroups as $k => $v)
			{
				$arrAllowedGroups[$v['group']] = $v;

				if (\in_array($v['group'], $arrGroupsUserBelongsTo, false))
				{
					if ($direction === 'up')
					{
						if ($v['releaseLevelRights'] === 'up' || $v['releaseLevelRights'] === 'upAndDown')
						{
							$allow = true;
							continue;
						}
					}

					if ($direction === 'down')
					{
						if ($v['releaseLevelRights'] === 'down' || $v['releaseLevelRights'] === 'upAndDown')
						{
							$allow = true;
							continue;
						}
					}
				}
			}
		}

		return $allow;
	}

	/**
	 * @param $eventId
	 * @param $level
	 * @return bool|void
	 */
	public static function levelExists($eventId, $level = null)
	{
		$objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($eventId);

		if ($objEventReleaseLevelPolicyPackageModel !== null)
		{
			// Check if the wanted level exists
			$objNewReleaseLevelModel = static::findOneByPidAndLevel($objEventReleaseLevelPolicyPackageModel->id, $level);

			if ($objNewReleaseLevelModel !== null)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $pid
	 * @param $level
	 * @return static
	 */
	public static function findOneByPidAndLevel($pid, $level = null)
	{
		$t = static::$strTable;
		$arrColumns = array();
		$arrColumns[] = "$t.pid=? AND  $t.level=?";
		$arrVars = array($pid, $level);

		return static::findOneBy($arrColumns, $arrVars, array());
	}
}
