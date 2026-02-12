<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\BackendUser;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Markocupic\SacEventToolBundle\Config\EventType;
use Contao\DataContainer;

// Table config
$GLOBALS['TL_DCA']['tl_calendar']['config']['ptable'] = 'tl_calendar_container';

// List
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['mode'] = DataContainer::MODE_PARENT;
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['headerFields'] = ['title'];
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['disableGrouping'] = true;

// Subpalettes
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['enableMaxEventReleaseLevelProtection'] = 'maxEventReleaseLevelTimeLimit';
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['enableEventStartDateValidation'] = 'validTimePeriodStart,validTimePeriodStop';

// Define selectors
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'enableMaxEventReleaseLevelProtection';
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'enableEventStartDateValidation';

// Palettes
PaletteManipulator::create()
	->addLegend('valid_time_period_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addLegend('event_release_level_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addLegend('event_type_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addLegend('event_reader_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addField(['enableEventStartDateValidation'], 'valid_time_period_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['enableMaxEventReleaseLevelProtection'], 'event_release_level_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['allowedEventTypes,notifyOnEventReleaseLevelChange,notifyOnEventPublish'], 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['userPortraitJumpTo'], 'event_reader_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('default', 'tl_calendar');

// Fields
$GLOBALS['TL_DCA']['tl_calendar']['fields']['pid'] = [
	'foreignKey' => 'tl_calendar_container.title',
	'sql'        => "int(10) unsigned NOT NULL default 0",
	'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['allowedEventTypes'] = [
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['allowedEventTypes'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'options'   => EventType::ALL,
	'eval'      => ['multiple' => true, 'includeBlankOption' => false, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => true],
	'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['notifyOnEventReleaseLevelChange'] = [
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['notifyOnEventReleaseLevelChange'],
	'exclude'   => true,
	'filter'    => false,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['notifyOnEventPublish'] = [
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['notifyOnEventPublish'],
	'exclude'   => true,
	'filter'    => false,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['userPortraitJumpTo'] = [
	'exclude'    => true,
	'inputType'  => 'pageTree',
	'foreignKey' => 'tl_page.title',
	'eval'       => ['fieldType' => 'radio'],
	'sql'        => 'int(10) unsigned NOT NULL default 0',
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['enableEventStartDateValidation'] = [
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['validTimePeriodStart'] = [
	'inputType' => 'text',
	'eval'      => ['mandatory' => true, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => "varchar(10) COLLATE ascii_bin NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['validTimePeriodStop'] = [
	'inputType' => 'text',
	'eval'      => ['mandatory' => true, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => "varchar(10) COLLATE ascii_bin NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['enableMaxEventReleaseLevelProtection'] = [
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['maxEventReleaseLevelTimeLimit'] = [
	'default'   => time(),
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'datim', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => 'bigint(20) unsigned NULL',
];
