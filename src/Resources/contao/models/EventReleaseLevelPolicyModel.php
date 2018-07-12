<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */

namespace Contao;

use Markocupic\SacEventToolBundle\CalendarEventsHelper;

/**
 * Class EventReleaseLevelPolicyModel
 * @package Contao
 */
class EventReleaseLevelPolicyModel extends \Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_event_release_level_policy';

    /**
     * @param $eventReleaseRecordId
     * @return null|static
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
     * @return null|static
     */
    public static function findFirstLevelByEventId($eventId)
    {

        $objEvent = \CalendarEventsModel::findByPk($eventId);
        if ($objEvent === null)
        {
            return null;
        }

        $objCalendar = $objEvent->getRelated('pid');
        if ($objCalendar === null)
        {
            return null;
        }

        if (!$objCalendar->levelAccessPermissionPackage)
        {
            return null;
        }

        $objEventReleaseLevelPolicyPackageModel = $objCalendar->getRelated('levelAccessPermissionPackage');
        if ($objEventReleaseLevelPolicyPackageModel === null)
        {
            Message::addError('Datarecord tl_event_release_level_policy_package with ID ' . $objCalendar->levelAccessPermissionPackage . ' not found. Error in ' . __METHOD__ . ' Line: ' . __LINE__);
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
     * @return null|static
     */
    public static function findLastLevelByEventId($eventId)
    {

        $objEvent = \CalendarEventsModel::findByPk($eventId);
        if ($objEvent === null)
        {
            return null;
        }

        $objCalendar = $objEvent->getRelated('pid');
        if ($objCalendar === null)
        {
            return null;
        }

        if (!$objCalendar->levelAccessPermissionPackage)
        {
            return null;
        }

        $objEventReleaseLevelPolicyPackageModel = $objCalendar->getRelated('levelAccessPermissionPackage');
        if ($objEventReleaseLevelPolicyPackageModel === null)
        {
            Message::addError('Datarecord tl_event_release_level_policy_package with ID ' . $objCalendar->levelAccessPermissionPackage . ' not found. Error in ' . __METHOD__ . ' Line: ' . __LINE__);
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
        $objBackendUser = \UserModel::findByPk($userId);
        if ($objBackendUser === null)
        {
            return false;
        }

        $objEvent = \CalendarEventsModel::findByPk($eventId);
        if ($objEvent === null)
        {
            return false;
        }

        $objCalendar = $objEvent->getRelated('pid');
        if ($objCalendar === null)
        {
            return false;
        }

        if (!$objCalendar->levelAccessPermissionPackage)
        {
            // Allow if there is no release package defined in tl_calendar
            return true;
        }
        $objEventReleaseLevelPolicyPackageModel = $objCalendar->getRelated('levelAccessPermissionPackage');
        if ($objEventReleaseLevelPolicyPackageModel === null)
        {

            Message::addError('Datarecord tl_event_release_level_policy_package with ID ' . $objCalendar->levelAccessPermissionPackage . ' not found. Error in ' . __METHOD__ . ' Line: ' . __LINE__);
            return false;
        }

        $objReleaseLevelModel = static::findOneById($objEvent->eventReleaseLevel);
        if ($objReleaseLevelModel === null)
        {
            Message::addError('ReleaseLevelModel not found for tl_calendar_events with ID ' . $objEvent->id . '. Error in ' . __METHOD__ . ' Line: ' . __LINE__);
            return false;
        }

        $arrGroupsUserBelongsTo = \StringUtil::deserialize($objBackendUser->groups, true);
        $arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvent->id);

        $allow = false;

        // Check if user has permission
        if ($objBackendUser->admin)
        {
            $allow = true;
        }
        elseif (static::findPrevLevel($objEvent->eventReleaseLevel) === null && $objBackendUser->id == $objEvent->author && $objReleaseLevelModel->allowWriteAccessToAuthor)
        {
            // The event is on the first level and the user is author and is allowed to write on this level
            $allow = true;
        }
        elseif (static::findPrevLevel($objEvent->eventReleaseLevel) === null && $objReleaseLevelModel->allowWriteAccessToInstructors && in_array($objBackendUser->id, $arrInstructors))
        {
            // The event is on the first level and the user is set as a instructor in the current event
            $allow = true;
        }
        else
        {
            // Check if user is in a group that is permitted
            $arrGroups = \StringUtil::deserialize($objReleaseLevelModel->groupReleaseLevelRights, true);
            foreach ($arrGroups as $k => $v)
            {
                if (in_array($v['group'], $arrGroupsUserBelongsTo))
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
     * @return null|static
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
        $objBackendUser = \UserModel::findByPk($userId);
        if ($objBackendUser === null)
        {
            return false;
        }

        $objEvent = \CalendarEventsModel::findByPk($eventId);
        if ($objEvent === null)
        {
            return false;
        }

        $objCalendar = $objEvent->getRelated('pid');
        if ($objCalendar === null)
        {
            return false;
        }

        if (!$objCalendar->levelAccessPermissionPackage)
        {
            // Allow if there is no release package defined in tl_calendar
            return true;
        }
        $objEventReleaseLevelPolicyPackageModel = $objCalendar->getRelated('levelAccessPermissionPackage');
        if ($objEventReleaseLevelPolicyPackageModel === null)
        {

            Message::addError('Datarecord tl_event_release_level_policy_package with ID ' . $objCalendar->levelAccessPermissionPackage . ' not found. Error in ' . __METHOD__ . ' Line: ' . __LINE__);
            return false;
        }

        $objReleaseLevelModel = static::findOneById($objEvent->eventReleaseLevel);
        if ($objReleaseLevelModel === null)
        {
            Message::addError('ReleaseLevelModel not found for tl_calendar_events with ID ' . $objEvent->id . '. Error in ' . __METHOD__ . ' Line: ' . __LINE__);
            return false;
        }

        $arrGroupsUserBelongsTo = \StringUtil::deserialize($objBackendUser->groups, true);
        $arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvent->id);

        $allow = false;

        // Check if user has permission
        if ($objBackendUser->admin)
        {
            $allow = true;
        }
        elseif ($objBackendUser->id == $objEvent->author && $objReleaseLevelModel->allowWriteAccessToAuthor)
        {
            // User is author and is allowed to write on this level
            $allow = true;
        }
        elseif ($objReleaseLevelModel->allowWriteAccessToInstructors && in_array($objBackendUser->id, $arrInstructors))
        {
            // User is set as a instructor in the current event
            $allow = true;
        }
        else
        {
            // Check if user is in a group that is permitted
            $arrGroups = \StringUtil::deserialize($objReleaseLevelModel->groupReleaseLevelRights, true);
            foreach ($arrGroups as $k => $v)
            {
                if (in_array($v['group'], $arrGroupsUserBelongsTo))
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
     *
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
        $objBackendUser = \UserModel::findByPk($userId);
        if ($objBackendUser === null)
        {
            return false;
        }

        $objEvent = \CalendarEventsModel::findByPk($eventId);
        if ($objEvent === null)
        {
            return false;
        }

        $objCalendar = $objEvent->getRelated('pid');
        if ($objCalendar === null)
        {
            return false;
        }

        if (!$objCalendar->levelAccessPermissionPackage)
        {
            // Allow if there is no release package defined in tl_calendar
            return true;
        }
        $objEventReleaseLevelPolicyPackageModel = $objCalendar->getRelated('levelAccessPermissionPackage');
        if ($objEventReleaseLevelPolicyPackageModel === null)
        {

            Message::addError('Datarecord tl_event_release_level_policy_package with ID ' . $objCalendar->levelAccessPermissionPackage . ' not found. Error in ' . __METHOD__ . ' Line: ' . __LINE__);
            return false;
        }

        $objReleaseLevelModel = static::findOneById($objEvent->eventReleaseLevel);
        if ($objReleaseLevelModel === null)
        {
            Message::addError('ReleaseLevelModel not found for tl_calendar_events with ID ' . $objEvent->id . '. Error in ' . __METHOD__ . ' Line: ' . __LINE__);
            return false;
        }

        $arrGroupsUserBelongsTo = \StringUtil::deserialize($objBackendUser->groups, true);
        $arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvent->id);

        $allow = false;

        // Check if user has permission
        if ($objBackendUser->admin)
        {
            $allow = true;
        }

        elseif ($objBackendUser->id == $objEvent->author && $objReleaseLevelModel->allowWriteAccessToAuthor)
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
        elseif (in_array($objBackendUser->id, $arrInstructors))
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
        else
        {
            // Check if user is in a group that is permitted
            $arrGroups = \StringUtil::deserialize($objReleaseLevelModel->groupReleaseLevelRights, true);
            $arrAllowedGroups = array();
            foreach ($arrGroups as $k => $v)
            {
                $arrAllowedGroups[$v['group']] = $v;
                if (in_array($v['group'], $arrGroupsUserBelongsTo))
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

        $objEvent = \CalendarEventsModel::findByPk($eventId);
        if ($objEvent === null)
        {
            return false;
        }

        $objCalendar = $objEvent->getRelated('pid');
        if ($objCalendar === null)
        {
            return false;
        }

        if (!$objCalendar->levelAccessPermissionPackage)
        {
            return false;
        }

        $objEventReleaseLevelPolicyPackageModel = $objCalendar->getRelated('levelAccessPermissionPackage');
        if ($objEventReleaseLevelPolicyPackageModel === null)
        {
            return false;
        }

        // Check if the wanted level exists
        $objNewReleaseLevelModel = static::findOneByPidAndLevel($objEventReleaseLevelPolicyPackageModel->id, $level);

        if ($objNewReleaseLevelModel !== null)
        {
            return true;
        }


        return false;
    }


    /**
     * @param $pid
     * @param $level
     * @return static
     */
    public function findOneByPidAndLevel($pid, $level = null)
    {
        $t = static::$strTable;
        $arrColumns = array();
        $arrColumns[] = "$t.pid=? AND  $t.level=?";
        $arrVars = array($pid, $level);

        return static::findOneBy($arrColumns, $arrVars, array());
    }


}
