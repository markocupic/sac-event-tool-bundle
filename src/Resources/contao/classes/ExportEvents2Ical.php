<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\Database;
use Contao\FrontendTemplate;
use Contao\CalendarModel;
use Contao\UserModel;
use Contao\File;
use Contao\Date;


/**
 * Class ExportEvents2Ical
 * @package Markocupic\SacEventToolBundle
 */
class ExportEvents2Ical
{

    /**
     * Download ical file for a certain calendar
     * @param $id
     */
    public static function sendToBrowser($id)
    {

        $arrItems = array();
        $objTemplate = new FrontendTemplate('ical_export');
        $objDb = Database::getInstance();
        $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE pid=? && published=?')->execute($id, '1');
        while ($objEvent->next())
        {

            $arrItem = [];
            $arrItem['organizer_CN'] = UserModel::findByPk($objEvent->mainInstructor)->name;
            $arrItem['organizer_MailTo'] = UserModel::findByPk($objEvent->mainInstructor)->email;
            $arrItem['location'] = $objEvent->location;
            $arrItem['summary'] = $objEvent->title;
            $arrItem['description'] = $objEvent->title;
            $arrItem['dtstart'] = Date::parse('Ymd', $objEvent->startDate);
            $arrItem['dtend'] = Date::parse('Ymd', $objEvent->endDate);
            $arrItem['dtstamp'] = Date::parse('Ymd\THis', time());

            $arrItems[] = $arrItem;
        }
        $calendarTitle = CalendarModel::findByPk($id)->title;
        $objTemplate->calname = $calendarTitle;
        $objTemplate->items = $arrItems;


        $tmpFile = 'system/tmp/sac-pilatus-kurse-' . time() . '.ical';
        $objFile = new File($tmpFile);
        $fcontent = $objTemplate->parse();
        $objFile->append($fcontent);
        $objFile->close();
        sleep(1);

        header('Content-type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename=sac_pilatus_calendar.ical');
        echo $objFile->getContent();
        exit();


    }

}