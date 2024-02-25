<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\DC_Table;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_calendar_events_journey'] = [
	'config' => [
		'dataContainer'    => DC_Table::class,
		'enableVersioning' => true,
		'switchToEdit'     => true,
		'sql'              => [
			'keys' => [
				'id' => 'primary',
			],
		],
	],

	'list'     => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTED,
			'fields'      => ['title ASC'],
			'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
			'panelLayout' => 'filter;sort,search,limit',
		],
		'label'             => [
			'fields'      => ['title'],
			'showColumns' => true,
		],
		'global_operations' => [
			'all' => [
				'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset();"',
			],
		],
		'operations'        => [
			'edit'   => [
				'href' => 'act=edit',
				'icon' => 'edit.svg',
			],
			'copy'   => [
				'href' => 'act=copy',
				'icon' => 'copy.svg',
			],
			'delete' => [
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
			],
		],
	],
	'palettes' => [
		'default' => 'title,alias',
	],
	'fields'   => [
		'id'     => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp' => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'title'  => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'filter'    => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'alias'  => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'filter'    => true,
			'inputType' => 'select',
			'options'   => ['not-specified', 'car', 'public-transport'],
			'eval'      => ['mandatory' => true, 'unique' => true],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
	],
];
