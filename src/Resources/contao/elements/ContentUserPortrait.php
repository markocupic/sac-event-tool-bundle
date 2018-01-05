<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\ContentElement;
use Contao\UserModel;
use Contao\CalendarEventsModel;
use Contao\Database;
use Contao\StringUtil;



/**
 * Class ContentUserPortrait
 * @package Markocupic\SacEventToolBundle
 */
class ContentUserPortrait extends ContentElement
{


    protected $objUser;

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'ce_user_portrait';


    /**
     * Return if there are no files
     *
     * @return string
     */
    public function generate()
    {

        if (\Input::get('username') != '')
        {
            $objUser = UserModel::findByUsername(\Input::get('username'));
            if ($objUser !== null)
            {
                $this->objUser = $objUser;
            }
        }

        if ($this->objUser === null)
        {
            return '';
        }


        return parent::generate();
    }


    /**
     * Generate the content element
     */
    protected function compile()
    {
        $arrUser = $this->objUser->row();
        $this->Template->user = $arrUser;


        // List all courses
        $arrEvents = array();
        $objEvent = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE published=? AND startTime > ? ORDER BY startDate')->execute(1, time());
        while ($objEvent->next())
        {
            $eventModel = CalendarEventsModel::findByPk($objEvent->id);
            if ($eventModel !== null)
            {
                if (in_array($this->objUser->id, StringUtil::deserialize($objEvent->instructor, true)))
                {
                    $arrEvents[$objEvent->eventType][] = $objEvent->row();
                }
            }
        }
        $this->Template->events = $arrEvents;
    }
}