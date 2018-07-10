<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\EventOrganizerModel;
use Contao\FilesModel;
use Contao\MemberModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\TourDifficultyModel;
use Contao\TourTypeModel;
use Contao\UserModel;
use Haste\Util\Url;


/**
 * Class CalendarEventsHelper
 * @package Markocupic\SacEventToolBundle
 */
class CalendarEventsHelper extends System
{


    /**
     * @param $id
     * @return string
     * @throws \Exception
     */
    public static function getEventState($id)
    {
        $objEvent = CalendarEventsModel::findByPk($id);
        if ($objEvent === null)
        {
            throw new \Exception(sprintf('Calendar Event with ID %s not found.', $id));
        }

        $objDb = Database::getInstance();
        $objEventsMember = $objDb->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND stateOfSubscription=?')->execute($id, 'subscription-accepted');
        $registrationCount = $objEventsMember->numRows;

        // Event canceled
        if ($objEvent->eventState === 'event_canceled')
        {
            return 'event_status_4';
        }

        // Event deferred
        elseif ($objEvent->eventState === 'event_deferred')
        {
            return 'event_status_6';
        }

        // Event is fully booked
        elseif ($objEvent->eventState === 'event_fully_booked' || ($objEvent->maxMembers > 0 && $registrationCount >= $objEvent->maxMembers))
        {
            return 'event_status_3'; // fa-circle red
        }

        // Event is over or booking in no more possible
        elseif ($objEvent->startDate <= time() || ($objEvent->setRegistrationPeriod && $objEvent->registrationEndDate < time()))
        {
            return 'event_status_2';
        }

        // Booking not possible yet
        elseif ($objEvent->setRegistrationPeriod && $objEvent->registrationStartDate > time())
        {
            return 'event_status_5'; // fa-circle orange
        }

        else
        {
            return 'event_status_1';
        }

    }

    /**
     * @param $eventId
     * @return bool
     */
    public static function eventIsFullyBooked($eventId)
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);
        if ($objEvent !== null)
        {
            $objEventsMember = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND stateOfSubscription=?')->execute($eventId, 'subscription-accepted');
            $registrationCount = $objEventsMember->numRows;
            if ($objEvent->eventState === 'event_fully_booked' || ($objEvent->maxMembers > 0 && $registrationCount >= $objEvent->maxMembers))
            {
                return true;
            }

        }
        return false;
    }

    /**
     * @param $id
     * @return string
     */
    public static function getMainInstructorName($id)
    {

        $objDb = Database::getInstance();
        $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->execute($id);
        if ($objEvent->numRows)
        {

            $arrInstructors = static::getInstructorsAsArray($objEvent->id);
            $objUser = UserModel::findByPk($arrInstructors[0]);
            if ($objUser !== null)
            {
                $arrName = array();
                $arrName[] = $objUser->lastname;
                $arrName[] = $objUser->firstname;
                $arrName = array_filter($arrName);
                return implode(' ', $arrName);
            }
        }
        return '';
    }

    /**
     * Get instructors as array
     * @param $eventId
     * @return array
     */
    public static function getInstructorsAsArray($eventId)
    {
        $arrInstructors = array();
        $objEvent = \CalendarEventsModel::findByPk($eventId);
        if ($objEvent !== null)
        {
            $arrInstr = StringUtil::deserialize($objEvent->instructor, true);
            foreach ($arrInstr as $arrUser)
            {
                if (isset($arrUser['instructorId']))
                {
                    $objUser = UserModel::findByPk($arrUser['instructorId']);
                    if ($objUser !== null)
                    {
                        $arrInstructors[] = $arrUser['instructorId'];
                    }
                }
            }
        }
        return $arrInstructors;
    }

    /**
     * Get instructors names as array
     * @param $eventId
     * @return array
     */
    public static function getInstructorNamesAsArray($eventId, $blnAddMainQualification = false)
    {
        $arrInstructors = array();
        $objEvent = \CalendarEventsModel::findByPk($eventId);
        if ($objEvent !== null)
        {
            $arrInstr = StringUtil::deserialize($objEvent->instructor, true);
            foreach ($arrInstr as $arrUser)
            {
                if (isset($arrUser['instructorId']))
                {
                    $objUser = UserModel::findByPk($arrUser['instructorId']);
                    if ($objUser !== null)
                    {
                        $arrName = array();
                        $arrName[] = $objUser->lastname;
                        $arrName[] = $objUser->firstname;
                        $arrName = array_filter($arrName);
                        if ($blnAddMainQualification && static::getMainQualifikation($objUser->id) != '')
                        {
                            $arrInstructors[] = implode(' ', $arrName) . ' (' . static::getMainQualifikation($objUser->id) . ')';
                        }
                        else
                        {
                            $arrInstructors[] = implode(' ', $arrName);
                        }
                    }
                }
            }
        }
        return $arrInstructors;
    }

    /**
     * @param $id
     * @return string
     */
    public static function getMainQualifikation($id)
    {
        $strQuali = '';
        $objUser = UserModel::findByPk($id);
        if ($objUser !== null)
        {
            $arrQuali = StringUtil::deserialize($objUser->leiterQualifikation, true);
            if (!empty($arrQuali[0]))
            {
                $strQuali = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['leiterQualifikation'][$arrQuali[0]];
            }
        }
        return $strQuali;
    }

    /**
     * @param $arrData
     * @return string
     */
    public static function getGallery($arrData)
    {
        $arrData['type'] = 'gallery';

        if (!isset($arrData['perRow']) || $arrData['perRow'] < 1)
        {
            $arrData['perRow'] = 1;
        }

        $objModel = new ContentModel();
        $objModel->setRow($arrData);

        $objGallery = new \ContentGallery($objModel);
        $strBuffer = $objGallery->generate();

        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['getContentElement']) && is_array($GLOBALS['TL_HOOKS']['getContentElement']))
        {
            foreach ($GLOBALS['TL_HOOKS']['getContentElement'] as $callback)
            {
                $strBuffer = static::importStatic($callback[0])->{$callback[1]}($objModel, $strBuffer, $objGallery);
            }
        }
        return $strBuffer;
    }

    /**
     * @param $id
     * @return string
     */
    public static function getEventImagePath($id)
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $objEvent = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->execute($id);
        if ($objEvent->numRows)
        {
            if ($objEvent->singleSRC != '')
            {
                $objFile = FilesModel::findByUuid($objEvent->singleSRC);

                if ($objFile !== null && is_file($rootDir . '/' . $objFile->path))
                {
                    return $objFile->path;
                }
            }
        }

        return Config::get('SAC_EVT_EVENT_DEFAULT_PREVIEW_IMAGE_SRC');

    }

    /**
     * @param $id
     * @param string $dateFormat
     * @param bool $blnAppendEventDuration
     * @param bool $blnTooltip
     * @return string
     * @throws \Exception
     */
    public static function getEventPeriod($id, $dateFormat = '', $blnAppendEventDuration = true, $blnTooltip = true)
    {
        if ($dateFormat == '')
        {
            $dateFormat = Config::get('dateFormat');
        }

        $dateFormatShortened = $dateFormat;
        if ($dateFormat === 'd.m.Y')
        {
            $dateFormatShortened = 'd.m.';
        }

        $eventDuration = count(self::getEventTimestamps($id));
        $span = Calendar::calculateSpan(self::getStartDate($id), self::getEndDate($id)) + 1;

        if ($eventDuration == 1)
        {
            return Date::parse($dateFormat, self::getStartDate($id)) . ($blnAppendEventDuration ? ' (' . self::getEventDuration($id) . ')' : '');
        }
        elseif ($span == $eventDuration)
        {
            // von bis
            return Date::parse($dateFormatShortened, self::getStartDate($id)) . ' - ' . Date::parse($dateFormat, self::getEndDate($id)) . ($blnAppendEventDuration ? ' (' . self::getEventDuration($id) . ')' : '');
        }
        else
        {
            $arrDates = array();
            $dates = self::getEventTimestamps($id);
            foreach ($dates as $date)
            {
                $arrDates[] = Date::parse($dateFormat, $date);
            }
            if ($blnTooltip)
            {
                return Date::parse($dateFormat, self::getStartDate($id)) . ($blnAppendEventDuration ? ' (' . self::getEventDuration($id) . ')' : '') . '<br><a tabindex="0" class="more-date-infos" data-toggle="tooltip" data-placement="bottom" title="Eventdaten: ' . implode(', ', $arrDates) . '">und weitere</a>';
            }
            else
            {
                $dateString = '';
                foreach (self::getEventTimestamps($id) as $tstamp)
                {
                    $dateString .= sprintf('<time datetime="%s">%s</time>', Date::parse('Y-m-d', $tstamp), Date::parse('D, d.m.Y', $tstamp));
                }
                $dateString .= $blnAppendEventDuration ? sprintf('<time>(%s)</time>', self::getEventDuration($id)) : '';
                return $dateString;
            }
        }
    }

    /**
     * @param $id
     * @return array|bool
     */
    public static function getEventTimestamps($id)
    {
        $arrRepeats = array();
        $objDb = Database::getInstance();
        $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->execute($id);
        if ($objEvent->numRows)
        {
            $arrDates = StringUtil::deserialize($objEvent->eventDates);
            if (!is_array($arrDates) || empty($arrDates))
            {
                return false;
            }

            foreach ($arrDates as $v)
            {
                $arrRepeats[] = $v['new_repeat'];
            }
        }
        return $arrRepeats;

    }

    /**
     * @param $eventId
     * @return int
     */
    public static function getStartDate($eventId)
    {
        $tstamp = 0;
        $objDb = Database::getInstance();
        $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->execute($eventId);
        if ($objEvent->numRows)
        {
            $arrDates = StringUtil::deserialize($objEvent->eventDates);
            if (!is_array($arrDates) || empty($arrDates))
            {
                return $tstamp;
            }
            $tstamp = $arrDates[0]['new_repeat'];
        }
        return $tstamp;

    }

    /**
     * @param $eventId
     * @return int
     */
    public static function getEndDate($eventId)
    {
        $tstamp = 0;
        $objDb = Database::getInstance();
        $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->execute($eventId);
        if ($objEvent->numRows)
        {
            $arrDates = StringUtil::deserialize($objEvent->eventDates);
            if (!is_array($arrDates) || empty($arrDates))
            {
                return $tstamp;
            }
            $tstamp = $arrDates[count($arrDates) - 1]['new_repeat'];
        }
        return $tstamp;

    }

    /**
     * @param $id
     * @return string
     * @throws \Exception
     */
    public static function getEventDuration($id)
    {

        $objDb = Database::getInstance();
        $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->execute($id);
        if ($objEvent->numRows === null)
        {
            throw new \Exception(sprintf('Calendar Event with ID %s not found.', $id));
        }

        $arrDates = StringUtil::deserialize($objEvent->eventDates);

        if ($objEvent->durationInfo != '')
        {
            return (string)$objEvent->durationInfo;
        }
        elseif (is_array($arrDates) && !empty($arrDates))
        {
            return sprintf('%s Tage', count($arrDates));
        }
        else
        {
            return '';
        }

    }

    /**
     * @param $eventId
     * @return array
     */
    public static function getTourTechDifficultiesAsArray($eventId, $tooltip = false)
    {
        $arrReturn = array();
        $objEventModel = CalendarEventsModel::findByPk($eventId);
        if ($objEventModel !== null)
        {
            $arrValues = StringUtil::deserialize($objEventModel->tourTechDifficulty, true);
            if (!empty($arrValues) && is_array($arrValues))
            {
                $arrDiff = array();
                foreach ($arrValues as $difficulty)
                {
                    $strDiff = '';
                    $strDiffTitle = '';
                    if (strlen($difficulty['tourTechDifficultyMin']) && strlen($difficulty['tourTechDifficultyMax']))
                    {
                        $objDiff = TourDifficultyModel::findByPk(intval($difficulty['tourTechDifficultyMin']));
                        if ($objDiff !== null)
                        {
                            $strDiff = $objDiff->shortcut;
                            $strDiffTitle = $objDiff->title;

                        }
                        $objDiff = TourDifficultyModel::findByPk(intval($difficulty['tourTechDifficultyMax']));
                        if ($objDiff !== null)
                        {
                            $max = $objDiff->shortcut;
                            $strDiff .= ' - ' . $max;
                            $strDiffTitle .= ' - ' . $objDiff->title;
                        }
                    }
                    elseif (strlen($difficulty['tourTechDifficultyMin']))
                    {
                        $objDiff = TourDifficultyModel::findByPk(intval($difficulty['tourTechDifficultyMin']));
                        if ($objDiff !== null)
                        {
                            $strDiff = $objDiff->shortcut;
                            $strDiffTitle = $objDiff->title;

                        }
                        $arrDiff[] = $strDiff;
                    }


                    if ($strDiff !== '')
                    {
                        if ($tooltip)
                        {
                            $html = '<span class="badge badge-pill badge-primary" data-toggle="tooltip" data-placement="top" title="Techn. Schwierigkeit: %s">%s</span>';
                            $arrReturn[] = sprintf($html, $strDiffTitle, $strDiff);
                        }
                        else
                        {
                            $arrReturn[] = $strDiff;
                        }
                    }
                }
            }
        }
        return $arrReturn;
    }

    /**
     * @param $eventId
     * @param string $field
     * @return array
     */
    public static function getTourTypesAsArray($eventId, $field = 'shortcut', $tooltip = false)
    {
        $arrReturn = array();

        $objEventModel = CalendarEventsModel::findByPk($eventId);
        if ($objEventModel !== null)
        {
            $arrValues = StringUtil::deserialize($objEventModel->tourType, true);
            if (!empty($arrValues) && is_array($arrValues))
            {
                foreach ($arrValues as $id)
                {
                    $objModel = TourTypeModel::findByPk($id);
                    if ($objModel !== null)
                    {
                        if ($tooltip)
                        {
                            $html = '<span class="badge badge-pill badge-secondary" data-toggle="tooltip" data-placement="top" title="Typ: %s">%s</span>';
                            $arrReturn[] = sprintf($html, $objModel->{'title'}, $objModel->{$field});
                        }
                        else
                        {
                            $arrReturn[] = $objModel->{$field};
                        }
                    }
                }
            }
        }

        return $arrReturn;
    }

    /**
     * Return a bootstrap badge with some booking count information
     * @param $eventId
     * @return string
     */
    public static function getBookingCounter($eventId)
    {
        $strBadge = '<span class="badge badge-pill badge-%s" data-toggle="tooltip" data-placement="top" title="%s">%s</span>';
        $objDb = Database::getInstance();
        $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->limit(1)->execute($eventId);
        if ($objEvent->numRows)
        {
            $calendarEventsMember = $objDb->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? && stateOfSubscription=?')->execute($eventId, 'subscription-accepted');
            $memberCount = $calendarEventsMember->numRows;

            if ($objEvent->eventState === 'event_canceled')
            {
                // Event canceled
                return '';
            }
            // At the moment "diableOnlineREgistration" is deactivated anyway
            if (!$objEvent->disableOnlineRegistration)
            {
                if ($objEvent->addMinAndMaxMembers && $objEvent->maxMembers > 0)
                {
                    if ($memberCount >= $objEvent->maxMembers)
                    {
                        // Event fully booked
                        return sprintf($strBadge, 'danger', 'ausgebucht', $memberCount . '/' . $objEvent->maxMembers);
                    }
                    if ($memberCount < $objEvent->maxMembers)
                    {
                        // Free places
                        return sprintf($strBadge, 'success', sprintf('noch %s freie Pl&auml;tze', $objEvent->maxMembers - $memberCount), $memberCount . '/' . $objEvent->maxMembers);
                    }
                }
                else
                {
                    // There is no booking limit. Show registered members
                    return sprintf($strBadge, 'success', $memberCount . ' Anmeldungen', $memberCount);
                }
            }
        }
        return '';
    }

    /**
     * Is event bookable
     * Are there some free places?
     * @param $eventId
     * @return string
     */
    public static function isEventBookable($eventId)
    {
        $objDb = Database::getInstance();
        $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->limit(1)->execute($eventId);
        if ($objEvent->numRows)
        {
            $calendarEventsMember = $objDb->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? && stateOfSubscription=?')->execute($eventId, 'subscription-accepted');
            $memberCount = $calendarEventsMember->numRows;

            if ($objEvent->eventState === 'event_canceled')
            {
                return false;
            }


            if (!$objEvent->disableOnlineRegistration)
            {
                if ($objEvent->addMinAndMaxMembers && $objEvent->maxMembers > 0)
                {
                    if ($memberCount >= $objEvent->maxMembers)
                    {
                        // Event fully booked
                        return false;
                    }
                    if ($memberCount < $objEvent->maxMembers)
                    {
                        // Free places
                        return true;
                    }
                }
                else
                {
                    // There is no booking limit.
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * @param $eventId
     * @param string $field
     * @return array
     */
    public static function getEventOrganizersAsArray($eventId, $field = 'title')
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);
        $arrReturn = array();
        if ($objEvent !== null)
        {
            $arrValues = StringUtil::deserialize($objEvent->organizers, true);
            if (!empty($arrValues) && is_array($arrValues))
            {
                foreach ($arrValues as $id)
                {
                    $objModel = EventOrganizerModel::findByPk($id);
                    if ($objModel !== null)
                    {
                        $arrReturn[] = $objModel->{$field};
                    }
                }
            }
        }

        return $arrReturn;
    }

    /**
     * Check if event dates are not already occupied by an other booked event
     * @param $eventId
     * @param $memberId
     * @return bool
     */
    public static function areBookingDatesOccupied($eventId, $memberId)
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);
        $objMember = MemberModel::findByPk($memberId);
        if ($objEvent === null || $objMember === null)
        {
            return true;
        }

        $arrEventDates = array();
        $arrEventRepeats = StringUtil::deserialize($objEvent->eventDates, true);
        if (!empty($arrEventRepeats) && is_array($arrEventRepeats))
        {
            foreach ($arrEventRepeats as $eventRepeat)
            {
                if (isset($eventRepeat['new_repeat']) && !empty($eventRepeat['new_repeat']))
                {
                    $arrEventDates[] = $eventRepeat['new_repeat'];
                }
            }
        }

        $arrOccupiedDates = array();
        // Get all future events of the member
        $objMemberEvents = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId!=? AND contaoMemberId=? AND stateOfSubscription=? AND hasParticipated=?')
            ->execute($objEvent->id, $objMember->id, 'subscription-accepted', '');
        while ($objMemberEvents->next())
        {

            $objMemberEvent = CalendarEventsModel::findByPk($objMemberEvents->eventId);
            if ($objMemberEvent !== null)
            {
                $arrRepeats = StringUtil::deserialize($objMemberEvent->eventDates, true);
                if (!empty($arrRepeats) && is_array($arrRepeats))
                {
                    foreach ($arrRepeats as $repeat)
                    {
                        if (isset($repeat['new_repeat']) && !empty($repeat['new_repeat']))
                        {
                            if (in_array($repeat['new_repeat'], $arrEventDates))
                            {
                                // This date is already occupied (do not allow booking)
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param $objEvent
     * @return null|string|string[]
     */
    public static function generateEventPreviewUrl($objEvent)
    {
        $strUrl = '';
        $objPage = PageModel::findByPk($objEvent->getRelated('pid')->previewPage);

        if ($objPage instanceof PageModel)
        {
            $params = (Config::get('useAutoItem') ? '/' : '/events/') . ($objEvent->alias ?: $objEvent->id);
            $strUrl = ampersand($objPage->getFrontendUrl($params));
            $strUrl = Url::addQueryString('eventToken=' . $objEvent->eventToken, $strUrl);
        }

        return $strUrl;
    }

    /**
     * Get tour profile as array
     * @param $eventId
     * @return array
     */
    public static function getTourProfileAsArray($eventId)
    {
        $arrProfile = array();
        $objEventModel = CalendarEventsModel::findByPk($eventId);
        if ($objEventModel !== null)
        {
            if (!empty($objEventModel->tourProfile) && is_array(deserialize($objEventModel->tourProfile)))
            {
                $m = 0;
                $arrTourProfile = StringUtil::deserialize($objEventModel->tourProfile, true);
                foreach ($arrTourProfile as $profile)
                {
                    if ($profile['tourProfileAscentMeters'] == '' && $profile['tourProfileAscentTime'] == '' && $profile['tourProfileDescentMeters'] == '' && $profile['tourProfileDescentTime'] == '')
                    {
                        continue;
                    }
                    $m++;

                    $arrAsc = array();
                    $arrDesc = array();
                    if (count($arrTourProfile) > 1)
                    {
                        $strProfile = sprintf('%s. Tag: ', $m);
                    }
                    else
                    {
                        $strProfile = '';
                    }

                    if ($profile['tourProfileAscentMeters'] != '')
                    {
                        $arrAsc[] = sprintf('%s Hm', $profile['tourProfileAscentMeters']);
                    }
                    
                    if ($profile['tourProfileAscentTime'] != '')
                    {
                        $arrAsc[] = sprintf('%s h', $profile['tourProfileAscentTime']);
                    }

                    if ($profile['tourProfileDescentMeters'] != '')
                    {
                        $arrDesc[] = sprintf('%s Hm', $profile['tourProfileDescentMeters']);
                    }

                    if ($profile['tourProfileDescentTime'] != '')
                    {
                        $arrDesc[] = sprintf('%s h', $profile['tourProfileDescentTime']);
                    }

                    if (count($arrAsc) > 0)
                    {
                        $strProfile .= 'Aufst: ' . implode('/', $arrAsc);
                    }

                    if (count($arrDesc) > 0)
                    {
                        $strProfile .= ($strProfile != '' ? ', ' : '') . 'Abst: ' . implode('/', $arrDesc);
                    }

                    $arrProfile[] = $strProfile;
                }
            }
        }
        return $arrProfile;
    }

    /**
     * @param $field
     * @param $value
     * @param $strTable
     * @param $dataRecord
     * @param $dca
     * @return string
     */
    public function exportRegistrationListHook($field, $value, $strTable, $dataRecord, $dca)
    {
        if ($strTable === 'tl_calendar_events_member')
        {
            if ($field === 'dateOfBirth' || $field === 'addedOn')
            {
                if (intval($value))
                {
                    $value = Date::parse('Y-m-d', $value);
                }
            }
            if ($field === 'phone' || $field === 'phone')
            {
                $value = str_replace(' ', '', $value);
                if (strlen($value) == 10)
                {
                    // Format phone numbers to 0xx xxx xx xx
                    $value = preg_replace('/^0(\d{2})(\d{3})(\d{2})(\d{2})/', '0${1} ${2} ${3} ${4}', $value, -1, $count);
                }
            }

            if ($field === 'stateOfSubscription')
            {
                Controller::loadLanguageFile('tl_calendar_events_member');
                if (strlen($value) && isset($GLOBALS['TL_LANG']['tl_calendar_events_member'][$value]))
                {
                    $value = $GLOBALS['TL_LANG']['tl_calendar_events_member'][$value];
                }
            }

        }
        return $value;
    }


}