<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

/**
 * Class tl_calendar_events_story
 */
class tl_calendar_events_story extends Backend
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
        if($this->User->admin)
        {
            $fields = array('sacMemberId', 'eventId', 'authorName');
            foreach($fields as $field)
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
        Database::getInstance()->prepare('DELETE FROM tl_calendar_events_story WHERE tstamp<? AND publishState<?')->execute($limit, 3);


        // Delete unfinished stories older the 14 days
        $limit = time() - 60 * 60 * 24 * 14;
        Database::getInstance()->prepare('DELETE FROM tl_calendar_events_story WHERE tstamp<? AND text=? AND youtubeId=? AND multiSRC=?')->execute($limit, '', '', null);

        // Keep stories up to date, if events are renamed f.ex.
        $objStory = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story')->execute();
        while ($objStory->next())
        {
            $objStoryModel = \Contao\CalendarEventsStoryModel::findByPk($objStory->id);
            $objEvent = $objStoryModel->getRelated('eventId');
            if ($objEvent !== null)
            {
                $objStoryModel->eventTitle = $objEvent->title;
                $objStoryModel->substitutionEvent = ($objEvent->executionState === 'event_adapted' && $objEvent->eventSubstitutionText != '') ? $objEvent->eventSubstitutionText : '';
                $objStoryModel->eventStartDate = $objEvent->startDate;
                $objStoryModel->eventEndDate = $objEvent->endDate;
                $objStoryModel->organizers = $objEvent->organizers;

                $aDates = [];
                $arrDates = \Contao\StringUtil::deserialize($objEvent->eventDates, true);
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
     * @param $strContent
     * @param $strTemplate
     * @return mixed
     */
    public function parseBackendTemplate($strContent, $strTemplate)
    {

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


    /**
     * Return the "toggle visibility" button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (strlen(Input::get('tid')))
        {
            $this->toggleVisibility(Input::get('tid'), (Input::get('state') == 1), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }


        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin)
        {
            // Full access to admins
        }
        elseif (array_intersect(StringUtil::deserialize($this->User->groups, true), array(Config::get('SAC_EVT_GRUPPE_EVENTERFASSUNG_HAUPTREDAKTOREN'))))
        {
            // If user belongs to group "Hauptredaktor" grant full rights.
        }
        else
        {
            $id = Input::get('id');
            $objEvent = CalendarEventsModel::findByPk($id);
            if ($objEvent !== null)
            {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                if (!in_array($this->User->id, $arrAuthors))
                {
                    return '';
                }
            }
        }


        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . $row['disable'];

        if ($row['disable'])
        {
            $icon = 'invisible.svg';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label, 'data-state="' . ($row['disable'] ? 0 : 1) . '"') . '</a> ';
    }


    /**
     * Disable/enable a registration
     *
     * @param integer $intId
     * @param boolean $blnVisible
     * @param DataContainer $dc
     *
     * @throws \Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function toggleVisibility($intId, $blnVisible, DataContainer $dc = null)
    {
        // Set the ID and action
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        if ($dc)
        {
            $dc->id = $intId; // see #8043
        }


        // Allow full access only to admins, owners and allowed groups
        if ($this->User->isAdmin)
        {
        }
        elseif (array_intersect(StringUtil::deserialize($this->User->groups, true), array(Config::get('SAC_EVT_GRUPPE_EVENTERFASSUNG_HAUPTREDAKTOREN'))))
        {
            // If user belongs to group "Hauptredaktor" grant full rights.
        }
        else
        {
            $id = Input::get('id');
            $objEvent = CalendarEventsModel::findByPk($id);
            if ($objEvent !== null)
            {
                $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                if (!in_array($this->User->id, $arrAuthors))
                {
                    throw new \Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to activate/deactivate registration ID ' . $id . '.');
                }
            }
        }


        $objVersions = new Versions('tl_calendar_events_story', $intId);
        $objVersions->initialize();

        // Reverse the logic (members have disabled=1)
        $blnVisible = !$blnVisible;

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA']['tl_calendar_events_story']['fields']['disable']['save_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_calendar_events_story']['fields']['disable']['save_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, ($dc ?: $this));
                }
                elseif (is_callable($callback))
                {
                    $blnVisible = $callback($blnVisible, ($dc ?: $this));
                }
            }
        }

        $time = time();

        // Update the database
        $this->Database->prepare("UPDATE tl_calendar_events_story SET tstamp=$time, disable='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
            ->execute($intId);

        $objVersions->create();


    }
}
