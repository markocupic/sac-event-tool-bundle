<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

/**
 * Class tl_content_sac_event_tool
 */
class tl_content_sac_event_tool extends tl_content
{
    /**
     * @return array
     */
    public function getCabannes()
    {

        $options = array();
        $objDb = \Database::getInstance()->prepare('SELECT * FROM tl_cabanne_sac')->execute();
        while ($objDb->next())
        {
            $options[$objDb->id] = $objDb->name;
        }

        return $options;

    }
}

