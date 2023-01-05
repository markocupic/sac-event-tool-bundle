<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Extend default palette
PaletteManipulator::create()
	->addLegend('allowed_event_types_legend', 'calendars_legend', PaletteManipulator::POSITION_AFTER)
	->addField(['calendar_containers', 'calendar_containerp'], 'calendars_legend', PaletteManipulator::POSITION_PREPEND)
	->addField(['allowedEventTypes'], 'allowed_event_types_legend', PaletteManipulator::POSITION_PREPEND)
	->applyToPalette('default', 'tl_user_group');

$GLOBALS['TL_DCA']['tl_user_group']['fields']['calendar_containers'] = [
	'exclude'    => true,
	'inputType'  => 'checkbox',
	'foreignKey' => 'tl_calendar_container.title',
	'eval'       => ['multiple' => true],
	'sql'        => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user_group']['fields']['calendar_containerp'] = [
	'exclude'   => true,
	'inputType' => 'checkbox',
	'options'   => ['create', 'delete'],
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'eval'      => ['multiple' => true],
	'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user_group']['fields']['allowedEventTypes'] = [
	'exclude'    => true,
	'inputType'  => 'checkbox',
	'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
	'foreignKey' => 'tl_event_type.title',
	'sql'        => 'blob NULL',
	'eval'       => ['multiple' => true, 'mandatory' => false, 'tl_class' => 'clr'],
];
