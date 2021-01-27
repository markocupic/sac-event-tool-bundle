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

/**
 * Class TlTourDifficulty
 * @package Markocupic\SacEventToolBundle\Dca
 */
class TlTourDifficulty extends Backend
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
