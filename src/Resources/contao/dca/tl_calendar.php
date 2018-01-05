<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */


// Table config
$GLOBALS['TL_DCA']['tl_calendar']['config']['ptable'] = 'tl_calendar_container';


// List
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['mode'] = 4;
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['child_record_callback'] = array('tl_calendar_sac_event_tool', 'listCalendars');
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['headerFields'] = array('title');
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['disableGrouping'] = true;
$GLOBALS['TL_DCA']['tl_calendar']['list']['operations']['cut'] = array
(
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['cut'],
    'href'  => 'act=paste&amp;mode=cut',
    'icon'  => 'cut.svg'
);


// Palettes
Contao\CoreBundle\DataContainer\PaletteManipulator::create()
    ->addLegend('access_permission_legend', 'protected_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE)
    ->addField(array('useLevelAccessPermissions'), 'access_permission_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_calendar');

// Selectors
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'useLevelAccessPermissions';

// Subpalettes
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['useLevelAccessPermissions'] = 'levelAccessPermissionPackage';


// Fields
// pid
$GLOBALS['TL_DCA']['tl_calendar']['fields']['pid'] = array(
    'foreignKey' => 'tl_calendar_container.title',
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => array('type' => 'belongsTo', 'load' => 'eager')
);

// levelAccessPermissionPackage
$GLOBALS['TL_DCA']['tl_calendar']['fields']['levelAccessPermissionPackage'] = array(
    'label'      => &$GLOBALS['TL_LANG']['tl_calendar']['levelAccessPermissionPackage'],
    'exclude'    => true,
    'inputType'  => 'select',
    'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
    'foreignKey' => 'tl_event_release_level_policy_package.title',
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'eval'       => array('includeBlankOption' => false, 'mandatory' => true, 'tl_class' => 'clr')
);

// useLevelAccessPermissions
$GLOBALS['TL_DCA']['tl_calendar']['fields']['useLevelAccessPermissions'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['useLevelAccessPermissions'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => array('submitOnChange' => true),
    'sql'       => "char(1) NOT NULL default ''"
);
