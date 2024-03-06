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

$GLOBALS['TL_DCA']['tl_tour_difficulty'] = [
	'config'   => [
		'dataContainer'      => DC_Table::class,
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
			'mode'            => DataContainer::MODE_PARENT,
			'fields'          => ['code ASC'],
			'flag'            => DataContainer::SORT_INITIAL_LETTER_ASC,
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
			'all',
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
