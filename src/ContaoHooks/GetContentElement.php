<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Input;

/**
 * Class GetContentElement
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class GetContentElement
{


    /**
     *
     */
    public function getContentElement($objElement, $strBuffer)
    {

        // Allow event preview if the eventToken is passed to the url
        if(Input::get('eventPreviewMode') === 'true')
        {
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
                            $objEvent->published = Input::get('published');
                            $objEvent->save();
                        }
                    }
                }
            }

        }

        return $strBuffer;
    }

}


