<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */


// Table config
$GLOBALS['TL_DCA']['tl_calendar']['config']['ptable'] = 'tl_calendar_container';


// List
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['mode'] = 4;
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['child_record_callback'] = array('tl_calendar_sac_event_tool', 'listCalendars');
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['headerFields'] = array('title');
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['disableGrouping'] = true;

if (BackendUser::getInstance()->isAdmin)
{
    $GLOBALS['TL_DCA']['tl_calendar']['list']['operations']['cut'] = array
    (
        'label' => &$GLOBALS['TL_LANG']['tl_calendar']['cut'],
        'href'  => 'act=paste&amp;mode=cut',
        'icon'  => 'cut.svg',
    );
}


// Palettes
Contao\CoreBundle\DataContainer\PaletteManipulator::create()
    ->addLegend('event_type_legend', 'protected_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE)
    ->addLegend('access_permission_legend', 'protected_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE)
    ->addLegend('preview_page_legend', 'protected_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_BEFORE)
    ->addField(array('allowedEventTypes'), 'event_type_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
    ->addField(array('useLevelAccessPermissions'), 'access_permission_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
    ->addField(array('previewPage'), 'preview_page_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
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
    'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
);

// Allowed event types
$GLOBALS['TL_DCA']['tl_calendar']['fields']['allowedEventTypes'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['allowedEventTypes'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'],
    'eval'      => array('multiple' => true, 'includeBlankOption' => false, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => true),
    'sql'       => "blob NULL",
);

// Preview page
$GLOBALS['TL_DCA']['tl_calendar']['fields']['previewPage'] = array(

    'label'      => &$GLOBALS['TL_LANG']['tl_calendar']['previewPage'],
    'exclude'    => true,
    'inputType'  => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval'       => array('mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'),
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
);

// levelAccessPermissionPackage
$GLOBALS['TL_DCA']['tl_calendar']['fields']['levelAccessPermissionPackage'] = array(
    'label'      => &$GLOBALS['TL_LANG']['tl_calendar']['levelAccessPermissionPackage'],
    'exclude'    => true,
    'inputType'  => 'select',
    'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
    'foreignKey' => 'tl_event_release_level_policy_package.title',
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'eval'       => array('includeBlankOption' => false, 'mandatory' => true, 'tl_class' => 'clr'),
);

// useLevelAccessPermissions
$GLOBALS['TL_DCA']['tl_calendar']['fields']['useLevelAccessPermissions'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['useLevelAccessPermissions'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => array('submitOnChange' => true),
    'sql'       => "char(1) NOT NULL default ''",
);
