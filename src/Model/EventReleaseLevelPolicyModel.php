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

namespace Markocupic\SacEventToolBundle\Model;

use Contao\CalendarEventsModel;
use Contao\Message;
use Contao\Model;

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
     */
    public static function findNextLevel($eventReleaseRecordId): static|null
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
     */
    public static function findLowestLevelByEventId($eventId): static|null
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);

        if (null === $objEvent) {
            return null;
        }

        $objEventType = EventTypeModel::findOneBy('alias', $objEvent->eventType);

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
        $objReleaseLevelModel = static::findOneBy(['tl_event_release_level_policy.pid=?'], [$objEventReleaseLevelPolicyPackageModel->id], $options);

        if (null === $objReleaseLevelModel) {
            Message::addError('No ReleaseLevelModel found for tl_calendar_events with ID '.$objEvent->id.'. Error in '.__METHOD__.' Line: '.__LINE__);

            return null;
        }

        return $objReleaseLevelModel;
    }

    /**
     * @param $eventId
     */
    public static function findHighestLevelByEventId($eventId): static|null
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);

        if (null === $objEvent) {
            return null;
        }

        $objEventType = EventTypeModel::findOneBy('alias', $objEvent->eventType);

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
        $objReleaseLevelModel = static::findOneBy(['tl_event_release_level_policy.pid=?'], [$objEventReleaseLevelPolicyPackageModel->id], $options);

        if (null === $objReleaseLevelModel) {
            Message::addError('No ReleaseLevelModel found for tl_calendar_events with ID '.$objEvent->id.'. Error in '.__METHOD__.' Line: '.__LINE__);

            return null;
        }

        return $objReleaseLevelModel;
    }

    /**
     * @param $levelId
     */
    public static function findPrevLevel($levelId): static|null
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
     */
    public static function levelExists($eventId, $level = null): bool
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
     */
    public static function findOneByPidAndLevel($pid, $level = null): static|null
    {
        $t = static::$strTable;
        $arrColumns = [];
        $arrColumns[] = "$t.pid=? AND  $t.level=?";
        $arrVars = [$pid, $level];

        return static::findOneBy($arrColumns, $arrVars, []);
    }

    /**
     * @param $eventId
     */
    public static function findOneByEventId($eventId): static|null
    {
        $event = CalendarEventsModel::findByPk($eventId);

        if (null === $event) {
            return null;
        }

        $t = static::$strTable;
        $arrColumns = [];
        $arrColumns[] = "$t.id=?";
        $arrVars = [$event->eventReleaseLevel];

        return static::findOneBy($arrColumns, $arrVars, []);
    }
}
