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

use Markocupic\SacEventToolBundle\CalendarEventsHelper;

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
                $objEventsMembers = \Database::getInstance()->prepare('SELECT * FROM ' . static::$strTable . ' WHERE eventId=? AND sacMemberId=?')->execute($eventId, $objMember->sacMemberId);
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

        // Get ids of events member has joined
        $arrIDS = array();
        $objEventsMemberHasRegistered = \Database::getInstance()->prepare('SELECT eventId FROM tl_calendar_events_member WHERE sacMemberId=?')->execute($objMember->sacMemberId);
        while ($objEventsMemberHasRegistered->next())
        {
            $arrIDS[] = $objEventsMemberHasRegistered->eventId;
        }

        if (count($arrIDS) > 0)
        {
            $objEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE id IN(' . implode(',', $arrIDS) . ') AND endDate>? AND published=? ORDER BY startDate')->execute(time(), '1');
            while ($objEvents->next())
            {
                $objJoinedEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND eventId=?')->limit(1)->execute($objMember->sacMemberId, $objEvents->id);
                if ($objJoinedEvents->numRows)
                {
                    $arr = $objEvents->row();
                    $objEventsModel = \CalendarEventsModel::findByPk($objEvents->id);
                    $arr['id'] = $objEvents->id;
                    $arr['eventModel'] = $objEventsModel;
                    $arr['eventUrl'] = \Events::generateEventUrl($objEventsModel);
                    $arr['dateSpan'] = ($objEventsModel->startDate != $objEventsModel->endDate) ? \Date::parse('d.m.', $objEventsModel->startDate) . ' - ' . \Date::parse('d.m.Y', $objEventsModel->endDate) : \Date::parse('d.m.Y', $objEventsModel->startDate);
                    $arr['eventType'] = $objEventsModel->eventType;
                    $arr['registrationId'] = $objJoinedEvents->id;
                    $arr['eventRegistrationModel'] = CalendarEventsMemberModel::findByPk($objJoinedEvents->id);
                    $arr['unregisterUrl'] = \Frontend::addToUrl('do=unregisterUserFromEvent&amp;registrationId=' . $objJoinedEvents->id);
                    $arrEvents[] = $arr;
                }
            }
        }

        return $arrEvents;
    }

    /**
     * @param $memberId
     * @param array $arrEventTypeFilter
     * @return array
     */
    public static function findPastEventsByMemberId($memberId, $arrEventTypeFilter = array())
    {
        $arrEvents = array();
        $arrEventIDS = array();
        $objMember = MemberModel::findByPk($memberId);

        if ($objMember !== null)
        {
            $objJoinedEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=?')->execute($objMember->sacMemberId);
            if ($objJoinedEvents->numRows)
            {
                $arrEventIDS = $objJoinedEvents->fetchEach('eventId');
                $arrEventIDS = array_values(array_unique($arrEventIDS));
            }

            if (count($arrEventIDS))
            {
                $objEvents = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE id IN(' . implode(',', $arrEventIDS) . ') AND startDate<? ORDER BY startDate DESC')->execute(time());
                while ($objEvents->next())
                {
                    // Filter by event type
                    if (count($arrEventTypeFilter) > 0)
                    {
                        if (!in_array($objEvents->eventType, $arrEventTypeFilter))
                        {
                            continue;
                        }
                    }

                    $objJoinedEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND eventId=?')->limit(1)->execute($objMember->sacMemberId, $objEvents->id);
                    if ($objJoinedEvents->numRows)
                    {
                        $arr['title'] = $objEvents->title;
                        $arr['dateSpan'] = ($objEvents->startDate != $objEvents->endDate) ? \Date::parse('d.m.', $objEvents->startDate) . ' - ' . \Date::parse('d.m.Y', $objEvents->endDate) : \Date::parse('d.m.Y', $objEvents->startDate);
                        $arr['registrationId'] = $objJoinedEvents->id;
                        $arr['objEvent'] = \CalendarEventsModel::findByPk($objEvents->id);
                        $arr['eventType'] = $objEvents->eventType;
                        $arr['eventRegistrationModel'] = CalendarEventsMemberModel::findByPk($objJoinedEvents->id);
                        $arrEvents[] = $arr;
                    }
                }
            }
        }

        return $arrEvents;
    }

    /**
     * List all events where user has participated as member or instructor
     * @param $memberId
     * @return array
     */
    public static function findPastEventsByMemberIdAndTimeSpan($memberId, $timeSpanDays = 0)
    {
        $arrEvents = array();
        $objMember = \MemberModel::findByPk($memberId);

        $memberHasUserAccount = false;
        if ($objMember !== null)
        {
            if ($objMember->sacMemberId != '')
            {
                $objUser = UserModel::findBySacMemberId($objMember->sacMemberId);
                if ($objUser !== null)
                {
                    $memberHasUserAccount = true;
                }
            }
        }

        $queryTimeSpan = '';
        if ($timeSpanDays > 0)
        {
            $limit = time() - $timeSpanDays * 24 * 3600;
            $queryTimeSpan = ' AND startDate>' . $limit . ' ';
        }

        $arrEventsAsInstructor = array();
        if ($memberHasUserAccount)
        {
            $objEvents1 = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE startDate<? ' . $queryTimeSpan . ' ORDER BY startDate DESC')->execute(time());
            while ($objEvents1->next())
            {
                if ($objEvents1->instructor != '')
                {
                    $arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvents1->id);
                    if (in_array($objUser->id, $arrInstructors))
                    {
                        $arrEventsAsInstructor[] = $objEvents1->id;
                    }
                }
            }
        }

        $objEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE startDate<? ' . $queryTimeSpan . ' ORDER BY startDate DESC')->execute(time());
        while ($objEvents->next())
        {
            $objJoinedEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND eventId=? AND hasParticipated=?')->limit(1)->execute($objMember->sacMemberId, $objEvents->id, '1');
            if ($objJoinedEvents->numRows)
            {
                $arr = $objEvents->row();
                $arr['id'] = $objEvents->id;
                $arr['role'] = 'member';
                $arr['dateSpan'] = ($objEvents->startDate != $objEvents->endDate) ? \Date::parse('d.m.', $objEvents->startDate) . ' - ' . \Date::parse('d.m.Y', $objEvents->endDate) : \Date::parse('d.m.Y', $objEvents->startDate);
                $arr['registrationId'] = $objJoinedEvents->id;
                $arr['objEvent'] = \CalendarEventsModel::findByPk($objEvents->id);
                $arr['eventRegistrationModel'] = CalendarEventsMemberModel::findByPk($objJoinedEvents->id);
                $arrEvents[] = $arr;
            }
            // Instructor
            elseif (in_array($objEvents->id, $arrEventsAsInstructor))
            {
                $arr = $objEvents->row();
                $arr['id'] = $objEvents->id;
                $arr['role'] = 'instructor';
                $arr['dateSpan'] = ($objEvents->startDate != $objEvents->endDate) ? \Date::parse('d.m.', $objEvents->startDate) . ' - ' . \Date::parse('d.m.Y', $objEvents->endDate) : \Date::parse('d.m.Y', $objEvents->startDate);
                $arr['registrationId'] = null;
                $arr['objEvent'] = \CalendarEventsModel::findByPk($objEvents->id);
                $arr['eventRegistrationModel'] = null;
                $arrEvents[] = $arr;
            }
        }

        return $arrEvents;
    }
}
