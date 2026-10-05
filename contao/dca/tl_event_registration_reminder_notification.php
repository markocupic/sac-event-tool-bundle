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

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Log of the event registration reminder: one row per (user, calendar) pair with the last reminder.
 *
 * Back end module "event_registration_reminder_notification": READ ONLY.
 *
 * See docs/features/event-registration-reminder.md
 */
$GLOBALS['TL_DCA']['tl_event_registration_reminder_notification'] = [
	'config'   => [
		'dataContainer' => DC_Table::class,
		'closed'        => true,
		'notCreatable'  => true,
		'notEditable'   => true,
		'notDeletable'  => false,
		'notCopyable'   => true,
		'notSortable'   => true,
		'sql'           => [
			'keys' => [
				'id'                      => 'primary',
				'dateAdded,user,calendar' => 'index',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => ['dateAdded'],
			'flag'        => DataContainer::SORT_DAY_DESC,
			'panelLayout' => 'filter;sort,search,limit',
		],
		'label'             => [
			'fields'      => ['dateAdded', 'user', 'calendar', 'title'],
			'showColumns' => true,
		],
		'global_operations' => [],
		'operations'        => [
			'show',
			'delete',
		],
	],
	'palettes' => [
		'default' => '{first_legend},title,user,calendar,dateAdded,prevReminderTstamp,history',
	],
	'fields'   => [
		'id'                 => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp'             => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'title'              => [
			'inputType' => 'text',
			'search'    => true,
			'sql'       => "varchar(512) NOT NULL default ''",
		],
		'dateAdded'          => [
			'inputType' => 'text',
			'sorting'   => true,
			'flag'      => DataContainer::SORT_DAY_DESC,
			'eval'      => ['rgxp' => 'datim'],
			'sql'       => 'int(11) unsigned NOT NULL default 0',
		],
		'prevReminderTstamp' => [
			'inputType' => 'text',
			'eval'      => ['rgxp' => 'datim'],
			'sql'       => 'int(11) unsigned NOT NULL default 0',
		],
		'user'               => [
			'inputType'  => 'select',
			'filter'     => true,
			'foreignKey' => 'tl_user.name',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
			'sql'        => 'int(10) unsigned NOT NULL default 0',
		],
		'calendar'           => [
			'inputType'  => 'select',
			'filter'     => true,
			'foreignKey' => 'tl_calendar.title',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
			'sql'        => 'int(10) unsigned NOT NULL default 0',
		],
		'history'            => [
			'inputType' => 'textarea',
			'sql'       => 'text NULL',
		],
	],
];
