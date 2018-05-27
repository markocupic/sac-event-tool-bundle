<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;


PaletteManipulator::create()
    ->addLegend('sacEventTool_legend', 'global_legend')
    ->addField(array('tourenUndKursadministrationName', 'tourenUndKursadministrationEmail'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings');


$GLOBALS['TL_DCA']['tl_settings']['fields']['tourenUndKursadministrationName'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['tourenUndKursadministrationName'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['tourenUndKursadministrationEmail'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['tourenUndKursadministrationEmail'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'rgxp' => 'friendly', 'decodeEntities' => true, 'tl_class' => 'w50'),
);
