<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Contao;

/**
 * @method static findByPid(int|mixed|null $id)
 */
class EventReleaseLevelPolicyModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_event_release_level_policy';

    /**
     * @param $eventReleaseRecordId
     *
     * @return static|null
     */
    public static function findNextLevel($eventReleaseRecordId)
    {
        $eventReleaseLevelModel = static::findByPk($eventReleaseRecordId);

        if (null !== $eventReleaseLevelModel) {
            $eventReleasePackageModel = $eventReleaseLevelModel->getRelated('pid');

            if (null !== $eventReleasePackageModel) {
                $arrColumns = [static::$strTable.'.pid=?'];
                $arrColumns[] = static::$strTable.'.level>?';
                $arrInt = [$eventReleasePackageModel->id, $eventReleaseLevelModel->level];
                $arrOpt = ['order' => 'tl_event_release_level_policy.level ASC'];
                $objModel = static::findOneBy($arrColumns, $arrInt, $arrOpt);

                if (null !== $objModel) {
                    return $objModel;
                }
            }
        }

        return null;
    }

    /**
     * @param $eventId
     *
     * @return static|null
     */
    public static function findFirstLevelByEventId($eventId)
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);

        if (null === $objEvent) {
            return null;
        }

        $objEventType = EventTypeModel::findByAlias($objEvent->eventType);

        if (null === $objEventType) {
            return null;
        }

        if ($objEventType->levelAccessPermissionPackage > 0) {
            $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findByPk($objEventType->levelAccessPermissionPackage);
        }

        if (null === $objEventReleaseLevelPolicyPackageModel) {
            Message::addError('Datarecord tl_event_release_level_policy_package with ID '.$objEventType->levelAccessPermissionPackage.' not found. Error in '.__METHOD__.' Line: '.__LINE__);

            return null;
        }

        $options = [
            'order' => 'level ASC',
        ];
        $objReleaseLevelModel = self::findOneBy(['tl_event_release_level_policy.pid=?'], [$objEventReleaseLevelPolicyPackageModel->id], $options);

        if (null === $objReleaseLevelModel) {
            Message::addError('No ReleaseLevelModel found for tl_calendar_events with ID '.$objEvent->id.'. Error in '.__METHOD__.' Line: '.__LINE__);

            return null;
        }

        return $objReleaseLevelModel;
    }

    /**
     * @param $eventId
     *
     * @return static|null
     */
    public static function findLastLevelByEventId($eventId)
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);

        if (null === $objEvent) {
            return null;
        }

        $objEventType = EventTypeModel::findByAlias($objEvent->eventType);

        if (null === $objEventType) {
            return null;
        }

        if ($objEventType->levelAccessPermissionPackage > 0) {
            $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findByPk($objEventType->levelAccessPermissionPackage);
        }

        if (null === $objEventReleaseLevelPolicyPackageModel) {
            Message::addError('Datarecord tl_event_release_level_policy_package with ID '.$objEventType->levelAccessPermissionPackage.' not found. Error in '.__METHOD__.' Line: '.__LINE__);

            return null;
        }

        $options = [
            'order' => 'level DESC',
        ];
        $objReleaseLevelModel = self::findOneBy(['tl_event_release_level_policy.pid=?'], [$objEventReleaseLevelPolicyPackageModel->id], $options);

        if (null === $objReleaseLevelModel) {
            Message::addError('No ReleaseLevelModel found for tl_calendar_events with ID '.$objEvent->id.'. Error in '.__METHOD__.' Line: '.__LINE__);

            return null;
        }

        return $objReleaseLevelModel;
    }

    /**
     * @param $levelId
     *
     * @return static|null
     */
    public static function findPrevLevel($levelId)
    {
        $eventReleaseLevelModel = static::findByPk($levelId);

        if (null !== $eventReleaseLevelModel) {
            $eventReleasePackageModel = $eventReleaseLevelModel->getRelated('pid');

            if (null !== $eventReleasePackageModel) {
                $arrColumns = [static::$strTable.'.pid=?'];
                $arrColumns[] = static::$strTable.'.level<?';
                $arrInt = [$eventReleasePackageModel->id, $eventReleaseLevelModel->level];
                $arrOpt = ['order' => 'tl_event_release_level_policy.level DESC'];
                $objModel = static::findOneBy($arrColumns, $arrInt, $arrOpt);

                if (null !== $objModel) {
                    return $objModel;
                }
            }
        }

        return null;
    }

    /**
     * @param $eventId
     * @param $level
     *
     * @return bool|void
     */
    public static function levelExists($eventId, $level = null)
    {
        $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($eventId);

        if (null !== $objEventReleaseLevelPolicyPackageModel) {
            // Check if the wanted level exists
            $objNewReleaseLevelModel = static::findOneByPidAndLevel($objEventReleaseLevelPolicyPackageModel->id, $level);

            if (null !== $objNewReleaseLevelModel) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $pid
     * @param $level
     *
     * @return static
     */
    public static function findOneByPidAndLevel($pid, $level = null)
    {
        $t = static::$strTable;
        $arrColumns = [];
        $arrColumns[] = "$t.pid=? AND  $t.level=?";
        $arrVars = [$pid, $level];

        return static::findOneBy($arrColumns, $arrVars, []);
    }
}
