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
 * Scheduled feedback requests: one row per (registration, sending date).
 * Written when the participation is confirmed, claimed by the cron (dispatched = 1) and deleted after sending.
 *
 * See docs/features/event-feedback.md
 */
$GLOBALS['TL_DCA']['tl_event_feedback_reminder'] = [
	'config'   => [
		'dataContainer' => DC_Table::class,
		'ptable'        => 'tl_calendar_events_member',
		'closed'        => true,
		'sql'           => [
			'keys' => [
				'id'                 => 'primary',
				'uuid'               => 'index',
				'uuid,executionDate' => 'unique',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => ['executionDate'],
			'flag'        => DataContainer::SORT_DAY_ASC,
			'panelLayout' => 'filter;sort,search,limit',
		],
		'label'             => [
			'fields'      => ['executionDate', 'pid', 'dispatched', 'expiration'],
			'showColumns' => true,
		],
		'global_operations' => [
			'all',
		],
	],
	'palettes' => [
		'default' => '{title_legend},uuid,dateAdded,executionDate,expiration,dispatched,dispatchTime',
	],
	'fields'   => [
		'id'            => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'pid'           => [
			'foreignKey' => 'tl_calendar_events_member.CONCAT(firstname, " ", lastname, IF(sacMemberId > 0, CONCAT(" [", sacMemberId, "]"), ""))',
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
			'sql'        => 'int(10) unsigned NOT NULL default 0',
		],
		'tstamp'        => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'dateAdded'     => [
			'inputType' => 'text',
			'sorting'   => true,
			'flag'      => DataContainer::SORT_DAY_DESC,
			'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
			'sql'       => 'int(10) unsigned NOT NULL default 0',
		],
		'uuid'          => [
			'exclude'   => true,
			'inputType' => 'text',
			'search'    => true,
			'eval'      => ['mandatory' => true, 'readonly' => true, 'tl_class' => 'w50'],
			'sql'       => "char(36) NOT NULL default ''",
		],
		'dispatched'    => [
			'inputType' => 'checkbox',
			'filter'    => true,
			'eval'      => ['tl_class' => 'clr'],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'dispatchTime'  => [
			'inputType' => 'text',
			'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
			'sql'       => "varchar(11) NOT NULL default ''",
		],
		'executionDate' => [
			'inputType' => 'text',
			'sorting'   => true,
			'flag'      => DataContainer::SORT_DAY_ASC,
			'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
			'sql'       => "varchar(11) NOT NULL default ''",
		],
		'expiration'    => [
			'inputType' => 'text',
			'sorting'   => true,
			'flag'      => DataContainer::SORT_DAY_DESC,
			'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
			'sql'       => "varchar(11) NOT NULL default ''",
		],
	],
];
