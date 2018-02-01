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

/**
 * Class CalendarEventsMemberModel
 * @package Contao
 */
class CalendarEventsMemberModel extends \Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_calendar_events_member';

    /**
     * @param $memberId
     * @param $eventId
     * @return bool
     */
    public static function isRegistered($memberId, $eventId)
    {
        $objMember = \MemberModel::findByPk($memberId);
        if ($objMember !== null)
        {
            if ($objMember->sacMemberId != '')
            {
                $objEventsMembers = \Database::getInstance()->prepare('SELECT * FROM ' . static::$strTable . ' WHERE pid=? AND sacMemberId=?')->execute($eventId, $objMember->sacMemberId);
                if ($objEventsMembers->numRows)
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $memberId
     * @return array
     */
    public static function findUpcomingEventsByMemberId($memberId)
    {
        $arrEvents = array();
        $objMember = \MemberModel::findByPk($memberId);

        if ($objMember === null)
        {
            return $arrEvents;
        }

        $objEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE endDate>? ORDER BY startDate')->execute(time());
        while ($objEvents->next())
        {
            $objJoinedEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND pid=?')->limit(1)->execute($objMember->sacMemberId, $objEvents->id);
            if ($objJoinedEvents->numRows)
            {
                $objEventsModel = \CalendarEventsModel::findByPk($objEvents->id);
                $arr['id'] = $objEvents->id;
                $arr['eventModel'] = $objEventsModel;
                $arr['eventUrl'] = \Events::generateEventUrl($objEventsModel);
                $arr['dateSpan'] = ($objEventsModel->startDate != $objEventsModel->endDate) ? \Date::parse('d.m.', $objEventsModel->startDate) . ' - ' . \Date::parse('d.m.Y', $objEventsModel->endDate) : \Date::parse('d.m.Y', $objEventsModel->startDate);
                $arr['eventType'] = $objEventsModel->eventType;
                $arr['registrationId'] = $objJoinedEvents->id;
                $arr['eventRegistrationModel'] = \CalendarEventsMemberModel::findByPk($objJoinedEvents->id);
                $arr['unregisterUrl'] = \Frontend::addToUrl('do=unregisterUserFromEvent&amp;registrationId=' . $objJoinedEvents->id);
                $arrEvents[] = $arr;
            }
        }

        return $arrEvents;
    }


    /**
     * @param $memberId
     * @return array
     */
    public static function findPastEventsBySacMemberId($sacMemberId)
    {
        $arrEvents = array();
        $objMember = \MemberModel::findBySacMemberId($sacMemberId);

        if ($objMember === null)
        {
            return $arrEvents;
        }

        $objEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE startDate<? ORDER BY startDate DESC')->execute(time());
        while ($objEvents->next())
        {
            $objJoinedEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND pid=? AND hasParticipated=?')->limit(1)->execute($objMember->sacMemberId, $objEvents->id, '1');
            if ($objJoinedEvents->numRows)
            {
                $arr = $objEvents->row();
                $arr['id'] = $objEvents->id;
                $arr['dateSpan'] = ($objEvents->startDate != $objEvents->endDate) ? \Date::parse('d.m.', $objEvents->startDate) . ' - ' . \Date::parse('d.m.Y', $objEvents->endDate) : \Date::parse('d.m.Y', $objEvents->startDate);
                $arr['registrationId'] = $objJoinedEvents->id;
                $arr['objEvent'] = \CalendarEventsModel::findByPk($objEvents->id);
                $arrEvents[] = $arr;
            }
        }

        return $arrEvents;
    }

}
