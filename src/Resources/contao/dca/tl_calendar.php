<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\BackendUser;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Table config
$GLOBALS['TL_DCA']['tl_calendar']['config']['ptable'] = 'tl_calendar_container';

// List
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['mode'] = 4;
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['headerFields'] = ['title'];
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['disableGrouping'] = true;

if (BackendUser::getInstance()->isAdmin) {
    $GLOBALS['TL_DCA']['tl_calendar']['list']['operations']['cut'] = [
        'label' => &$GLOBALS['TL_LANG']['tl_calendar']['cut'],
        'href'  => 'act=paste&amp;mode=cut',
        'icon'  => 'cut.svg',
    ];
}

// Palettes
PaletteManipulator::create()
    ->addLegend(
        'event_type_legend',
        'protected_legend',
        PaletteManipulator::POSITION_BEFORE
    )
    ->addField(
        ['allowedEventTypes,adviceOnEventReleaseLevelChange,adviceOnEventPublish'],
        'event_type_legend',
        PaletteManipulator::POSITION_APPEND
    )
    ->applyToPalette(
        'default',
        'tl_calendar'
    );

// Fields
// pid
$GLOBALS['TL_DCA']['tl_calendar']['fields']['pid'] = [
    'foreignKey' => 'tl_calendar_container.title',
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
];

// Allowed event types
$GLOBALS['TL_DCA']['tl_calendar']['fields']['allowedEventTypes'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['allowedEventTypes'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'],
    'eval'      => ['multiple' => true, 'includeBlankOption' => false, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => true],
    'sql'       => 'blob NULL',
];

// adviceOnEventReleaseLevelChange
$GLOBALS['TL_DCA']['tl_calendar']['fields']['adviceOnEventReleaseLevelChange'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['adviceOnEventReleaseLevelChange'],
    'exclude'   => true,
    'filter'    => false,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => "varchar(255) NOT NULL default ''",
];

// adviceOnEventPublish
$GLOBALS['TL_DCA']['tl_calendar']['fields']['adviceOnEventPublish'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['adviceOnEventPublish'],
    'exclude'   => true,
    'filter'    => false,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => "varchar(255) NOT NULL default ''",
];
