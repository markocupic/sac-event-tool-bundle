<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */

namespace Contao;


/**
 * Class EventReleaseLevelPolicyPackageModel
 * @package Contao
 */
class EventReleaseLevelPolicyPackageModel extends \Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_event_release_level_policy_package';

    /**
     * @param $eventId
     * @return null
     */
    public function findReleaseLevelPolicyPackageModelByEventId($eventId)
    {
        $objEvent = \CalendarEventsModel::findByPk($eventId);
        if ($objEvent === null)
        {
            return null;
        }

        $objEventType = EventTypeModel::findByAlias($objEvent->eventType);
        if($objEventType === null)
        {
            return null;
        }

        if($objEventType->levelAccessPermissionPackage > 0)
        {
            return self::findByPk($objEventType->levelAccessPermissionPackage);
        }
    }

}
