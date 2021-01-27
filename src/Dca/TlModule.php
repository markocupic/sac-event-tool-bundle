<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Controller;
use Contao\System;

/**
 * Class TlModule
 */
class TlModule extends \tl_module
{
    /**
     * @return array
     */
    public function getEventFilterBoardFields()
    {
        $opt = array();
        Controller::loadDataContainer('tl_event_filter_form');
        System::loadLanguageFile('tl_event_filter_form');
        foreach ($GLOBALS['TL_DCA']['tl_event_filter_form']['fields'] as $k => $v)
        {
            $opt[$k] = isset($GLOBALS['TL_LANG']['tl_event_filter_form'][$k][0]) ? $GLOBALS['TL_LANG']['tl_event_filter_form'][$k][0] : $k;
        }
        return $opt;
    }

    /**
     * Return all calendar templates as array
     *
     * @return array
     */
    public function getEventListTemplates()
    {
        return $this->getTemplateGroup('event_list_partial_');
    }
}
