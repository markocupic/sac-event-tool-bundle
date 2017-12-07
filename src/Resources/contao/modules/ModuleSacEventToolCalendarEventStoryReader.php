<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Patchwork\Utf8;
use Contao\Module;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\Input;
use Contao\CalendarEventsStoryModel;
use Contao\CalendarEventsModel;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\FilesModel;
use Contao\Validator;
use Contao\File;
use Contao\PageModel;
use Contao\System;


/**
 * Class ModuleSacEventToolCalendarEventStoryReader
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolCalendarEventStoryReader extends Module
{

    /**
     * @var
     */
    protected $story;

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_calendar_events_story_reader';


    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['calendarEventsStoryReader'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }


        // Set the item from the auto_item parameter
        if (!isset($_GET['items']) && Config::get('useAutoItem') && isset($_GET['auto_item']))
        {
            Input::setGet('items', Input::get('auto_item'));
        }

        // Do not index or cache the page if no event has been specified
        if (!Input::get('items'))
        {
            /** @var PageModel $objPage */
            global $objPage;

            $objPage->noSearch = 1;
            $objPage->cache = 0;

            return '';
        }

        $arrColumns = array('tl_calendar_events_story.publishState=? AND tl_calendar_events_story.id=?');
        $this->story = CalendarEventsStoryModel::findBy($arrColumns, array('3', Input::get('items')));

        if ($this->story === null)
        {
            return '';
        }

        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $objEvent = CalendarEventsModel::findByPk($this->story->pid);
        $objAuthor = MemberModel::findBySacMemberId($this->story->sacMemberId);
        $this->Template->setData($this->story->row());
        $this->Template->authorName = $objAuthor !== null ? $objAuthor->firstname . ' ' . $objAuthor->lastname : $this->story->authorName;
        $this->Template->objEvent = $objEvent;

        // Add gallery
        $images = [];
        $arrMultiSRC = StringUtil::deserialize($this->story->multiSRC, true);
        foreach ($arrMultiSRC as $uuid)
        {
            if (Validator::isUuid($uuid))
            {
                $objFiles = FilesModel::findByUuid($uuid);
                if ($objFiles !== null)
                {
                    if (is_file($rootDir . '/' . $objFiles->path))
                    {
                        $objFile = new File($objFiles->path);

                        if ($objFile->isImage)
                        {
                            $images[$objFiles->path] = array
                            (
                                'id' => $objFiles->id,
                                'path' => $objFiles->path,
                                'uuid' => $objFiles->uuid,
                                'name' => $objFile->basename,
                                'singleSRC' => $objFiles->path,
                                'title' => StringUtil::specialchars($objFile->basename),
                                'filesModel' => $objFiles->current(),
                            );
                        }
                    }
                }
            }
        }

        // Custom image sorting
        if ($this->story->orderSRC != '')
        {
            $tmp = StringUtil::deserialize($this->story->orderSRC);

            if (!empty($tmp) && is_array($tmp))
            {
                // Remove all values
                $arrOrder = array_map(function () {
                }, array_flip($tmp));

                // Move the matching elements to their position in $arrOrder
                foreach ($images as $k => $v)
                {
                    if (array_key_exists($v['uuid'], $arrOrder))
                    {
                        $arrOrder[$v['uuid']] = $v;
                        unset($images[$k]);
                    }
                }

                // Append the left-over images at the end
                if (!empty($images))
                {
                    $arrOrder = array_merge($arrOrder, array_values($images));
                }

                // Remove empty (unreplaced) entries
                $images = array_values(array_filter($arrOrder));
                unset($arrOrder);
            }
        }
        $images = array_values($images);

        $this->Template->images = count($images) ? $images : null;

        // Add youtube movie
        $this->Template->youtubeId = $this->story->youtubeId != '' ? $this->story->youtubeId : null;
    }
}
