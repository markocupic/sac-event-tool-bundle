<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */


// Extend default palette
Contao\CoreBundle\DataContainer\PaletteManipulator::create()
    ->addLegend('allowed_event_types_legend', 'calendars_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_AFTER)
    ->addField(array('calendar_containers', 'calendar_containerp'), 'calendars_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_PREPEND)
    ->addField(array('allowedEventTypes'), 'allowed_event_types_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_PREPEND)
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

// allowedEventTypes
$GLOBALS['TL_DCA']['tl_user_group']['fields']['allowedEventTypes'] = array
(
    'label'      => &$GLOBALS['TL_LANG']['tl_user_group']['allowedEventTypes'],
    'exclude'    => true,
    'inputType'  => 'checkbox',
    'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
    'foreignKey' => 'tl_event_type.title',
    'sql'        => "blob NULL",
    'eval'       => array('multiple' => true, 'mandatory' => false, 'tl_class' => 'clr'),
);

