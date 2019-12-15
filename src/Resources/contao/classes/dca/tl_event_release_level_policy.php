<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Class tl_event_release_level_policy
 */
class tl_event_release_level_policy extends Backend
{
    /**
     * List a style sheet
     *
     * @param array $row
     *
     * @return string
     */
    public function listReleaseLevels($row)
    {
        return '<div class="tl_content_left"><span class="level">Stufe: ' . $row['level'] . '</span> ' . $row['title'] . "</div>\n";
    }

}
