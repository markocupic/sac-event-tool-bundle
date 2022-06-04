<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DocxTemplator;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Controller;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\EventOrganizerModel;
use Contao\Folder;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

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
     * @param $year
     * @param null $eventId
     *
     * @throws Exception
     */
    public static function generate(CalendarModel $objCalendar, $year, $eventId = null): void
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        // Get the temp dir
        $tempDir = System::getContainer()->getParameter('kernel.temp_dir');

        self::$strTable = 'tl_calendar_events';
        Controller::loadDataContainer('tl_calendar_events');
        self::$dca = $GLOBALS['TL_DCA'][self::$strTable];

        // Creating the new document...
        // Tutorial http://phpword.readthedocs.io/en/latest/elements.html#titles
        $phpWord = new PhpWord();

        // Styles
        $fStyleTitle = ['color' => '000000', 'size' => 16, 'bold' => true, 'name' => 'Century Gothic'];

        $fStyle = ['color' => '000000', 'size' => 10, 'bold' => false, 'name' => 'Century Gothic'];
        $phpWord->addFontStyle('fStyle', $fStyle);

        $fStyleSmall = ['color' => '000000', 'size' => 9, 'bold' => false, 'name' => 'Century Gothic'];
        $phpWord->addFontStyle('fStyleSmall', $fStyleSmall);

        $fStyleMediumRed = ['color' => 'ff0000', 'size' => 12, 'bold' => true, 'name' => 'Century Gothic'];
        $phpWord->addFontStyle('fStyleMediumRed', $fStyleMediumRed);

        $fStyleBold = ['color' => '000000', 'size' => 10, 'bold' => true, 'name' => 'Century Gothic'];
        $phpWord->addFontStyle('fStyleBold', $fStyleBold);

        $pStyle = ['lineHeight' => '1.0', 'spaceBefore' => 0, 'spaceAfter' => 0];
        $phpWord->addParagraphStyle('pStyle', $pStyle);

        $tableStyle = [
            'borderColor' => '000000',
            'borderSize' => 6,
            'cellMargin' => 50,
        ];
        $twip = 56.6928; // 1mm = 56.6928 twip
        $widthCol_1 = round(45 * $twip);
        $widthCol_2 = round(115 * $twip);

        $objEvent = CalendarEventsModel::findBy(
            ['tl_calendar_events.pid=?', 'tl_calendar_events.published=?'],
            [$objCalendar->id, '1'],
            ['order' => 'courseTypeLevel0, title, startDate']
        );

        if (null !== $objEvent) {
            while ($objEvent->next()) {
                if ($eventId > 0) {
                    if ($eventId !== $objEvent->id) {
                        continue;
                    }
                }

                self::$arrDatarecord = $objEvent->row();

                // Adding an empty Section to the document...
                $section = $phpWord->addSection();

                // Add page header
                $header = $section->addHeader();
                $header->firstPage();
                $table = $header->addTable();
                $table->addRow();
                $cell = $table->addCell(4500);
                $textrun = $cell->addTextRun();
                $textrun->addLink(Environment::get('host').'/', htmlspecialchars('KURSPROGRAMM '.$year, ENT_COMPAT, 'UTF-8'), $fStyleMediumRed);
                $table->addCell(4500)->addImage($rootDir.'/files/fileadmin/page_assets/kursbroschuere/logo-sac-pilatus.png', ['height' => 40, 'align' => 'right']);

                // Add footer
                //$footer = $section->addFooter();
                //$footer->addPreserveText(htmlspecialchars('Page {PAGE} of {NUMPAGES}.', ENT_COMPAT, 'UTF-8'), null, null);
                //$footer->addLink('https://github.com/PHPOffice/PHPWord', htmlspecialchars('PHPWord on GitHub', ENT_COMPAT, 'UTF-8'));

                // Add the title
                $title = htmlspecialchars(self::formatValue('title', $objEvent->title, $objEvent->current()));
                $phpWord->addTitleStyle(1, $fStyleTitle, null);
                $section->addTitle(htmlspecialchars($title, ENT_COMPAT, 'UTF-8'), 1);

                // Add the table
                //$firstRowStyle = array('bgColor' => '66BBFF');
                $firstRowStyle = [];
                $phpWord->addTableStyle('Event-Item', $tableStyle, $firstRowStyle);
                $table = $section->addTable('Event-Item');

                $arrFields = [
                    'Datum' => 'eventDates',
                    'Autor (-en)' => 'author',
                    'Kurshauptkat.' => 'courseTypeLevel0',
                    'Kursunterkat.' => 'courseTypeLevel1',
                    'Kursstufe' => 'courseLevel',
                    'Organisierende Gruppe' => 'organizers',
                    'Einführungstext' => 'teaser',
                    'Kursziele' => 'terms',
                    'Kursinhalte' => 'issues',
                    'Voraussetzungen' => 'requirements',
                    'Bergf./Tourenl.' => 'mountainguide',
                    'Leiter' => 'instructor',
                    'Preis/Leistungen' => 'leistungen',
                    'Anmeldung' => 'bookingEvent',
                    'Material' => 'equipment',
                    'Weiteres' => 'miscellaneous',
                ];

                foreach ($arrFields as $label => $fieldname) {
                    if (!Database::getInstance()->fieldExists($fieldname, self::$strTable)) {
                        throw new \Exception(sprintf('Field %s.%s does not exist.', self::$strTable, $fieldname));
                    }
                    $table->addRow();
                    $table->addCell($widthCol_1)->addText(htmlspecialchars($label.':'), 'fStyleBold', 'pStyle');
                    $objCell = $table->addCell($widthCol_2);
                    $value = self::formatValue($fieldname, $objEvent->{$fieldname}, $objEvent->current());
                    // Add multiline text
                    self::addMultilineText($objCell, $value);
                }

                $section->addText('event-alias: '.$objEvent->alias, 'fStyleSmall', 'pStyle');
                $section->addText('event-id: '.$objEvent->id, 'fStyleSmall', 'pStyle');
                $section->addText('version-date: '.Date::parse('Y-m-d'), 'fStyleSmall', 'pStyle');

                $section->addPageBreak();
            }
        }
        // Saving the document as OOXML file...
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        new Folder($tempDir);
        $objWriter->save($rootDir.'/'.$tempDir.'/sac-jahresprogramm.docx');
        sleep(1);

        $fileSRC = $tempDir.'/sac-jahresprogramm.docx';
        Controller::sendFileToBrowser($fileSRC, false);
    }

    public static function addMultilineText(Cell $objCell, string $textlines = ''): void
    {
        foreach (explode("\n", $textlines) as $line) {
            $objCell->addText(htmlspecialchars($line), 'fStyle', 'pStyle');
        }
    }

    /**
     * @param $field
     * @param string $value
     * @param $objEvent
     */
    public static function formatValue(string $field, $value, CalendarEventsModel $objEvent): string
    {
        $table = self::$strTable;

        if ('tl_calendar_events' === $table) {
            if ('courseLevel' === $field) {
                if ('' !== $value) {
                    $value = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$value];
                }
            }

            if ('courseTypeLevel0' === $field) {
                $objCourseMainType = CourseMainTypeModel::findByPk($value);

                if (null !== $objCourseMainType) {
                    $value = $objCourseMainType->name;
                }
            }

            if ('courseTypeLevel1' === $field) {
                $objCourseSubType = CourseSubTypeModel::findByPk($value);

                if (null !== $objCourseSubType) {
                    $value = $objCourseSubType->name;
                }
            }

            if ('kursart' === $field) {
                $levelMain = $objEvent->courseTypeLevel0;
                $levelSub = $objEvent->courseTypeLevel1;
                $strSub = '';
                $strMain = '';
                $objMain = CourseMainTypeModel::findByPk($levelMain);

                if (null !== $objMain) {
                    $strMain = $objMain->name;
                }
                $objSub = CourseSubTypeModel::findByPk($levelSub);

                if (null !== $objSub) {
                    $strSub = $objSub->code.' - '.$objSub->name;
                }
                $value = $strMain.': '.$strSub;
            }

            if ('author' === $field) {
                $value = StringUtil::deserialize($value, true);

                if (\is_array(StringUtil::deserialize($value)) && !empty($value)) {
                    $arrValue = array_map(
                        static fn ($v) => UserModel::findByPk((int) $v)->name,
                        StringUtil::deserialize($value)
                    );
                    $value = implode(', ', $arrValue);
                }
            }

            if ('instructor' === $field) {
                $arrInstructors = CalendarEventsHelper::getInstructorsAsArray($objEvent);
                $arrValue = array_map(
                    static fn ($v) => UserModel::findByPk($v)->name,
                    $arrInstructors
                );
                $value = implode(', ', $arrValue);
            }

            if ('organizers' === $field) {
                $value = StringUtil::deserialize($value, true);

                if (\is_array(StringUtil::deserialize($value)) && !empty($value)) {
                    $arrValue = array_map(
                        static function ($v) {
                            $objOrganizer = EventOrganizerModel::findByPk($v);

                            if (null !== $objOrganizer) {
                                $v = $objOrganizer->title;
                            }

                            return $v;
                        },
                        StringUtil::deserialize($value)
                    );
                    $value = implode(', ', $arrValue);
                }
            }

            if ('startDate' === $field || 'endDate' === $field || 'tstamp' === $field) {
                if ($value > 0) {
                    $value = Date::parse('d.m.Y', $value);
                }
            }

            // Kusdatendaten in der Form d.m.Y, d.m.Y, ...
            if ('eventDates' === $field) {
                $objEvent = CalendarEventsModel::findByPk(self::$arrDatarecord['id']);
                $arr = CalendarEventsHelper::getEventTimestamps($objEvent);
                $arr = array_map(
                    static fn ($tstamp) => Date::parse('d.m.Y', $tstamp),
                    $arr
                );
                $value = implode(', ', $arr);
            }

            if ('mountainguide' === $field) {
                $value = $value > 0 ? 'Mit Bergfuehrer' : 'Mit SAC-Kursleiter';
                $value = utf8_encode($value);
            }
            /*
            if ($field === 'issues')
            {
                $value = str_replace('</li>', '', $value);
                $value = str_replace('</ul>', '', $value);
                $value = str_replace('<ul>', '', $value);
                $value = str_replace('<li>', '•\t', $value);
                $value = str_replace('</p>', chr(13), $value);
                $value = strip_tags($value);
            }
            */

            $value = '' !== $value ? html_entity_decode((string) $value, ENT_QUOTES) : '';
        }

        return $value;
    }
}
