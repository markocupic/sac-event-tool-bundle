<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

/**
 * Class tl_tour_difficulty
 */
class tl_tour_difficulty extends Backend
{

    /**
     * List a style sheet
     *
     * @param array $row
     *
     * @return string
     */
    public function listDifficulties($row)
    {
        return '<div class="tl_content_left"><span class="level">' . $row['title'] . '</span> ' . $row['shortcut'] . "</div>\n";
    }
}