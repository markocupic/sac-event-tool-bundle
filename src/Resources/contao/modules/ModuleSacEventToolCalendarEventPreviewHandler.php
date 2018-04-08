<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\Config;
use Contao\CalendarEventsModel;
use Contao\Input;
use Contao\Module;
use Patchwork\Utf8;
use Contao\BackendTemplate;

/**
 * Class ModuleSacEventToolCalendarEventStoryReader
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolCalendarEventPreviewHandler extends Module
{

    /**
     * @var
     */
    protected $story;

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_event_tool_calendar_event_preview_handler';


    /**
     * !Important
     * Add this content element right before! and right after! a calendar event reader element
     * to use event preview in conjunction with an eventToken
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

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventPreviewHandler'][0]);
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }


        if (isset($_GET['eventToken']))
        {

            if (!isset($_GET['events']) && Config::get('useAutoItem') && isset($_GET['auto_item']))
            {
                Input::setGet('events', Input::get('auto_item'));
            }

            if (Input::get('events') != '')
            {
                $objEvent = CalendarEventsModel::findByIdOrAlias(Input::get('events'));
                if ($objEvent !== null)
                {
                    if ($objEvent->eventToken === $_GET['eventToken'])
                    {
                        // Start
                        if(!Input::get('publishedEventIn'))
                        {
                            Input::setGet('publishedEventIn', 'contentElement-' . $this->id);
                            Input::setGet('published', $objEvent->published);
                            Input::setGet('eventPreviewMode', 'true');
                            $objEvent->published = '1';
                            $objEvent->save();
                        }else{
                            $objEvent->published = Input::get('published');
                            $objEvent->save();
                        }
                    }
                }
            }
        }

        return '';
    }


    /**
     * Generate the module
     */
    protected function compile()
    {
        //
    }
}
