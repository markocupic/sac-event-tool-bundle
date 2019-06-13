<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Class tl_module_sac_event_tool
 */
class tl_module_sac_event_tool extends tl_module
{
    /**
     * @return array
     */
    public function getEventFilterBoardFields()
    {
        $opt = array();
        \Contao\Controller::loadDataContainer('tl_event_filter_form');
        \Contao\System::loadLanguageFile('tl_event_filter_form');
        foreach ($GLOBALS['TL_DCA']['tl_event_filter_form']['fields'] as $k => $v)
        {
            $opt[$k] = isset($GLOBALS['TL_LANG']['tl_event_filter_form'][$k][0]) ? $GLOBALS['TL_LANG']['tl_event_filter_form'][$k][0] : $k;
        }
        return $opt;
    }
}