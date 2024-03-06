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

$GLOBALS['TL_DCA']['tl_event_release_level_policy_package'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'ctable'           => ['tl_event_release_level_policy'],
		'switchToEdit'     => true,
		'enableVersioning' => true,
		'sql'              => [
			'keys' => [
				'id' => 'primary',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTED,
			'fields'      => ['title'],
			'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
			'panelLayout' => 'filter;search,limit',
		],
		'label'             => [
			'fields' => ['title'],
			'format' => '%s',
		],
		'global_operations' => [
			'all',
		],
	],
	'palettes' => [
		'default' => '{title_legend},title;',
	],
	'fields'   => [
		'id'     => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp' => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'title'  => [
			'inputType' => 'text',
			'exclude'   => true,
			'search'    => true,
			'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
			'eval'      => ['mandatory' => true, 'rgxp' => 'alnum', 'maxlength' => 64, 'tl_class' => 'w50'],
			'sql'       => 'varchar(64) NULL',
		],
	],
];
