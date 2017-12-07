<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\Input;
use Contao\Date;
use Contao\CalendarEventsModel;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\System;


/**
 * Class ExportEvents2Typo3
 * @package Markocupic\SacEventToolBundle
 */
class ExportEvents2Typo3
{
    /**
     * @param $id
     */
    public static function sendToBrowser($id)
    {
        System::loadLanguageFile('default', 'de');

        $output = '';
        $objEvent = CalendarEventsModel::findByPk($id);
        if ($objEvent !== null)
        {

            if ($objEvent->teaser != '')
            {
                $output .= "<p>";
                //$output .= "<b>Kursziele</b><br>";
                $output .= static::format($objEvent->teaser);
                $output .= "</p>";
            }

            if ($objEvent->terms != '')
            {
                $output .= "<p>";
                $output .= "<b>Kursziele</b><br>";
                $output .= static::format($objEvent->terms);
                $output .= "</p>";
            }

            if ($objEvent->issues != '')
            {
                $output .= "<p>";
                $output .= "<b>Kursinhalte</b><br>";
                $output .= static::format($objEvent->issues);
                $output .= "</p>";
            }

            if ($objEvent->requirements != '')
            {
                $output .= "<p>";
                $output .= "<b>Voraussetzungen</b><br>";
                $output .= static::format($objEvent->requirements);
                $output .= "</p>";
            }

            if ($objEvent->repeatFixedDates != '')
            {
                $output .= "<p>";
                $output .= "<b>Kursdaten</b><br>";
                foreach (CalendarSacEvents::getEventTimestamps($objEvent->id) as $tstamp)
                {
                    $output .= Date::parse('D, d.m.Y', $tstamp) . "<br>";
                }
                $output .= "</p>";
            }

            if ($objEvent->location != '')
            {
                $output .= "<p>";
                $output .= "<b>Kursort</b><br>";
                $output .= static::format($objEvent->location);
                $output .= "</p>";
            }

            if ($objEvent->meetingPoint != '')
            {
                $output .= "<p>";
                $output .= "<b>Zeit und Treffpunkt</b><br>";
                $output .= static::format($objEvent->meetingPoint);
                $output .= "</p>";
            }

            if (count(StringUtil::deserialize($objEvent->instructor, true)) > 0)
            {
                $arrLeiter = StringUtil::deserialize($objEvent->instructor, true);
                $arrName = array_map(function ($id) {
                    $strQuali = CalendarSacEvents::getMainQualifikation($id) != '' ? ' (' . CalendarSacEvents::getMainQualifikation($id) . ')' : '';
                    return UserModel::findByPk($id)->name . $strQuali;
                }, $arrLeiter);

                $output .= "<p>";
                $output .= "<b>Kursleitung</b><br>";
                $output .= static::format(implode(', ', $arrName));
                $output .= "</p>";
            }

            if ($objEvent->equipment != '')
            {
                $output .= "<p>";
                $output .= "<b>Mitbringen</b><br>";
                $output .= static::format($objEvent->equipment);
                $output .= "</p>";
            }

            if ($objEvent->minMembers > 0)
            {
                $output .= "<p>";
                $output .= "<b>Minimale Teilnehmerzahl</b><br>";
                $output .= static::format($objEvent->minMembers) . ' Personen';
                $output .= "</p>";
            }

            if ($objEvent->maxMembers > 0)
            {
                $output .= "<p>";
                $output .= "<b>Maximale Teilnehmerzahl</b><br>";
                $output .= static::format($objEvent->maxMembers) . ' Personen';
                $output .= "</p>";
            }

            if ($objEvent->leistungen != '')
            {
                $output .= "<p>";
                $output .= "<b>Preis/Leistungen</b><br>";
                $output .= static::format($objEvent->leistungen);
                $output .= "</p>";
            }

            if ($objEvent->bookingEvent != '')
            {
                $output .= "<p>";
                $output .= "<b>Anmeldung</b><br>";
                $output .= static::format($objEvent->bookingEvent);
                $output .= "</p>";
            }

            if ($objEvent->miscelaneous != '')
            {
                $output .= "<p>";
                $output .= "<b>Sonstiges</b><br>";
                $output .= static::format($objEvent->miscelaneous);
                $output .= "</p>";
            }


            header('Content-type: text/plain');
            header('Content-Disposition: attachment; filename="[' . Date::parse('Y-m-d', $objEvent->startDate) . '] ' . $objEvent->title . '.txt"');
            if (Input::get('test'))
            {
                $output = str_replace("<br></p>", "", $output);

                $output = str_replace("<br>", "\r\n", $output);
                $output = str_replace("</br>", "\r\n", $output);

                $output = str_replace("\n", "\r\n", $output);

                $output = str_replace('</b><br>', "\r\n", $output);
                $output = str_replace('<b>', "\r\n\r\n", $output);

                echo strip_tags($output);
                exit();
            }
            echo $output;
        }
        exit();

    }

    /**
     * @param $field
     * @return string
     */
    protected static function format($field)
    {
        if (Input::get('test'))
        {
            return $field;
        }
        $field = nl2br($field);
        return $field;
    }

}