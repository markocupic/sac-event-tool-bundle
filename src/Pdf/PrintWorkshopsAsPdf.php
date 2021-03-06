<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Pdf;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;
use Contao\Date;
use Contao\EventOrganizerModel;
use Contao\FrontendTemplate;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use TCPDF_FONTS;

/**
 * Class PrintWorkshopsAsPdf.
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
    protected $year;

    /**
     * @var null
     */
    protected $calendarId;

    /**
     * @var null
     */
    protected $eventId;

    /**
     * @var PrintWorkshopsAsPdf
     */
    protected $download = false;

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
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
        $this->framework->initialize(true);

        // Set defaults
        $this->year = Config::get('SAC_EVT_WORKSHOP_FLYER_YEAR');
        $this->calendarId = Config::get('SAC_EVT_WORKSHOP_FLYER_CALENDAR_ID');
        $this->download = false;
    }

    /**
     * @throws \Exception
     *
     * @return PrintWorkshopsAsPdf
     */
    public function setYear(int $year): self
    {
        if (!checkdate(1, 1, $year)) {
            throw new \Exception(sprintf('%s is not a valid year number. Please use a valid year number f.ex. "2020" as first parameter.', Date::parse('Y', strtotime($year))));
        }
        $this->year = $year;

        return $this;
    }

    /**
     * @throws \Exception
     *
     * @return PrintWorkshopsAsPdf
     */
    public function setCalendarId(int $calendarId): self
    {
        $objCalendar = CalendarModel::findByPk($calendarId);

        if (null === $objCalendar) {
            throw new \Exception('Please use a valid calendar id as first parameter.');
        }
        $this->calendarId = $calendarId;

        return $this;
    }

    /**
     * @throws \Exception
     *
     * @return PrintWorkshopsAsPdf
     */
    public function setEventId(int $eventId): self
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);

        if (null === $objEvent) {
            throw new \Exception('Please use a valid event id as first parameter.');
        }
        $this->eventId = $objEvent->id;

        return $this;
    }

    /**
     * @return PrintWorkshopsAsPdf
     */
    public function setDownload(bool $download): self
    {
        $this->download = $download;

        return $this;
    }

    /**
     * Launch method via CronJob (Geplante Aufgaben on Plesk).
     */
    public function printWorkshopsAsPdf(): void
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $this->addToc = true;
        $this->addCover = true;

        // Print single event
        if (null !== ($objEvent = CalendarEventsModel::findByPk($this->eventId))) {
            $this->printSingleEvent = true;
            $this->addToc = false;
            $this->addCover = false;
            $this->calendarId = $objEvent->pid;
        }

        // Get the font directory
        $container = System::getContainer();
        $bundleSRC = $container->get('kernel')->locateResource('@MarkocupicSacEventToolBundle');
        $fontDirectory = $bundleSRC.'Pdf/fonts/opensans';

        // create new PDF document
        // Extend TCPDF for special footer and header handling
        $this->pdf = new WorkshopTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        TCPDF_FONTS::addTTFfont($fontDirectory.'/OpenSans-Light.ttf', 'TrueTypeUnicode', '', 96);
        TCPDF_FONTS::addTTFfont($fontDirectory.'/OpenSans-Bold.ttf', 'TrueTypeUnicode', '', 96);
        TCPDF_FONTS::addTTFfont($fontDirectory.'/OpenSans-LightItalic.ttf', 'TrueTypeUnicode', '', 96);

        //$this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        // set margins
        $this->pdf->SetMargins(20, 20, 20);
        $this->pdf->SetHeaderMargin(0);

        // set auto page breaks false
        $this->pdf->SetAutoPageBreak(false, 0);

        if ($this->addCover) {
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
            $this->pdf->MultiCell(0, 0, 'Kursprogramm '.$this->year, 0, 'R', 0, 1, '', '', true, 0);

            $this->pdf->Ln();
            $this->pdf->SetY(270);
            $this->pdf->SetFont('opensanslight', '', 8);
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->MultiCell(0, 0, 'Ausgabe vom '.Date::parse('d.m.Y H:i', time()), 0, 'R', 0, 1, '', '', true, 0);
        }

        // Event items
        $this->pdf->SetFont('opensanslight', '', 12);
        $this->pdf->SetTextColor(0, 0, 0);
        $objDb = Database::getInstance();

        if ($this->eventId) {
            $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE id=? AND published=?')->execute($this->eventId, 1);
        } else {
            $objEvent = $objDb->prepare('SELECT * FROM tl_calendar_events WHERE pid=? AND published=? ORDER BY courseTypeLevel0, title, startDate')->execute($this->calendarId, 1);
        }

        while ($objEvent->next()) {
            // Create a page for each event
            $this->pdf->type = 'eventPage';
            $this->pdf->Event = $objEvent;
            $this->pdf->AddPage('P', 'A4');
            $html = $this->generateHtmlContent();
            $this->pdf->writeHTML($html);
        }

        if ($this->addToc) {
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
            $this->pdf->addTOC(2, 'opensanslight', '.', 'INDEX', 'B', [255, 255, 255]);

            // end of TOC page
            $this->pdf->endTOCPage();
        }

        $filenamePattern = str_replace('%%s', '%s', Config::get('SAC_EVT_WORKSHOP_FLYER_SRC'));
        $fileSRC = sprintf($filenamePattern, $this->year);

        if (false === $this->download) {
            // Close and output PDF document
            if (file_exists($rootDir.'/'.$fileSRC)) {
                unlink($rootDir.'/'.$fileSRC);
            }
            sleep(1);
            $this->pdf->Output($rootDir.'/'.$fileSRC, 'F');
        } else {
            // Send File to Browser
            if ($this->printSingleEvent) {
                $eventAlias = CalendarEventsModel::findByPk($this->eventId)->alias;
                $this->pdf->Output($eventAlias.'.pdf', 'D');
            } else {
                $filename = basename($fileSRC);
                $this->pdf->Output($filename, 'D');
            }
        }
    }

    /**
     * @param $eventId
     *
     * @return mixed|string
     */
    public function getDateString($eventId)
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);
        $strDates = '';

        if (null !== $objEvent) {
            $arr = CalendarEventsHelper::getEventTimestamps($objEvent);

            if (false !== $arr) {
                if (\count($arr) > 1) {
                    $arrValue = [];

                    foreach ($arr as $k => $v) {
                        if ($k === \count($arr) - 1) {
                            $arrValue[] = 'und '.Date::parse('d.m.Y', $v);
                        } else {
                            $arrValue[] = Date::parse('d.m.', $v);
                        }
                    }
                    $strDates = implode(', ', $arrValue);
                    $strDates = str_replace(', und ', ' und ', $strDates);
                } else {
                    $strDates = Date::parse('d.m.Y', $arr[0]);
                }
            }
        }

        return $strDates;
    }

    /**
     * @param string $string
     */
    protected function nl2br($string = ''): string
    {
        if (null === $string) {
            return '';
        }

        return nl2br($string);
    }

    /**
     * @return string
     */
    private function generateHtmlContent()
    {
        System::loadLanguageFile('tl_calendar_events');
        $objEvent = CalendarEventsModel::findByPk($this->pdf->Event->id);
        $this->pdf->Bookmark(html_entity_decode((string) $objEvent->title), 0, 0, '', 'I', [0, 0, 0]);

        // Create template object
        $objPartial = new FrontendTemplate('tcpdf_template_sac_kurse');

        // Title
        $objPartial->title = $objEvent->title;

        // Dates
        $objPartial->date = $this->getDateString($objEvent->id);

        // Duration
        $objPartial->durationInfo = $objEvent->durationInfo;

        // Course type
        $objPartial->courseTypeLevel0 = CourseMainTypeModel::findByPk($objEvent->courseTypeLevel0)->name;
        $objPartial->courseTypeLevel1 = CourseSubTypeModel::findByPk($objEvent->courseTypeLevel1)->name;

        // Course level
        $objPartial->courseLevel = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel];

        // organisierende Gruppen
        $arrItems = array_map(
            static function ($item) {
                $objOrganizer = EventOrganizerModel::findByPk($item);

                if (null !== $objOrganizer) {
                    $item = $objOrganizer->title;
                }

                return $item;
            },
            StringUtil::deserialize($objEvent->organizers, true)
        );
        $objPartial->organizers = implode(', ', $arrItems);

        // Teasertext
        $objPartial->teaser = $this->nl2br($objEvent->teaser);

        // Event terms
        $objPartial->terms = $this->nl2br($objEvent->terms);

        // Event Issues
        $objPartial->issues = $this->nl2br($objEvent->issues);

        // Requirements
        $objPartial->requirements = $this->nl2br($objEvent->requirements);

        // Event location
        $objPartial->location = $this->nl2br($objEvent->location);

        // Mountainguide
        $objPartial->mountainguide = $objEvent->mountainguide;

        // Instructors
        $arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvent);
        $arrItems = array_map(
            static function ($userId) {
                $objUser = UserModel::findByPk($userId);

                if (null !== $objUser) {
                    $strQuali = '' !== CalendarEventsHelper::getMainQualification($objUser) ? ' ('.CalendarEventsHelper::getMainQualification($objUser).')' : '';

                    return $objUser->name.$strQuali;
                }

                return '';
            },
            $arrInstructors
        );
        $objPartial->instructor = implode(', ', $arrItems);

        // Services/Leistungen
        $objPartial->leistungen = $this->nl2br($objEvent->leistungen);

        // Signin
        $objPartial->bookingEvent = str_replace('(at)', '@', html_entity_decode($this->nl2br((string) $objEvent->bookingEvent)));

        // Equipment
        $objPartial->equipment = $this->nl2br($objEvent->equipment);

        // Meeting point
        $objPartial->meetingPoint = $this->nl2br($objEvent->meetingPoint);

        // Miscelaneous
        $objPartial->miscellaneous = $this->nl2br($objEvent->miscellaneous);

        // Styles
        $objPartial->titleStyle = "color:#000000; font-family: 'opensansbold'; font-size: 20px";
        $objPartial->rowStyleA = 'background-color: #ffffff';
        $objPartial->rowStyleB = 'background-color: #ffffff';

        $objPartial->cellStyleA = "font-family: 'opensansbold';  width:40mm; font-weight:bold; font-size: 9px";
        $objPartial->cellStyleB = "font-family: 'opensanslight'; width:130mm; font-size: 9px";
        //$objPartial->cellStyleANoBorder = "color: #000000; font-family: 'opensansbold'; font-weight:bold; width:40mm; font-size: 10px";
        //$objPartial->cellStyleBNoBorder = "color: #000000; font-family: 'opensanslight'; width:130mm; font-size: 10px";

        // Teaser
        $objPartial->cellStyleC = "color: #000000; font-family: 'opensansbold'; font-size: 9px";

        return $objPartial->parse();
    }
}
