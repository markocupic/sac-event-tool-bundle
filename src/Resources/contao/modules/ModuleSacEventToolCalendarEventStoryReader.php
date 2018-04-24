<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\File;
use Contao\FilesModel;
use Contao\Input;
use Contao\MemberModel;
use Contao\Module;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Patchwork\Utf8;


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
    protected $strTemplate = 'mod_event_tool_calendar_event_story_reader';


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

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventStoryReader'][0]) . ' ###';
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

        $objStory = CalendarEventsStoryModel::findAll();
        while ($objStory->next())
        {
            //$objStory->securityToken = md5(rand(100000000, 999999999)) . $objStory->id;
            //$objStory->save();
        }


        if (strlen(Input::get('securityToken')))
        {
            $arrColumns = array('tl_calendar_events_story.securityToken=? AND tl_calendar_events_story.id=?');
            $arrValues = array(Input::get('securityToken'), Input::get('items'));
        }
        else
        {
            $arrColumns = array('tl_calendar_events_story.publishState=? AND tl_calendar_events_story.id=?');
            $arrValues = array('3', Input::get('items'));
        }

        $this->story = CalendarEventsStoryModel::findBy($arrColumns, $arrValues);

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

        // Set data
        $this->Template->setData($this->story->row());

        // Fallback if author is no more findable in tl_member
        $objAuthor = MemberModel::findBySacMemberId($this->story->sacMemberId);
        $this->Template->authorName = $objAuthor !== null ? $objAuthor->firstname . ' ' . $objAuthor->lastname : $this->story->authorName;

        // !!! $objEvent can be NULL, if the related event no more exists
        $objEvent = CalendarEventsModel::findByPk($this->story->eventId);
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
                            $arrMeta = StringUtil::deserialize($objFiles->meta, true);
                            $title = '';
                            $alt = '';
                            $caption = '';

                            if (isset($arrMeta['de']))
                            {
                                $title = $arrMeta['de']['title'];
                                $alt = $arrMeta['de']['alt'];
                                $caption = $arrMeta['de']['caption'];
                                $photographer = $arrMeta['de']['photographer'];
                            }

                            $images[$objFiles->path] = array
                            (
                                'id'           => $objFiles->id,
                                'path'         => $objFiles->path,
                                'uuid'         => $objFiles->uuid,
                                'name'         => $objFile->basename,
                                'singleSRC'    => $objFiles->path,
                                'title'        => StringUtil::specialchars($objFile->basename),
                                'filesModel'   => $objFiles->current(),
                                'caption'      => StringUtil::specialchars($caption),
                                'alt'          => StringUtil::specialchars($alt),
                                'title'        => StringUtil::specialchars($title),
                                'photographer' => StringUtil::specialchars($photographer),
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
