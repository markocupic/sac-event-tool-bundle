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

$GLOBALS['TL_DCA']['tl_tour_difficulty_category'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'ctable'           => ['tl_tour_difficulty'],
		'doNotCopyRecords' => true,
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
			'all',
		],
	],
	'palettes' => [
		'default' => 'title',
	],
	'fields'   => [
		'id'     => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp' => [
			'sql' => "int(10) unsigned NOT NULL default 0",
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
	],
];
