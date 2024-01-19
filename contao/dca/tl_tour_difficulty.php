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

$GLOBALS['TL_DCA']['tl_tour_difficulty'] = [
	'config'   => [
		'dataContainer'      => 'Table',
		'ptable'             => 'tl_tour_difficulty_category',
		'doNotCopyRecords'   => true,
		'enableVersioning'   => true,
		'switchToEdit'       => true,
		'doNotDeleteRecords' => true,
		'sql'                => [
			'keys' => [
				'id' => 'primary',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'            => 4,
			'fields'          => ['code ASC'],
			'flag'            => 1,
			'panelLayout'     => 'filter;sort,search,limit',
			'headerFields'    => ['level', 'title'],
			'disableGrouping' => true,
		],
		'label'             => [
			'fields'      => [
				'title',
				'shortcut',
			],
			'showColumns' => true,
		],
		'global_operations' => [
			'all' => [
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
		'default' => 'code,shortcut,title,description',
	],
	'fields'   => [
		'id'          => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'pid'         => [
			'foreignKey' => 'tl_tour_difficulty_category.title',
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
		],
		'sorting'     => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'tstamp'      => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'shortcut'    => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'title'       => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'code'        => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'description' => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'textarea',
			'eval'      => ['mandatory' => true],
			'sql'       => 'text NULL',
		],
	],
];
