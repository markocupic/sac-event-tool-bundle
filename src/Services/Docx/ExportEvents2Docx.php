<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\Services\Docx;

use Contao\Environment;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Contao\Controller;
use Contao\Database;
use Contao\CalendarEventsModel;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\UserModel;
use Contao\StringUtil;
use Contao\Date;
use Contao\EventOrganizerModel;
use Contao\Folder;
use Contao\System;
use Contao\Config;


/**
 * Class ExportEvents2Docx
 * @package Markocupic\SacEventToolBundle
 */
class ExportEvents2Docx
{

    /**
     * @var
     */
    public static $strTable;
    /**
     * @var
     */
    public static $dca;

    /**
     * @var
     */
    public static $arrDatarecord;


    /**
     * @param $calendarId
     * @param $year
     * @param null $eventId
     */
    public static function sendToBrowser($calendarId, $year, $eventId = null): void
    {

        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        self::$strTable = 'tl_calendar_events';
        Controller::loadDataContainer('tl_calendar_events');
        self::$dca = $GLOBALS['TL_DCA'][self::$strTable];


        // Creating the new document...
        // Tutorial http://phpword.readthedocs.io/en/latest/elements.html#titles
        $phpWord = new PhpWord();


        // Styles
        $fStyleTitle = array('color' => '000000', 'size' => 16, 'bold' => true, 'name' => 'Century Gothic');

        $fStyle = array('color' => '000000', 'size' => 10, 'bold' => false, 'name' => 'Century Gothic');
        $phpWord->addFontStyle('fStyle', $fStyle);

        $fStyleSmall = array('color' => '000000', 'size' => 9, 'bold' => false, 'name' => 'Century Gothic');
        $phpWord->addFontStyle('fStyleSmall', $fStyleSmall);

        $fStyleMediumRed = array('color' => 'ff0000', 'size' => 12, 'bold' => true, 'name' => 'Century Gothic');
        $phpWord->addFontStyle('fStyleMediumRed', $fStyleMediumRed);

        $fStyleBold = array('color' => '000000', 'size' => 10, 'bold' => true, 'name' => 'Century Gothic');
        $phpWord->addFontStyle('fStyleBold', $fStyleBold);

        $pStyle = array('lineHeight' => '1.0', 'spaceBefore' => 0, 'spaceAfter' => 0);
        $phpWord->addParagraphStyle('pStyle', $pStyle);

        $tableStyle = array(
            'borderColor' => '000000',
            'borderSize'  => 6,
            'cellMargin'  => 50,
        );
        $twip = 56.6928; // 1mm = 56.6928 twip
        $widthCol_1 = round(45 * $twip);
        $widthCol_2 = round(115 * $twip);

        $objEvent = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE pid=? AND published=? ORDER BY courseTypeLevel0, title, startDate')->execute($calendarId, 1);
        while ($objEvent->next())
        {

            if ($eventId > 0)
            {
                if ($eventId != $objEvent->id)
                {
                    continue;
                }
            }

            self::$arrDatarecord = CalendarEventsModel::findByPk($objEvent->id)->row();

            // Adding an empty Section to the document...
            $section = $phpWord->addSection();


            // Add page header
            $header = $section->addHeader();
            $header->firstPage();
            $table = $header->addTable();
            $table->addRow();
            $cell = $table->addCell(4500);
            $textrun = $cell->addTextRun();
            $textrun->addLink(Environment::get('host') . '/', htmlspecialchars('KURSPROGRAMM ' . $year, ENT_COMPAT, 'UTF-8'), $fStyleMediumRed);
            $table->addCell(4500)->addImage($rootDir . '/files/fileadmin/page_assets/kursbroschuere/logo-sac-pilatus.png', array('height' => 40, 'align' => 'right'));

            // Add footer
            //$footer = $section->addFooter();
            //$footer->addPreserveText(htmlspecialchars('Page {PAGE} of {NUMPAGES}.', ENT_COMPAT, 'UTF-8'), null, null);
            //$footer->addLink('https://github.com/PHPOffice/PHPWord', htmlspecialchars('PHPWord on GitHub', ENT_COMPAT, 'UTF-8'));


            // Add the title
            $title = htmlspecialchars(self::formatValue('title', $objEvent->title, $objEvent));
            $phpWord->addTitleStyle(1, $fStyleTitle, null);
            $section->addTitle(htmlspecialchars($title, ENT_COMPAT, 'UTF-8'), 1);

            // Add the table
            //$firstRowStyle = array('bgColor' => '66BBFF');
            $firstRowStyle = array();
            $phpWord->addTableStyle('Event-Item', $tableStyle, $firstRowStyle);
            $table = $section->addTable('Event-Item');

            $arrFields = array(
                "Datum"                 => 'eventDates',
                "Autor (-en)"           => 'author',
                "Kursart"               => 'kursart',
                "Kursstufe"             => 'courseLevel',
                "Organisierende Gruppe" => 'organizers',
                "Einführungstext"       => 'teaser',
                "Kursziele"             => 'terms',
                "Kursinhalte"           => 'issues',
                "Voraussetzungen"       => 'requirements',
                "Bergf./Tourenl."       => 'mountainguide',
                "Leiter"                => 'instructor',
                "Preis/Leistungen"      => 'leistungen',
                "Anmeldung"             => 'bookingEvent',
                "Material"              => 'equipment',
                "Weiteres"              => 'miscellaneous',
            );

            foreach ($arrFields as $label => $fieldname)
            {
                $table->addRow();
                $table->addCell($widthCol_1)->addText(htmlspecialchars($label . ":"), 'fStyleBold', 'pStyle');
                $objCell = $table->addCell($widthCol_2);
                $value = self::formatValue($fieldname, $objEvent->{$fieldname}, $objEvent);
                // Add multiline text
                self::addMultilineText($objCell, $value);
            }

            $section->addText('event-alias: ' . $objEvent->alias, 'fStyleSmall', 'pStyle');
            $section->addText('event-id: ' . $objEvent->id, 'fStyleSmall', 'pStyle');
            $section->addText('version-date: ' . Date::parse('Y-m-d'), 'fStyleSmall', 'pStyle');

            $section->addPageBreak();

        }
        // Saving the document as OOXML file...
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        new Folder(Config::get('SAC_EVT_TEMP_PATH'));
        $objWriter->save($rootDir . '/' . Config::get('SAC_EVT_TEMP_PATH') . '/sac-jahresprogramm.docx');
        sleep(1);

        $fileSRC = Config::get('SAC_EVT_TEMP_PATH') . '/sac-jahresprogramm.docx';
        Controller::sendFileToBrowser($fileSRC, false);
    }

    /**
     * @param $objCell
     * @param $textlines
     */
    public static function addMultilineText($objCell, $textlines): void
    {

        foreach (explode("\n", $textlines) as $line)
        {
            $objCell->addText(htmlspecialchars($line), 'fStyle', 'pStyle');
        }
    }

    /**
     * @param $field
     * @param string $value
     * @param $objEvent
     * @return string
     */
    public static function formatValue($field, $value = '', $objEvent): string
    {
        $table = self::$strTable;
        $dataRecord = self::$arrDatarecord;
        $dca = self::$dca;

        if ($table == 'tl_calendar_events')
        {
            if ($field == 'courseLevel')
            {
                if ($value != '')
                {
                    $value = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$value];
                }
            }

            if ($field == 'kursart')
            {
                $levelMain = $objEvent->courseTypeLevel0;
                $levelSub = $objEvent->courseTypeLevel1;
                $strSub = '';
                $strMain = '';
                $objMain = CourseMainTypeModel::findByPk($levelMain);
                if ($objMain !== null)
                {
                    $strMain = $objMain->name;
                }
                $objSub = CourseSubTypeModel::findByPk($levelSub);
                if ($objSub !== null)
                {
                    $strSub = $objSub->code . ' - ' . $objSub->name;
                }
                $value = $strMain . ': ' . $strSub;

            }

            if ($field == 'author')
            {
                $value = StringUtil::deserialize($value, true);
                if (is_array(StringUtil::deserialize($value)) && !empty($value))
                {
                    $arrValue = array_map(function ($v) {
                        return UserModel::findByPk(intval($v))->name;
                    }, StringUtil::deserialize($value));
                    $value = implode(', ', $arrValue);
                }
            }

            if ($field == 'instructor')
            {
                $arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvent->id);
                $arrValue = array_map(function ($v) {
                    return UserModel::findByPk($v)->name;
                }, $arrInstructors);
                $value = implode(', ', $arrValue);
            }

            if ($field == 'organizers')
            {
                $value = StringUtil::deserialize($value, true);
                if (is_array(StringUtil::deserialize($value)) && !empty($value))
                {
                    $arrValue = array_map(function ($v) {
                        $objOrganizer = EventOrganizerModel::findByPk($v);
                        if ($objOrganizer !== null)
                        {
                            $v = $objOrganizer->title;
                        }
                        return $v;
                    }, StringUtil::deserialize($value));
                    $value = implode(', ', $arrValue);
                }
            }


            if ($field == 'startDate' || $field == 'endDate' || $field == 'tstamp')
            {
                if ($value > 0)
                {
                    $value = Date::parse('d.m.Y', $value);
                }
            }

            // Kusdatendaten in der Form d.m.Y, d.m.Y, ...
            if ($field == 'eventDates')
            {

                $arr = CalendarEventsHelper::getEventTimestamps(self::$arrDatarecord['id']);
                $arr = array_map(function ($tstamp) {
                    return Date::parse('d.m.Y', $tstamp);
                }, $arr);
                $value = implode(', ', $arr);
            }


            if ($field == 'mountainguide')
            {
                $value = ($value > 0) ? 'Mit Bergfuehrer' : 'Mit SAC-Kursleiter';
                $value = utf8_encode($value);
            }
            /*
            if ($field == 'issues')
            {
                $value = str_replace('</li>', '', $value);
                $value = str_replace('</ul>', '', $value);
                $value = str_replace('<ul>', '', $value);
                $value = str_replace('<li>', '•\t', $value);
                $value = str_replace('</p>', chr(13), $value);
                $value = strip_tags($value);
            }
            */

            $value = $value != '' ? html_entity_decode($value, ENT_QUOTES) : '';

        }

        return $value;

    }

}
