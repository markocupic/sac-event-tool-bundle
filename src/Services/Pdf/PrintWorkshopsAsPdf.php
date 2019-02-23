<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */


namespace Markocupic\SacEventToolBundle\Services\Pdf;

use Contao\CalendarEventsModel;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;
use Contao\Date;
use Contao\EventOrganizerModel;
use Contao\FrontendTemplate;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Config;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use TCPDF_FONTS;

/**
 * Class PrintWorkshopsAsPdf
 * @package Markocupic\SacEventToolBundle\Services\Pdf
 */
class PrintWorkshopsAsPdf
{

    /**
     * @var
     */
    public $pdf;

    /**
     * @var null
     */
    protected $year = null;

    /**
     * @var null
     */
    protected $calendarId = null;

    /**
     * @var null
     */
    protected $eventId = null;

    /**
     * @var bool
     */
    protected $addToc = false;

    /**
     * @var bool
     */
    protected $addCover = false;

    /**
     * @var bool
     */
    protected $printSingleEvent = false;

    /**
     * PrintWorkshopsAsPdf constructor.
     * @param null $year
     * @param null $calendarId
     * @param null $eventId
     * @param bool $download
     */
    public function __construct($year = null, $calendarId = null, $eventId = null, $download = true)
    {
        $this->download = $download;
        if ($year > 2016 && $calendarId > 0)
        {
            $this->calendarId = $calendarId;
            $this->year = $year;
        }
        elseif ($eventId > 0)
        {

            $this->calendarId = null;
            $this->eventId = $eventId;
        }
        else
        {
            new \Exception('Please add more parameters.');
        }


        if ($this->year === null)
        {
            $this->year = Date::parse('Y');
        }
    }


    /**
     * Launch method cia CronJob (Geplante Aufgaben on Plesk)
     */
    public function printWorkshopsAsPdf()
    {

        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');


        $this->addToc = true;
        $this->addCover = true;

        // Print single event
        if ($this->eventId)
        {
            $this->printSingleEvent = true;
            $this->addToc = false;
            $this->addCover = false;
            $this->calendarId = CalendarEventsModel::findByPk($this->eventId)->pid;
        }

        // Get the font directory
        $container = System::getContainer();
        $bundleSRC = $container->get('kernel')->locateResource('@MarkocupicSacEventToolBundle');
        $fontDirectory = $bundleSRC . 'Resources/contao/fonts/opensans';

        // create new PDF document
        // Extend TCPDF for special footer and header handling
        $this->pdf = new WorkshopTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        TCPDF_FONTS::addTTFfont($fontDirectory . '/OpenSans-Light.ttf', 'TrueTypeUnicode', '', 96);
        TCPDF_FONTS::addTTFfont($fontDirectory . '/OpenSans-Bold.ttf', 'TrueTypeUnicode', '', 96);
        TCPDF_FONTS::addTTFfont($fontDirectory . '/OpenSans-LightItalic.ttf', 'TrueTypeUnicode', '', 96);

        //$this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        // set margins
        $this->pdf->SetMargins(20, 20, 20);
        $this->pdf->SetHeaderMargin(0);


        // set auto page breaks false
        $this->pdf->SetAutoPageBreak(false, 0);

        if ($this->addCover)
        {
            // Cover (first page)
            $this->pdf->type = 'cover';
            $this->pdf->AddPage('P', 'A4');

            $this->pdf->SetY(60);
            $this->pdf->SetFont('opensans', 'B', 30);
            $this->pdf->SetTextColor(255, 255, 255);
            $this->pdf->MultiCell(0, 0, 'SAC Sektion Pilatus', 0, 'R', 0, 1, '', '', true, 0);

            $this->pdf->SetY(120);
            $this->pdf->SetFont('opensans', 'B', 45);
            $this->pdf->SetTextColor(255, 255, 255);
            $this->pdf->MultiCell(0, 0, 'Kursprogramm ' . $this->year, 0, 'R', 0, 1, '', '', true, 0);


            $this->pdf->Ln();
            $this->pdf->SetY(270);
            $this->pdf->SetFont('opensanslight', '', 8);
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->MultiCell(0, 0, 'Ausgabe vom ' . Date::parse('d.m.Y H:i', time()), 0, 'R', 0, 1, '', '', true, 0);
        }

        // Event items
        $this->pdf->SetFont('opensanslight', '', 12);
        $this->pdf->SetTextColor(0, 0, 0);
        $objDb = Database::getInstance();
        if ($this->eventId)
        {
            $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE id=? AND published=?')->execute($this->eventId, 1);
        }
        else
        {
            $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE pid=? AND published=? ORDER BY courseTypeLevel0, title, startDate')->execute($this->calendarId, 1);
        }


        while ($objEvent->next())
        {
            // Create a page for each event
            $this->pdf->type = 'eventPage';
            $this->pdf->Event = $objEvent;
            $this->pdf->AddPage('P', 'A4');
            $html = $this->generateHtmlContent();
            $this->pdf->writeHTML($html);
        }

        if ($this->addToc)
        {
            $this->pdf->type = 'TOC';
            $this->pdf->addTOCPage('P', 'A4', 20);

            // write the TOC title
            $this->pdf->SetFont('opensanslight', 'B', 16);
            $this->pdf->SetTextColor(100, 0, 0);
            $this->pdf->MultiCell(0, 0, 'Inhaltsverzeichnis', 0, 'L', 0, 1, '', '', true, 0);
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->Ln();

            $this->pdf->SetFont('opensanslight', '', 11);

            // add a simple Table Of Content at first page
            // (check the example n. 59 for the HTML version)
            $this->pdf->addTOC(2, 'opensanslight', '.', 'INDEX', 'B', array(255, 255, 255));

            // end of TOC page
            $this->pdf->endTOCPage();
        }

        $container = System::getContainer();
        $filenamePattern = str_replace('%%s', '%s', Config::get('SAC_EVT_WORKSHOP_FLYER_SRC'));
        $fileSRC = sprintf($filenamePattern, $this->year);

        if ($this->download === false)
        {
            // Close and output PDF document
            if (file_exists($rootDir . '/' . $fileSRC))
            {
                unlink($rootDir . '/' . $fileSRC);
            }
            sleep(1);
            $this->pdf->Output($rootDir . '/' . $fileSRC, 'F');
        }
        else
        {
            // Send File to Browser
            if ($this->printSingleEvent)
            {
                $eventAlias = CalendarEventsModel::findByPk($this->eventId)->alias;
                $this->pdf->Output($eventAlias . '.pdf', 'D');
            }
            else
            {
                $filename = basename($fileSRC);
                $this->pdf->Output($filename, 'D');
            }
        }
    }

    /**
     * @return string
     */
    private function generateHtmlContent()
    {
        System::loadLanguageFile('tl_calendar_events');
        $objCalendar = CalendarEventsModel::findByPk($this->pdf->Event->id);
        $this->pdf->Bookmark(html_entity_decode($objCalendar->title), 0, 0, '', 'I', array(0, 0, 0));


        // Create template object
        $objPartial = new FrontendTemplate('tcpdf_template_sac_kurse');

        // Title
        $objPartial->title = $objCalendar->title;

        // Dates
        $objPartial->date = $this->getDateString($objCalendar->id);

        // Duration
        $objPartial->durationInfo = $objCalendar->durationInfo;

        // Course type
        $objPartial->courseTypeLevel0 = CourseMainTypeModel::findByPk($objCalendar->courseTypeLevel0)->name;
        $objPartial->courseTypeLevel1 = CourseSubTypeModel::findByPk($objCalendar->courseTypeLevel1)->name;

        // Course level
        $objPartial->courseLevel = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objCalendar->courseLevel];

        // organisierende Gruppen
        $arrItems = array_map(function ($item) {
            $objOrganizer = EventOrganizerModel::findByPk($item);
            if ($objOrganizer !== null)
            {
                $item = $objOrganizer->title;
            }
            return $item;
        }, StringUtil::deserialize($objCalendar->organizers, true));
        $objPartial->organizers = implode(', ', $arrItems);


        // Teasertext
        $objPartial->teaser = nl2br($objCalendar->teaser);

        // Event terms
        $objPartial->terms = nl2br($objCalendar->terms);

        // Event Issues
        $objPartial->issues = nl2br($objCalendar->issues);

        // Requirements
        $objPartial->requirements = nl2br($objCalendar->requirements);

        // Event location
        $objPartial->location = nl2br($objCalendar->location);

        // Mountainguide
        $objPartial->mountainguide = $objCalendar->mountainguide;

        // Instructors
        $arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objCalendar->id);
        $arrItems = array_map(function ($userId) {
            $objUser = UserModel::findByPk($userId);
            if ($objUser !== null)
            {
                $strQuali = CalendarEventsHelper::getMainQualifikation($userId) != '' ? ' (' . CalendarEventsHelper::getMainQualifikation($userId) . ')' : '';
                return $objUser->name . $strQuali;
            }
            return '';
        }, $arrInstructors);
        $objPartial->instructor = implode(', ', $arrItems);


        // Services/Leistungen
        $objPartial->leistungen = nl2br($objCalendar->leistungen);

        // Signin
        $objPartial->bookingEvent = str_replace('(at)', '@', html_entity_decode(nl2br($objCalendar->bookingEvent)));

        // Equipment
        $objPartial->equipment = nl2br($objCalendar->equipment);

        // Meeting point
        $objPartial->meetingPoint = nl2br($objCalendar->meetingPoint);

        // Miscelaneous
        $objPartial->miscellaneous = nl2br($objCalendar->miscellaneous);

        // Styles
        $objPartial->titleStyle = "color:#000000; font-family: 'opensansbold'; font-size: 20px";
        $objPartial->rowStyleA = "background-color: #ffffff";
        $objPartial->rowStyleB = "background-color: #ffffff";

        $objPartial->cellStyleA = "font-family: 'opensansbold';  width:40mm; font-weight:bold; font-size: 9px";
        $objPartial->cellStyleB = "font-family: 'opensanslight'; width:130mm; font-size: 9px";
        //$objPartial->cellStyleANoBorder = "color: #000000; font-family: 'opensansbold'; font-weight:bold; width:40mm; font-size: 10px";
        //$objPartial->cellStyleBNoBorder = "color: #000000; font-family: 'opensanslight'; width:130mm; font-size: 10px";

        // Teaser
        $objPartial->cellStyleC = "color: #000000; font-family: 'opensansbold'; font-size: 9px";

        return $objPartial->parse();
    }

    /**
     * @param $eventId
     * @return mixed|string
     */
    public function getDateString($eventId)
    {

        $arr = CalendarEventsHelper::getEventTimestamps($eventId);
        if (count($arr) > 1)
        {
            $arrValue = array();
            foreach ($arr as $k => $v)
            {
                if ($k == count($arr) - 1)
                {
                    $arrValue[] = 'und ' . Date::parse('d.m.Y', $v);
                }
                else
                {
                    $arrValue[] = Date::parse('d.m.', $v);
                }
            }
            $strDates = implode(', ', $arrValue);
            $strDates = str_replace(', und ', ' und ', $strDates);
        }
        else
        {
            $strDates = Date::parse('d.m.Y', $arr[0]);
        }
        return $strDates;
    }

}