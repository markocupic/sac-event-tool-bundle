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
 * One row per (user, calendar) pair (unique index): the LAST notification
 * (sentAt, notificationId, openTaskCount, eventIds, delivered) and the total number
 * of notifications (reminderCount). Each further notification updates the row (see ReminderLog::logNotification()).
 * Used to determine when the last notification for a (user, calendar) pair was sent.
 *
 * Back end module "sac_instructor_post_event_task_reminder_log": READ ONLY.
 * Records can only be listed and shown, never created, edited, copied, moved or deleted.
 * The list columns are formatted in Feature\InstructorPostEventTaskReminder\DataContainer\ReminderLogTable.
 *
 * See docs/features/instructor-post-event-task-reminder.md
 */
$GLOBALS['TL_DCA']['tl_instructor_post_event_task_reminder_log'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'closed'           => true,
		'notDeletable'     => true,
		'notCreatable'     => true,
		'notEditable'      => true,
		'notCopyable'      => true,
		'notSortable'      => true,
		'doNotCopyRecords' => true,
		'sql'              => [
			'keys' => [
				'id'                       => 'primary',
				'userId,calendarId'        => 'unique',
			],
		],
	],
	'list'     => [
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
	// Prepared in case editing is enabled one day (currently notEditable)
	'palettes' => [
		'default' => '{log_legend},userId,calendarId,notificationId,sentAt,reminderCount,openTaskCount,eventIds,delivered',
	],
	'fields'   => [
		'id'             => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp'         => [
			'sql' => 'int(10) unsigned NOT NULL default 0',
		],
		// tl_user.id of the recipient (instructor or registration coordinator)
		'userId'         => [
			'filter'     => true,
			'inputType'  => 'select',
			'foreignKey' => 'tl_user.name',
			'eval'       => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
			'sql'        => 'int(10) unsigned NOT NULL default 0',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
		],
		'calendarId'     => [
			'filter'     => true,
			'sorting'    => true,
			'inputType'  => 'select',
			'foreignKey' => 'tl_calendar.title',
			'eval'       => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
			'sql'        => 'int(10) unsigned NOT NULL default 0',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
		],
		'notificationId' => [
			'inputType'  => 'select',
			'foreignKey' => 'tl_nc_notification.title',
			'eval'       => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
			'sql'        => 'int(10) unsigned NOT NULL default 0',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
		],
		'sentAt'         => [
			'sorting'   => true,
			'flag'      => DataContainer::SORT_DAY_BOTH,
			'inputType' => 'text',
			'eval'      => ['rgxp' => 'datim', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
			'sql'       => 'int(10) unsigned NOT NULL default 0',
		],
		// Total number of notifications sent to this user for this calendar (incremented on every notification)
		'reminderCount'  => [
			'sorting'   => true,
			'flag'      => DataContainer::SORT_BOTH,
			'inputType' => 'text',
			'eval'      => ['rgxp' => 'natural', 'maxlength' => 10, 'tl_class' => 'w50'],
			'sql'       => 'int(10) unsigned NOT NULL default 0',
		],
		'openTaskCount'  => [
			'sorting'   => true,
			'flag'      => DataContainer::SORT_BOTH,
			'inputType' => 'text',
			'eval'      => ['rgxp' => 'natural', 'maxlength' => 10, 'tl_class' => 'w50'],
			'sql'       => 'int(10) unsigned NOT NULL default 0',
		],
		// Comma separated list of tl_calendar_events.id
		'eventIds'       => [
			'inputType' => 'text',
			'eval'      => ['tl_class' => 'clr long'],
			'sql'       => 'text NULL',
		],
		'delivered'      => [
			'filter'    => true,
			'sorting'   => true,
			'flag'      => DataContainer::SORT_BOTH,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'clr m12'],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
	],
];
