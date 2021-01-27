<?php

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Table tl_calendar_events_instructor
 */
$GLOBALS['TL_DCA']['tl_calendar_events_instructor'] = array
(
	'config'      => array
	(
		'dataContainer'     => 'Table',
		'notCopyable'       => true,
		'ptable'            => 'tl_calendar_events',
		// Do not copy nor delete records, if an item has been deleted!
		'onload_callback'   => array
		(
		),
		'onsubmit_callback' => array(),
		'ondelete_callback' => array(),
		'sql'               => array
		(
			'keys' => array
			(
				'id'     => 'primary',
				'pid'    => 'index',
				'userId' => 'index'
			),
		),
	),
	// Buttons callback
	'edit'        => array(//'buttons_callback' => array(array('tl_calendar_events_instructor', 'buttonsCallback')),
	),

	// List
	'list'        => array
	(
		'sorting'           => array
		(
		),
		'label'             => array
		(
		),
		'global_operations' => array
		(
			'all' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
		),
		'operations'        => array
		(
			'edit'   => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor']['edit'],
				'href'  => 'act=edit',
				'icon'  => 'edit.svg',
			),
			'delete' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
			),
		),
	),

	// Palettes
	'palettes'    => array(),

	// Subpalettes
	'subpalettes' => array
	(),

	// Fields
	'fields'      => array
	(
		'id'               => array
		(
			'sql' => "int(10) unsigned NOT NULL auto_increment",
		),
		// Parent: tl_calendar_events.id
		'pid'              => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0",
		),
		'tstamp'           => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0",
		),
		// Parent tl_user.id
		'userId'           => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0",
		),
		'isMainInstructor' => array
		(
			'sql' => "char(1) NOT NULL default ''",
		),
	),
);
