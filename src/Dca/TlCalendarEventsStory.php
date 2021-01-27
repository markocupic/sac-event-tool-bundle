<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\CalendarEventsStoryModel;
use Contao\Database;
use Contao\DataContainer;
use Contao\StringUtil;

/**
 * Class TlCalendarEventsStory
 */
class TlCalendarEventsStory extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

    /**
     * Onload Callback
     * setPalette
     */
    public function setPalettes()
    {
        // Overwrite readonly attribute for admins
        if ($this->User->admin)
        {
            $fields = array('sacMemberId', 'eventId', 'authorName');
            foreach ($fields as $field)
            {
                $GLOBALS['TL_DCA']['tl_calendar_events_story']['fields'][$field]['eval']['readonly'] = false;
            }
        }
    }

    /**
     *
     * OnLoad Callback
     * deleteUnfinishedAndOldEntries
     */
    public function deleteUnfinishedAndOldEntries()
    {
        // Delete old and unpublished stories
        $limit = time() - 60 * 60 * 24 * 30;
        Database::getInstance()
            ->prepare('DELETE FROM tl_calendar_events_story WHERE tstamp<? AND publishState<?')
            ->execute($limit, 3);

        // Delete unfinished stories older the 14 days
        $limit = time() - 60 * 60 * 24 * 14;
        Database::getInstance()
            ->prepare('DELETE FROM tl_calendar_events_story WHERE tstamp<? AND text=? AND youtubeId=? AND multiSRC=?')
            ->execute($limit, '', '', null);

        // Keep stories up to date, if events are renamed f.ex.
        $objStory = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_story')
            ->execute();
        while ($objStory->next())
        {
            $objStoryModel = CalendarEventsStoryModel::findByPk($objStory->id);
            $objEvent = $objStoryModel->getRelated('eventId');
            if ($objEvent !== null)
            {
                $objStoryModel->eventTitle = $objEvent->title;
                $objStoryModel->substitutionEvent = ($objEvent->executionState === 'event_adapted' && $objEvent->eventSubstitutionText != '') ? $objEvent->eventSubstitutionText : '';
                $objStoryModel->eventStartDate = $objEvent->startDate;
                $objStoryModel->eventEndDate = $objEvent->endDate;
                $objStoryModel->organizers = $objEvent->organizers;

                $aDates = [];
                $arrDates = StringUtil::deserialize($objEvent->eventDates, true);
                foreach ($arrDates as $arrDate)
                {
                    $aDates[] = $arrDate['new_repeat'];
                }
                $objStoryModel->eventDates = serialize($aDates);
                $objStoryModel->save();
            }
        }
    }



    /**
     * Add an image to each record
     * @param array $row
     * @param string $label
     * @param DataContainer $dc
     * @param array $args
     *
     * @return array
     */
    public function addIcon($row, $label, DataContainer $dc, $args)
    {
        $image = 'member';
        $disabled = false;
        if ($row['publishState'] != '3')
        {
            $image .= '_';
            $disabled = true;
        }

        $args[0] = sprintf('<div class="list_icon_new" style="background-image:url(\'%ssystem/themes/%s/icons/%s.svg\')" data-icon="%s.svg" data-icon-disabled="%s.svg">&nbsp;</div>', TL_ASSETS_URL, Backend::getTheme(), $image, $disabled ? $image : rtrim($image, '_'), rtrim($image, '_') . '_');

        return $args;
    }

}