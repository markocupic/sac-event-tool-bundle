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

$GLOBALS['TL_DCA']['tl_calendar_events_instructor'] = [
	'config' => [
		'dataContainer'     => DC_Table::class,
		'notCopyable'       => true,
		'ptable'            => 'tl_calendar_events',
		// Do not copy nor delete records, if an item has been deleted!
		'onload_callback'   => [],
		'onsubmit_callback' => [],
		'ondelete_callback' => [],
		'sql'               => [
			'keys' => [
				'id'     => 'primary',
				'pid'    => 'index',
				'userId' => 'index',
			],
		],
	],
	'list'   => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => ['userId ASC'],
			'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
			'panelLayout' => 'filter;sort,search,limit',
		],
		'label'             => [
			'fields'      => ['userId'],
			'showColumns' => true,
		],
		'global_operations' => [
			'all',
		],
	],
	'fields' => [
		'id'               => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		// Parent: tl_calendar_events.id
		'pid'              => [
			'sql' => 'int(10) unsigned NOT NULL default 0',
		],
		'tstamp'           => [
			'sql' => 'int(10) unsigned NOT NULL default 0',
		],
		// Parent tl_user.id
		'userId'           => [
			'sql' => 'int(10) unsigned NOT NULL default 0',
		],
		'isMainInstructor' => [
			'sql' => ['type' => 'boolean', 'default' => false],
		],
	],
];
