<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;

use Contao\CalendarEventsMemberModel;
use Contao\Config;
use Contao\Date;
use Contao\Folder;
use Contao\MemberModel;
use Contao\Message;
use Contao\System;

/**
 * Class ClearPersonalMemberData
 * @package Markocupic\SacEventToolBundle
 */
class ClearPersonalMemberData
{

    /**
     *
     */
    public static function anonymizeOrphanedCalendarEventsMemberDataRecords()
    {
        $objEventsMember = CalendarEventsMemberModel::findAll();
        while ($objEventsMember->next())
        {
            if ($objEventsMember->contaoMemberId > 0 || $objEventsMember->sacMemberId > 0)
            {
                $blnFound = false;
                if ($objEventsMember->contaoMemberId > 0)
                {
                    if (MemberModel::findByPk($objEventsMember->contaoMemberId) !== null)
                    {
                        $blnFound = true;
                    }
                }
                if ($objEventsMember->sacMemberId > 0)
                {
                    if (MemberModel::findBySacMemberId($objEventsMember->sacMemberId) !== null)
                    {
                        $blnFound = true;
                    }
                }
                if (!$blnFound)
                {
                    self::anonymizeCalendarEventsMemberDataRecord($objEventsMember->id);
                }
            }
        }
    }

    /**
     * @param $calendarEventsMemberId
     * @return bool
     */
    public static function anonymizeCalendarEventsMemberDataRecord($calendarEventsMemberId)
    {
        $objCalendarEventsMember = CalendarEventsMemberModel::findByPk($calendarEventsMemberId);
        if ($objCalendarEventsMember !== null)
        {
            if ($objCalendarEventsMember !== null)
            {
                if (!$objCalendarEventsMember->anonymized)
                {
                    System::log(sprintf('Anonymized tl_calendar_events_member.id=%s. Firstname: %s, Lastname: %s (%s)"', $objCalendarEventsMember->id, $objCalendarEventsMember->firstname, $objCalendarEventsMember->lastname, $objCalendarEventsMember->sacMemberId), __FILE__ . ' Line: ' . __LINE__, 'ANONYMIZED_CALENDAR_EVENTS_MEMBER_DATA');

                    $objCalendarEventsMember->firstname = 'Vorname [anonymisiert]';
                    $objCalendarEventsMember->lastname = 'Nachname [anonymisiert]';
                    $objCalendarEventsMember->email = '';
                    $objCalendarEventsMember->sacMemberId = '';
                    $objCalendarEventsMember->street = 'Adresse [anonymisiert]';
                    $objCalendarEventsMember->postal = '0';
                    $objCalendarEventsMember->city = 'Ort [anonymisiert]';
                    $objCalendarEventsMember->mobile = '';
                    $objCalendarEventsMember->foodHabits = '';
                    $objCalendarEventsMember->dateOfBirth = '';
                    $objCalendarEventsMember->contaoMemberId = 0;
                    $objCalendarEventsMember->notes = 'Benutzerdaten anonymisiert am ' . Date::parse('d.m.Y', time());
                    $objCalendarEventsMember->emergencyPhone = '999 99 99';
                    $objCalendarEventsMember->emergencyPhoneName = ' [anonymisiert]';
                    $objCalendarEventsMember->anonymized = '1';
                    $objCalendarEventsMember->save();
                }
                return true;
            }
        }

        return false;
    }

    /**
     * @param $memberId
     */
    public static function disableLogin($memberId)
    {
        $objMember = MemberModel::findByPk($memberId);
        if ($objMember !== null)
        {
            System::log(sprintf('Login for member with ID:%s has been deactivated.', $objMember->id), __FILE__ . ' Line: ' . __LINE__, Config::get('DISABLE_MEMBER_LOGIN'));
            $objMember->login = '';
            $objMember->password = '';
            $objMember->save();
        }
    }

    /**
     * @param $memberId
     */
    public static function deleteFrontendAccount($memberId)
    {
        $objMember = MemberModel::findByPk($memberId);
        if ($objMember !== null)
        {
            System::log(sprintf('Member with ID:%s has been deleted.', $objMember->id), __FILE__ . ' Line: ' . __LINE__, Config::get('DELETE_MEMBER'));
            $objMember->delete();
        }
    }

    /**
     * @param $memberId
     * @param bool $blnForceClearing
     * @return bool
     * @throws \Exception
     */
    public static function clearMemberProfile($memberId, $blnForceClearing = false)
    {
        $arrEventsMember = array();
        $blnHasError = false;
        $objMember = MemberModel::findByPk($memberId);
        if ($objMember !== null)
        {
            // Upcoming events
            $arrEvents = CalendarEventsMemberModel::findUpcomingEventsByMemberId($objMember->id);
            foreach ($arrEvents as $arrEvent)
            {
                $objEventsMember = CalendarEventsMemberModel::findByPk($arrEvent['registrationId']);
                if ($objEventsMember !== null)
                {
                    if ($arrEvent['eventModel'] !== null)
                    {
                        $objEvent = $arrEvent['eventModel'];
                        if ($blnForceClearing)
                        {
                            continue;
                        }
                        elseif ($objEventsMember->stateOfSubscription === 'subscription-refused')
                        {
                            continue;
                        }
                        else
                        {
                            $arrErrorMsg[] = sprintf('Dein Profil kann nicht gelöscht werden, weil du beim Event "%s [%s]" vom %s auf der Buchungsliste stehst. Bitte melde dich zuerst vom Event ab oder nimm gegebenenfalls mit dem Leiter Kontakt auf.', $objEvent->title, $objEventsMember->stateOfSubscription, Date::parse(Config::get('dateFormat'), $objEvent->startDate));
                            $blnHasError = true;
                        }
                    }
                }
            }

            // Past events
            $arrEvents = CalendarEventsMemberModel::findPastEventsByMemberId($objMember->id);
            foreach ($arrEvents as $arrEvent)
            {
                $objEventsMember = CalendarEventsMemberModel::findByPk($arrEvent['registrationId']);

                if ($objEventsMember !== null)
                {
                    $arrEventsMember[] = $objEventsMember->id;
                }
            }

            if ($blnHasError)
            {
                foreach ($arrErrorMsg as $errorMsg)
                {
                    Message::add($errorMsg, 'TL_ERROR', TL_MODE);
                }
                return false;
            }
            else
            {
                // Anonymize entries from tl_calendar_events_member
                foreach ($arrEventsMember as $eventsMemberId)
                {
                    $objEventsMember = CalendarEventsMemberModel::findByPk($eventsMemberId);
                    if ($objEventsMember !== null)
                    {
                        self::anonymizeCalendarEventsMemberDataRecord($objEventsMember->id);
                    }
                }
                // Delete avatar directory
                self::deleteAvatarDirectory($memberId);

                return true;
            }
        }
        return false;
    }

    /**
     * @param $memberId
     * @throws \Exception
     */
    public static function deleteAvatarDirectory($memberId)
    {
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');
        $strAvatarDir = Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY');
        if (is_dir($rootDir . '/' . $strAvatarDir . '/' . $memberId))
        {
            $strDir = $strAvatarDir . '/' . $memberId;
            $objDir = new Folder($strDir);
            if ($objDir !== null)
            {
                System::log(sprintf('Deleted avatar directory "%s" for member with ID:%s.', $strDir, $memberId), __FILE__ . ' Line: ' . __LINE__, 'DELETED_AVATAR_DORECTORY');
                $objDir->purge();
                $objDir->delete();
            }
        }
    }

    /**
     * @param $memberId
     * @return array
     */
    private static function findUpcomingEventsByMemberId($memberId)
    {
        $arrEvents = array();
        $objMember = \MemberModel::findByPk($memberId);

        if ($objMember !== null)
        {
            $objEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE endDate>? ORDER BY startDate')->execute(time());
            while ($objEvents->next())
            {
                $objJoinedEvents = \Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND eventId=?')->limit(1)->execute($objMember->sacMemberId, $objEvents->id);
                if ($objJoinedEvents->numRows)
                {
                    $arr = $objEvents->row();
                    $objEventsModel = \CalendarEventsModel::findByPk($objEvents->id);
                    $arr['id'] = $objEvents->id;
                    $arr['eventModel'] = $objEventsModel;
                    $arr['registrationId'] = $objJoinedEvents->id;
                    $arr['eventRegistrationModel'] = CalendarEventsMemberModel::findByPk($objJoinedEvents->id);
                    $arrEvents[] = $arr;
                }
            }
        }
        return $arrEvents;
    }

}
