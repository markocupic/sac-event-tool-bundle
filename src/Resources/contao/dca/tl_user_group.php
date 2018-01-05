<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */


// Extend default palette
Contao\CoreBundle\DataContainer\PaletteManipulator::create()
    ->addField(array('calendar_containers', 'calendar_containerp'), 'calendars_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_PREPEND)
    ->applyToPalette('default', 'tl_user_group');


// Fields

// calendar_containers
$GLOBALS['TL_DCA']['tl_user_group']['fields']['calendar_containers'] = array
(
    'label'      => &$GLOBALS['TL_LANG']['tl_user_group']['calendar_containers'],
    'exclude'    => true,
    'inputType'  => 'checkbox',
    'foreignKey' => 'tl_calendar_container.title',
    'eval'       => array('multiple' => true),
    'sql'        => "blob NULL",
);

// calendar_containerp
$GLOBALS['TL_DCA']['tl_user_group']['fields']['calendar_containerp'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_user_group']['calendar_containerp'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'options'   => array('create', 'delete'),
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval'      => array('multiple' => true),
    'sql'       => "blob NULL",
);

