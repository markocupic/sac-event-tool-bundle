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
 * for a (user, calendar) pair was sent. No back end module, the log is kept as history.
 *
 * See docs/features/instructor-post-event-task-reminder.md
 */
$GLOBALS['TL_DCA']['tl_instructor_post_event_task_reminder_log'] = [
	'config' => [
		'dataContainer'    => DC_Table::class,
		'notCopyable'      => true,
		'notEditable'      => true,
		'closed'           => true,
		'doNotCopyRecords' => true,
		'sql'              => [
			'keys' => [
				'id'                       => 'primary',
				'userId,calendarId,sentAt' => 'index',
			],
		],
	],
	'list'   => [
		'sorting' => [
			'mode'   => DataContainer::MODE_SORTED,
			'fields' => ['sentAt DESC'],
		],
		'label'   => [
			'fields'      => ['sentAt', 'userId', 'calendarId', 'openTaskCount', 'delivered'],
			'showColumns' => true,
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
			'foreignKey' => 'tl_user.name',
			'sql'        => 'int(10) unsigned NOT NULL default 0',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
		],
		'calendarId'     => [
			'foreignKey' => 'tl_calendar.title',
			'sql'        => 'int(10) unsigned NOT NULL default 0',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
		],
		'notificationId' => [
			'sql' => 'int(10) unsigned NOT NULL default 0',
		],
		'sentAt'         => [
			'eval' => ['rgxp' => 'datim'],
			'sql'  => 'int(10) unsigned NOT NULL default 0',
		],
		'openTaskCount'  => [
			'sql' => 'int(10) unsigned NOT NULL default 0',
		],
		// Comma separated list of tl_calendar_events.id
		'eventIds'       => [
			'sql' => 'text NULL',
		],
		'delivered'      => [
			'sql' => ['type' => 'boolean', 'default' => false],
		],
	],
];
