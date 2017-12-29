<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Module;
use Contao\BackendTemplate;
use Contao\CalendarEventsStoryModel;
use Contao\PageModel;
use Contao\MemberModel;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Pagination;
use Contao\Input;
use Contao\Environment;
use Contao\Config;
use Contao\Validator;
use Patchwork\Utf8;
use Contao\System;


/**
 * Class ModuleSacEventToolCalendarEventStoryList
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolCalendarEventStoryList extends Module
{

    /**
     * @var
     */
    protected $stories;

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_event_tool_calendar_event_story_list';


    /**
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventStoryList'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }
        $arrOptions = array('order' => 'addedOn DESC');
        $this->stories = CalendarEventsStoryModel::findBy(array('tl_calendar_events_story.publishState=?'), array('3'), $arrOptions);

        if ($this->stories === null)
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

        $objPageModel = null;
        if ($this->jumpTo)
        {
            $objPageModel = PageModel::findByPk($this->jumpTo);
        }

        $arrAllStories = array();
        while ($this->stories->next())
        {
            $arrStory = $this->stories->row();
            $objMember = MemberModel::findBySacMemberId($arrStory['sacMemberId']);
            $arrStory['authorId'] = $objMember->id;
            $arrStory['authorName'] = $objMember !== null ? $objMember->firstname . ' ' . $objMember->lastname : $arrStory['authorName'];
            $arrStory['href'] = $objPageModel !== null ? ampersand($objPageModel->getFrontendUrl((Config::get('useAutoItem') ? '/' : '/items/') . $this->stories->id)) : null;
            $multiSRC = StringUtil::deserialize($arrStory['multiSRC'], true);

            // Add a random image to the list
            $arrStory['singleSRC'] = null;
            if (!empty($multiSRC) && is_array($multiSRC))
            {
                $k = array_rand($multiSRC);
                $singleSRC = $multiSRC[$k];
                if (Validator::isUuid($singleSRC))
                {
                    $objFiles = FilesModel::findByUuid($singleSRC);
                    if ($objFiles !== null)
                    {
                        if (is_file($rootDir . '/' . $objFiles->path))
                        {
                            $arrStory['singleSRC'] = array(
                                'id' => $objFiles->id,
                                'path' => $objFiles->path,
                                'uuid' => StringUtil::binToUuid($objFiles->uuid),
                                'name' => $objFiles->name,
                                'singleSRC' => $objFiles->path,
                                'title' => StringUtil::specialchars($objFiles->name),
                                'filesModel' => $objFiles->current(),
                            );
                        }
                    }
                }
            }

            $arrAllStories[] = $arrStory;
        }

        $total = count($arrAllStories);
        $limit = $total;
        $offset = 0;

        // Overall limit
        if ($this->story_limit > 0)
        {
            $total = min($this->story_limit, $total);
            $limit = $total;
        }

        // Pagination
        if ($this->perPage > 0)
        {
            $id = 'page_e' . $this->id;
            $page = (Input::get($id) !== null) ? Input::get($id) : 1;

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

        $arrStories = [];
        for ($i = $offset; $i < $offset + $limit; $i++)
        {
            $arrStories[] = $arrAllStories[$i];
        }

        $this->Template->stories = $arrStories;

    }
}
