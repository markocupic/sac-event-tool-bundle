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

$GLOBALS['TL_DCA']['tl_calendar_container'] = [
	// Config
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'ctable'           => ['tl_calendar'],
		'switchToEdit'     => true,
		'enableVersioning' => true,
		'sql'              => [
			'keys' => [
				'id' => 'primary',
			],
		],
	],
	// List
	'list'     => [
		'sorting'           => [
			'mode'            => DataContainer::MODE_SORTED,
			'fields'          => ['title'],
			'flag'            => DataContainer::SORT_INITIAL_LETTER_DESC,
			'panelLayout'     => 'filter;search,limit',
			'disableGrouping' => true,
		],
		'label'             => [
			'fields' => ['title'],
			'format' => '%s',
		],
		'global_operations' => [
			'all' => [
				'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			],
		],
		'operations'        => [
			'editheader' => [
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['editheader'],
				'href'  => 'act=edit',
				'icon'  => 'edit.svg',
			],
			'edit'       => [
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['edit'],
				'href'  => 'table=tl_calendar',
				'icon'  => 'children.svg',
			],
			'copy'       => [
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['copy'],
				'href'  => 'act=copy',
				'icon'  => 'copy.svg',
			],
			'delete'     => [
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_container']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
			],
			'show'       => [
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['show'],
				'href'  => 'act=show',
				'icon'  => 'show.svg',
			],
		],
	],
	'palettes' => [
		'__selector__' => [],
		'default'      => '{title_legend},title',
	],
	'fields'   => [
		'id'     => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp' => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'title'  => [
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_container']['title'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
	],
];
