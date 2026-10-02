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

use Contao\DC_Table;
use Contao\DataContainer;

/*
 * Log of the instructor post-event task reminder notifications.
 * One row per sent notification. Used to determine when the last notification
 * for a (user, calendar) pair was sent. The log is kept as history.
 *
 * Back end module "sac_instructor_post_event_task_reminder_log": READ ONLY.
 * Records can only be listed and shown, never created, edited, copied, moved or deleted.
 * The list columns are formatted in Feature\InstructorPostEventTaskReminder\DataContainer\ReminderLogTable.
 *
 * See docs/features/instructor-post-event-task-reminder.md
 */
$GLOBALS['TL_DCA']['tl_instructor_post_event_task_reminder_log'] = [
	'config' => [
		'dataContainer'    => DC_Table::class,
		'closed'           => true,
		'notCreatable'     => true,
		'notEditable'      => true,
		'notDeletable'     => true,
		'notCopyable'      => true,
		'notSortable'      => true,
		'doNotCopyRecords' => true,
		'sql'              => [
			'keys' => [
				'id'                       => 'primary',
				'userId,calendarId,sentAt' => 'index',
			],
		],
	],
	'list'   => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => ['sentAt'],
			'flag'        => DataContainer::SORT_DAY_DESC,
			'panelLayout' => 'filter;sort,limit',
		],
		'label'             => [
			'fields'      => ['sentAt', 'userId', 'calendarId', 'reminderCount', 'openTaskCount', 'eventIds', 'delivered'],
			'showColumns' => true,
		],
		// Read only: no global operations ("Edit multiple" etc.)
		'global_operations' => [],
		// Read only: only the "show" operation
		'operations'        => [
			'show',
		],
	],
	'fields' => [
		'id'             => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp'         => [
			'sql' => 'int(10) unsigned NOT NULL default 0',
		],
		// tl_user.id of the recipient (instructor or registration coordinator)
		'userId'         => [
			'filter'     => true,
			'foreignKey' => 'tl_user.name',
			'sql'        => 'int(10) unsigned NOT NULL default 0',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
		],
		'calendarId'     => [
			'filter'     => true,
			'sorting'    => true,
			'foreignKey' => 'tl_calendar.title',
			'sql'        => 'int(10) unsigned NOT NULL default 0',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
		],
		'notificationId' => [
			'foreignKey' => 'tl_nc_notification.title',
			'sql'        => 'int(10) unsigned NOT NULL default 0',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
		],
		'sentAt'         => [
			'sorting' => true,
			'flag'    => DataContainer::SORT_DAY_BOTH,
			'eval'    => ['rgxp' => 'datim'],
			'sql'     => 'int(10) unsigned NOT NULL default 0',
		],
		// The how-manieth notification for this (user, calendar) pair, including this one (1 = first)
		'reminderCount'  => [
			'sorting' => true,
			'flag'    => DataContainer::SORT_BOTH,
			'sql'     => 'int(10) unsigned NOT NULL default 0',
		],
		'openTaskCount'  => [
			'sorting' => true,
			'flag'    => DataContainer::SORT_BOTH,
			'sql'     => 'int(10) unsigned NOT NULL default 0',
		],
		// Comma separated list of tl_calendar_events.id
		'eventIds'       => [
			'sql' => 'text NULL',
		],
		'delivered'      => [
			'filter'    => true,
			'sorting'   => true,
			'flag'      => DataContainer::SORT_BOTH,
			'inputType' => 'checkbox',
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
	],
];
