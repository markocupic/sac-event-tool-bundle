<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
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
    ->addField(array('allowedEventTypes,adviceOnEventReleaseLevelChange,adviceOnEventPublish'), 'event_type_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_calendar');

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

// adviceOnEventReleaseLevelChange
$GLOBALS['TL_DCA']['tl_calendar']['fields']['adviceOnEventReleaseLevelChange'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['adviceOnEventReleaseLevelChange'],
    'exclude'   => true,
    'filter'    => false,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false),
    'sql'       => "varchar(255) NOT NULL default ''"
);

// adviceOnEventPublish
$GLOBALS['TL_DCA']['tl_calendar']['fields']['adviceOnEventPublish'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['adviceOnEventPublish'],
    'exclude'   => true,
    'filter'    => false,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false),
    'sql'       => "varchar(255) NOT NULL default ''"
);




