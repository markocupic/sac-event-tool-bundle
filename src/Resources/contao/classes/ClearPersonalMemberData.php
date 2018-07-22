<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Date;
use Contao\MemberModel;


/**
 * Class ClearPersonalMemberData
 * @package Markocupic\SacEventToolBundle
 */
class ClearPersonalMemberData
{
    /**
     * @param $objCalendarEventsMember
     * @return bool
     */
    public static function anonymizeCalendarEventsMemberDataRecord(CalendarEventsMemberModel $objCalendarEventsMember)
    {
        mail('m.cupic@gmx.ch',$objCalendarEventsMember->firstname,'');
        return;
        if ($objCalendarEventsMember !== null)
        {
            if (!$objCalendarEventsMember->anonymized)
            {
                $objCalendarEventsMember->firstname = 'Anonymisierter Vorname';
                $objCalendarEventsMember->lastname = 'Anonymisierter Nachname';
                $objCalendarEventsMember->email = '';
                $objCalendarEventsMember->sacMemberId = '';
                $objCalendarEventsMember->street = 'Anonymisierte Adresse';
                $objCalendarEventsMember->postal = '0';
                $objCalendarEventsMember->city = 'Anonymisierter Ort';
                $objCalendarEventsMember->mobile = '';
                $objCalendarEventsMember->foodHabits = '';
                $objCalendarEventsMember->dateOfBirth = 0;
                $objCalendarEventsMember->contaoMemberId = 0;
                $objCalendarEventsMember->notes = 'Benutzerdaten anonymisiert am ' . Date::parse('d.m.Y', time());
                $objCalendarEventsMember->emergencyPhone = '999 99 99';
                $objCalendarEventsMember->emergencyPhoneName = 'anonymisiert';
                $objCalendarEventsMember->anonymized = '1';
                $objCalendarEventsMember->save();
            }
            return true;
        }
        return false;
    }

    /**
     *
     */
    public static function anonymizeOrphanedCalendarEventsMemberDataRecords()
    {
        $objEventsMember = CalendarEventsMemberModel::findAll();
        while($objEventsMember->next())
        {
            if($objEventsMember->contaoMemberId > 0 || $objEventsMember->sacMemberId > 0 )
            {
                $blnFound = false;
                if($objEventsMember->contaoMemberId > 0)
                {
                    if(MemberModel::findByPk($objEventsMember->contaoMemberId) !== null)
                    {
                        $blnFound = true;
                    }
                }
                if($objEventsMember->sacMemberId > 0)
                {
                    if(MemberModel::findBySacMemberId($objEventsMember->sacMemberId) !== null)
                    {
                        $blnFound = true;
                    }
                }
                if(!$blnFound)
                {
                    self::anonymizeCalendarEventsMemberDataRecord($objEventsMember);
                }
            }
        }
    }


    /**
     *
     */
    public static function deactivateFrontendAccount(MemberModel $objMember)
    {

        if($objMember !== null)
       {
           $objMember->login = '';
           $objMember->password = '';
           $objMember->save();
       }
    }

    /**
     *
     */
    public static function deleteFrontendAccount(MemberModel $objMember)
    {
        if($objMember !== null)
        {
            $objMember->delete();
        }
    }
}