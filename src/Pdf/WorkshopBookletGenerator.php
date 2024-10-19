<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Pdf;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\FrontendTemplate;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Config\CourseLevels;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\SacEventToolBundle\Model\CourseMainTypeModel;
use Markocupic\SacEventToolBundle\Model\CourseSubTypeModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Safe\DateTime;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class WorkshopBookletGenerator
{
    private WorkshopTCPDF|null $pdf;
    private int|null $year;
    private int|null $eventId = null;
    private bool $download = false;
    private bool $printSingleEvent = false;

    public function __construct(
        private readonly CourseLevels $courseLevels,
        private readonly BinaryFileDownload $binaryFileDownload,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly string $projectDir,
        private readonly string $sacevtEventCourseBookletFilenamePattern,
        private readonly string $sacevtTempDir,
    ) {
        $this->framework->initialize(true);

        // Set defaults
        $this->year = (int) date('Y');
    }

    /**
     * @throws \Exception
     *
     * @return $this
     */
    public function setYear(int $year): self
    {
        if (!checkdate(1, 1, $year)) {
            throw new \Exception(sprintf('%s is not a valid year number. Please use a valid year number f.ex. "2020" as first parameter.', Date::parse('Y', strtotime((string) $year))));
        }
        $this->year = $year;

        return $this;
    }

    /**
     * @throws \Exception
     *
     * @return $this
     */
    public function setEventId(int $eventId): self
    {
        if (null === CalendarEventsModel::findByPk($eventId)) {
            throw new \Exception('Please use a valid event id as first parameter.');
        }

        $this->eventId = $eventId;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDownload(bool $download): self
    {
        $this->download = $download;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function generate(): BinaryFileResponse
    {
        $addToc = true;
        $addCover = true;

        // Print single event
        if (null !== CalendarEventsModel::findByPk($this->eventId)) {
            $this->printSingleEvent = true;
            $addToc = false;
            $addCover = false;
        }

        // Get the font directory
        $bundleSRC = System::getContainer()->get('kernel')->locateResource('@MarkocupicSacEventToolBundle');
        $fontDirectory = $bundleSRC.'src/Pdf/fonts/opensans';

        // Create new PDF document
        // Extend TCPDF for special footer and header handling
        $this->pdf = new WorkshopTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        \TCPDF_FONTS::addTTFfont($fontDirectory.'/OpenSans-Light.ttf', 'TrueTypeUnicode', '', 96);
        \TCPDF_FONTS::addTTFfont($fontDirectory.'/OpenSans-Bold.ttf', 'TrueTypeUnicode', '', 96);
        \TCPDF_FONTS::addTTFfont($fontDirectory.'/OpenSans-LightItalic.ttf', 'TrueTypeUnicode', '', 96);

        //$this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        // Set margins
        $this->pdf->SetMargins(20, 20, 20);
        $this->pdf->SetHeaderMargin(0);

        // Set auto page breaks false
        $this->pdf->SetAutoPageBreak(false);

        if ($addCover) {
            // Cover (first page)
            $this->pdf->type = 'cover';
            $this->pdf->AddPage('P', 'A4');

            $this->pdf->SetY(60);
            $this->pdf->SetFont('opensans', 'B', 30);
            $this->pdf->SetTextColor(255, 255, 255);
            $this->pdf->MultiCell(0, 0, 'SAC Sektion Pilatus', 0, 'R', 0, 1, '', '', true);

            $this->pdf->SetY(120);
            $this->pdf->SetFont('opensans', 'B', 45);
            $this->pdf->SetTextColor(255, 255, 255);
            $this->pdf->MultiCell(0, 0, 'Kursprogramm '.$this->year, 0, 'R', 0, 1, '', '', true);

            $this->pdf->Ln();
            $this->pdf->SetY(270);
            $this->pdf->SetFont('opensanslight', '', 8);
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->MultiCell(0, 0, 'Ausgabe vom '.Date::parse('d.m.Y H:i', time()), 0, 'R', 0, 1, '', '', true);
        }

        // Event items
        $this->pdf->SetFont('opensanslight', '', 12);
        $this->pdf->SetTextColor(0, 0, 0);

        if ($this->eventId) {
            $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events WHERE id = ? AND published = ?', [$this->eventId, 1]);
        } else {
            $year = $this->year;
            $start = (new DateTime($year.'-01-01'))->getTimestamp();
            $stop = (new DateTime($year + 1 .'-01-01'))->getTimestamp();

            $stmt = $this->connection->executeQuery(
                'SELECT * FROM tl_calendar_events WHERE eventType = ? AND startTime >= ? AND endTime < ? AND published = ? ORDER BY courseTypeLevel0, title, startDate',
                [EventType::COURSE, $start, $stop, '1']
            );
        }

        while (false !== ($row = $stmt->fetchAssociative())) {
            // Create a page for each event
            $this->pdf->type = 'eventPage';
            $this->pdf->objEvent = CalendarEventsModel::findByPk($row['id']);
            $this->pdf->AddPage('P', 'A4');
            $html = $this->generateHtmlContent();
            $this->pdf->writeHTML($html);
        }

        if ($addToc) {
            $this->pdf->type = 'TOC';
            $this->pdf->addTOCPage('P', 'A4', 20);

            // Write the TOC title
            $this->pdf->SetFont('opensanslight', 'B', 16);
            $this->pdf->SetTextColor(100, 0, 0);
            $this->pdf->MultiCell(0, 0, 'Inhaltsverzeichnis', 0, 'L', 0, 1, '', '', true);
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->Ln();

            $this->pdf->SetFont('opensanslight', '', 11);

            // Add a simple Table Of Content at first page
            // (check the example n. 59 for the HTML version)
            $this->pdf->addTOC(2, 'opensanslight', '.', 'INDEX', 'B', [255, 255, 255]);

            // end of TOC page
            $this->pdf->endTOCPage();
        }

        // Kursprogramm_%s.pdf
        $filename = sprintf($this->sacevtEventCourseBookletFilenamePattern, $this->year);
        $path = $this->projectDir.'/'.$this->sacevtTempDir.'/'.$filename;

        if (false === $this->download) {
            $this->pdf->Output($path, 'F');

            throw new ResponseException(new Response(''));
        }

        if ($this->printSingleEvent) {
            $eventAlias = CalendarEventsModel::findByPk($this->eventId)->alias;
            $path = \dirname($path).'/'.$eventAlias.'.pdf';

            $this->pdf->setTitle(basename($path));

            // Save as file
            $this->pdf->Output($path, 'F');

            // Send file to the browser
            return $this->binaryFileDownload->sendFileToBrowser($path, basename($path), false, false);
        }

        $this->pdf->setTitle(basename($path));

        // Save as file
        $this->pdf->Output($path, 'F');

        // Send file to the browser
        return $this->binaryFileDownload->sendFileToBrowser($path, basename($path), false, true);
    }

    public function getDateString(int $eventId): string
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);
        $strDates = '';

        if (null !== $objEvent) {
            $arr = CalendarEventsUtil::getEventTimestamps($objEvent);

            if (!empty($arr)) {
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
            }
        }

        return $strDates;
    }

    private function nl2br(string|null $string = ''): string
    {
        if (null === $string) {
            return '';
        }

        return nl2br($string);
    }

    private function generateHtmlContent(): string
    {
        System::loadLanguageFile('tl_calendar_events');
        $objEvent = CalendarEventsModel::findByPk($this->pdf->objEvent->id);
        $this->pdf->Bookmark(html_entity_decode($objEvent->title), 0, 0, '', 'I', [0, 0, 0]);

        // Create template object
        $objPartial = new FrontendTemplate('tcpdf_template_sac_kurse');

        // Title
        $objPartial->title = $objEvent->title;

        // Dates
        $objPartial->date = $this->getDateString((int) $objEvent->id);

        // Duration
        $objPartial->durationInfo = $objEvent->durationInfo;

        // Course type
        $objPartial->courseTypeLevel0 = CourseMainTypeModel::findByPk($objEvent->courseTypeLevel0)->name;
        $objPartial->courseTypeLevel1 = CourseSubTypeModel::findByPk($objEvent->courseTypeLevel1)->name;

        // Course level
        $objPartial->courseLevel = $this->courseLevels->get($objEvent->courseLevel);

        // Organizers
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

        // Teaser text
        $objPartial->teaser = $this->nl2br($objEvent->teaser);

        // Event terms
        $objPartial->terms = $this->nl2br($objEvent->terms);

        // Event Issues
        $objPartial->issues = $this->nl2br($objEvent->issues);

        // Requirements
        $objPartial->requirements = $this->nl2br($objEvent->requirements);

        // Event location
        $objPartial->location = $this->nl2br($objEvent->location);

        // Instructors
        $arrInstructors = CalendarEventsUtil::getInstructorsAsArray($objEvent);
        $arrItems = array_map(
            static function ($userId) {
                $objUser = UserModel::findByPk($userId);

                if (null !== $objUser) {
                    $strQuali = '' !== CalendarEventsUtil::getMainQualification($objUser) ? ' ('.CalendarEventsUtil::getMainQualification($objUser).')' : '';

                    return $objUser->name.$strQuali;
                }

                return '';
            },
            $arrInstructors
        );

        // Instructors
        $objPartial->instructor = implode(', ', $arrItems);

        // Services/Leistungen
        $objPartial->leistungen = $this->nl2br($objEvent->leistungen);

        // Sign in
        $objPartial->bookingEvent = str_replace('(at)', '@', html_entity_decode($this->nl2br((string) $objEvent->bookingEvent)));

        // Equipment
        $objPartial->equipment = $this->nl2br($objEvent->equipment);

        // Meeting point
        $objPartial->meetingPoint = $this->nl2br($objEvent->meetingPoint);

        // Miscellaneous
        $objPartial->miscellaneous = $this->nl2br($objEvent->miscellaneous);

        // Styles
        $objPartial->titleStyle = "color:#000000; font-family: 'opensansbold'; font-size: 20px";
        $objPartial->rowStyleA = 'background-color: #ffffff';
        $objPartial->rowStyleB = 'background-color: #ffffff';

        $objPartial->cellStyleA = "font-family: 'opensansbold';  width:40mm; font-weight:bold; font-size: 9px";
        $objPartial->cellStyleB = "font-family: 'opensanslight'; width:130mm; font-size: 9px";

        // Teaser
        $objPartial->cellStyleC = "color: #000000; font-family: 'opensansbold'; font-size: 9px";

        return $objPartial->parse();
    }
}
