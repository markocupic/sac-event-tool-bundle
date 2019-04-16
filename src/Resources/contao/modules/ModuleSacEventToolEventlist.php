<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Patchwork\Utf8;

/**
 * Class ModuleSacEventToolEventlist
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolEventlist extends Events
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_eventlist';

    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventlist'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        $this->cal_calendar = $this->sortOutProtected(StringUtil::deserialize($this->cal_calendar, true));

        // Return if there are no calendars
        if (empty($this->cal_calendar) || !\is_array($this->cal_calendar))
        {
            return '';
        }

        // Show the event reader if an item has been selected
        if ($this->cal_readerModule > 0 && (isset($_GET['events']) || (Config::get('useAutoItem') && isset($_GET['auto_item']))))
        {
            return $this->getFrontendModule($this->cal_readerModule, $this->strColumn);
        }

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        $arrEvents = $this->getEvents();

        $total = \count($arrEvents);
        $limit = $total;
        $offset = 0;

        // Overall limit
        if ($this->cal_limit > 0)
        {
            $total = min($this->cal_limit, $total);
            $limit = $total;
        }

        // Pagination
        if ($this->perPage > 0)
        {
            $id = 'page_e' . $this->id;
            $page = Input::get($id) ?? 1;

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil($total / $this->perPage), 1))
            {
                throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
            }

            $offset = ($page - 1) * $this->perPage;
            $limit = min($this->perPage + $offset, $total);

            $objPagination = new Pagination($total, $this->perPage, Config::get('maxPaginationLinks'), $id);
            $this->Template->pagination = $objPagination->generate("\n  ");
        }

        $strEvents = '';
        $imgSize = false;

        // Override the default image size
        if ($this->imgSize != '')
        {
            $size = StringUtil::deserialize($this->imgSize);

            if ($size[0] > 0 || $size[1] > 0 || is_numeric($size[2]))
            {
                $imgSize = $this->imgSize;
            }
        }

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        // Parse events
        for ($i = $offset; $i < $limit; $i++)
        {
            $event = $arrEvents[$i];

            $objTemplate = new FrontendTemplate($this->cal_template);
            $objTemplate->setData($event);

            // Show the teaser text of redirect events (see #6315)
            if (\is_bool($event['details']) && $event['source'] == 'default')
            {
                $objTemplate->hasDetails = false;
            }

            $objTemplate->addImage = false;

            // Add an image
            if ($event['addImage'] && $event['singleSRC'] != '')
            {
                $objModel = FilesModel::findByUuid($event['singleSRC']);

                if ($objModel !== null && is_file($rootDir . '/' . $objModel->path))
                {
                    if ($imgSize)
                    {
                        $event['size'] = $imgSize;
                    }

                    $event['singleSRC'] = $objModel->path;
                    $this->addImageToTemplate($objTemplate, $event, null, null, $objModel);

                    // Link to the event if no image link has been defined
                    if (!$objTemplate->fullsize && !$objTemplate->imageUrl)
                    {
                        // Unset the image title attribute
                        $picture = $objTemplate->picture;
                        unset($picture['title']);
                        $objTemplate->picture = $picture;

                        // Link to the event
                        $objTemplate->linkTitle = $objTemplate->readMore;
                    }
                }
            }

            $objTemplate->enclosure = array();

            // Add enclosure
            if ($event['addEnclosure'])
            {
                $this->addEnclosuresToTemplate($objTemplate, $event);
            }

            $strEvents .= $objTemplate->parse();
        }

        $this->Template->hasItems = $total > 0 ? true : false;
        $this->Template->events = $strEvents;
        $this->Template->countItems = $total;

        // Headline
        if (Input::get('year') > 2000)
        {
            $this->Template->headline = $this->headline != '' ? $this->headline . ' ' . Input::get('year') : Input::get('year');
        }
    }

    /**
     * @return array
     */
    protected function getEvents()
    {
        // Build query string
        $query = 'SELECT * FROM tl_calendar_events WHERE published=\'1\' AND pid IN(' . implode(',', $this->cal_calendar) . ')';

        // Filter eventType
        $arrEventType = StringUtil::deserialize($this->eventType, true);
        $arrQuery = array();
        foreach ($arrEventType as $eventType)
        {
            $arrQuery[] = "eventType='" . $eventType . "'";
        }
        if (!empty($arrQuery))
        {
            $query .= ' AND (' . implode(' OR ', $arrQuery) . ')';
        }

        // Filterboard: year filter
        if (Input::get('year') > 2000)
        {
            $year = Input::get('year');
            $intStart = strtotime('01-01-' . $year);
            $intEnd = (int)(strtotime('31-12-' . $year) + 24 * 3600 - 1);
            $query .= ' AND startDate>=' . $intStart . ' AND startDate<' . $intEnd;
        }
        else
        {
            // Show upcoming events
            $query .= ' AND endDate>=' . (int)(strtotime(Date::parse('Y-m-d')));
        }

        // Filterboard: dateStart filter
        if (Input::get('dateStart') != '')
        {
            $dateStart = strtotime(Input::get('dateStart'));
            if ($dateStart > 0)
            {
                $query .= ' AND startDate>=' . $dateStart;
            }
        }

        // Filterboard: eventId filter
        if (Input::get('eventId') != '')
        {
            $strId = preg_replace('/\s/', '', Input::get('eventId'));
            $arrChunk = explode('-', $strId);
            if (isset($arrChunk[1]))
            {
                if (is_numeric($arrChunk[1]))
                {
                    $query .= ' AND id=' . $arrChunk[1];
                }
            }
        }

        // Filterboard: courseId
        if (Input::get('courseId') != '')
        {
            $strId = trim(Input::get('courseId'));
            if ($strId != '')
            {
                $query .= " AND courseId LIKE '%" . $strId . "%'";
            }
        }

        // ORDER BY
        $query .= ' ORDER BY startDate ASC';

        // Query !!!
        $objEvents = Database::getInstance()->query($query);

        $arrAllEvents = $objEvents->fetchAllAssoc();
        $arrEvents = array();
        foreach ($arrAllEvents as $event)
        {
            // Filter items that can not be filtered in the query above
            // Filterboard: organizers
            if (Input::get('organizers') != '')
            {
                $arrOrganizers = StringUtil::deserialize(Input::get('organizers'), true);
                $eventOrganizers = StringUtil::deserialize($event['organizers'], true);
                if (count(array_intersect($arrOrganizers, $eventOrganizers)) < 1)
                {
                    continue;
                }
            }

            // Filterboard: tourType
            if (Input::get('tourType') > 0)
            {
                $arrTourTypes = StringUtil::deserialize($event['tourType'], true);
                if (!in_array(Input::get('tourType'), $arrTourTypes))
                {
                    continue;
                }
            }

            // Filterboard: courseType
            if (Input::get('courseType') > 0)
            {
                $arrCourseTypes = StringUtil::deserialize($event['courseTypeLevel1'], true);
                if (!in_array(Input::get('courseType'), $arrCourseTypes))
                {
                    continue;
                }
            }

            $strSearchterm = Input::get('searchterm');
            if ($strSearchterm != '')
            {
                $intFound = 0;
                foreach (explode(' ', $strSearchterm) as $strNeedle)
                {
                    if ($intFound)
                    {
                        continue;
                    }

                    // Suche nach Namen des Kursleiters
                    $arrInstructors = CalendarEventsHelper::getInstructorsAsArray($event['id']);
                    $strLeiter = implode(', ', array_map(function ($userId) {
                        return UserModel::findByPk($userId)->name;
                    }, $arrInstructors));

                    if ($intFound == 0)
                    {
                        if ($this->textSearch($strNeedle, $strLeiter))
                        {
                            $intFound++;
                        }
                    }

                    if ($intFound == 0)
                    {
                        // Suchbegriff im Titel suchen
                        if ($this->textSearch($strNeedle, $event['title']))
                        {
                            $intFound++;
                        }
                    }

                    if ($intFound == 0)
                    {
                        // Suchbegriff im Teaser suchen
                        if ($this->textSearch($strNeedle, $event['teaser']))
                        {
                            $intFound++;
                        }
                    }
                }

                if ($intFound < 1)
                {
                    continue;
                }
            }

            // Pass the filter
            $arrEvents[] = $event;
        }

        return $arrEvents;
    }

    /**
     * Helper method of event filtering
     * @param $strNeedle
     * @param $strHaystack
     * @return bool
     */
    protected function textSearch($strNeedle = '', $strHaystack = '')
    {
        if ($strNeedle == '')
        {
            return true;
        }
        elseif (trim($strNeedle) == '')
        {
            return true;
        }
        elseif ($strHaystack == '')
        {
            return false;
        }
        elseif (trim($strHaystack) == '')
        {
            return false;
        }
        else
        {
            if (preg_match('/' . $strNeedle . '/i', $strHaystack))
            {
                return true;
            }
        }
        return false;
    }

}

class_alias(ModuleSacEventToolEventlist::class, 'ModuleSacEventToolEventlistNew');
