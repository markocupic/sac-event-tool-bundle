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

use Contao\Config;
use Contao\Input;
use Markocupic\SacEventToolBundle\Dca\TlCalendarEventsStory;

/**
 * Table tl_calendar_events_story
 */
$GLOBALS['TL_DCA']['tl_calendar_events_story'] = array
(
	// Config
	'config'      => array
	(
		'dataContainer'     => 'Table',
		'enableVersioning'  => true,
		'notCopyable'       => true,
		'onload_callback'   => array
		(
			array(TlCalendarEventsStory::class, 'setPalettes'),
			array(TlCalendarEventsStory::class, 'deleteUnfinishedAndOldEntries'),
		),
		'sql'               => array
		(
			'keys' => array
			(
				'id'      => 'primary',
				'eventId' => 'index',
			),
		),
	),

	// List
	'list'        => array
	(
		'sorting'           => array
		(
			'mode'        => 2,
			'fields'      => array('eventStartDate DESC'),
			//'flag'        => 12,
			'panelLayout' => 'filter;sort,search',
		),
		'label'             => array
		(
			'fields'         => array('publishState', 'doPublishInClubMagazine', 'checkedByInstructor', 'title', 'authorName'),
			'showColumns'    => true,
			'label_callback' => array(TlCalendarEventsStory::class, 'addIcon'),
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
			'edit' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['edit'],
				'href'  => 'act=edit',
				'icon'  => 'edit.svg',
			),

			'delete' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
			),

			'show' => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['show'],
				'href'  => 'act=show',
				'icon'  => 'show.svg',
			),

			'exportArticle' => array(
				'label'           => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['exportArticle'],
				'href'            => 'action=exportArticle',
				'icon'            => 'bundles/markocupicsaceventtool/icons/docx.png',
				//'button_callback' => array(TlCalendarEventsStory::class, 'exportArticle'),
			),
		),
	),

	// Palettes
	'palettes'    => array
	(
		'__selector__' => array('doPublishInClubMagazine'),
		'default' => '
		{publishState_legend},publishState,checkedByInstructor;
		{author_legend},addedOn,sacMemberId,authorName;
		{event_legend},eventId,title,eventTitle,eventSubstitutionText,organizers,tourWaypoints,tourProfile,tourTechDifficulty,text,youtubeId,multiSRC;
		{tourInfoBox_legend},doPublishInClubMagazine',
	),

	// Subpalettes
	'subpalettes' => array
	(
		'doPublishInClubMagazine' => 'tourHighlights,tourPublicTransportInfo'
	),

	// Fields
	'fields'      => array
	(
		'id'                    => array
		(
			'sql' => "int(10) unsigned NOT NULL auto_increment",
		),
		'eventId'               => array
		(
			'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventId'],
			'foreignKey' => 'tl_calendar_events.title',
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
			'eval'       => array('readonly' => true),
		),
		'tstamp'                => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'publishState'          => array
		(
			'filter'    => true,
			'default'   => 1,
			'exclude' => true,
			'reference' => $GLOBALS['TL_LANG']['tl_calendar_events_story']['publishStateRef'],
			'inputType' => 'select',
			'options'   => array('1', '2', '3'),
			'eval'      => array('tl_class' => 'clr', 'submitOnChange' => true),
			'sql'       => "char(1) NOT NULL default '1'",
		),
		'doPublishInClubMagazine'          => array
		(
			'filter'    => true,
			'default'   => 1,
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'clr', 'submitOnChange' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'checkedByInstructor'          => array
		(
			'filter'    => true,
			'default'   => 1,
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'clr', 'submitOnChange' => false),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'authorName'            => array
		(
			'filter'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'eventTitle'            => array
		(
			'inputType' => 'text',
			'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'readonly' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'eventSubstitutionText' => array
		(
			'inputType' => 'text',
			'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'readonly' => true, 'maxlength' => 64, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'eventStartDate'        => array
		(
			'sorting' => true,
			'flag'    => 6,
			'sql'     => "int(10) unsigned NOT NULL default '0'",
		),
		'eventEndDate'          => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'eventDates'            => array
		(
			'sql' => "blob NULL",
		),
		'title'                 => array
		(
			'inputType' => 'text',
			'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'text'                  => array
		(
			'inputType' => 'textarea',
			'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'tl_class' => 'clr'),
			'sql'       => "mediumtext NULL",
		),
		'youtubeId'             => array
		(
			'inputType' => 'text',
			'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'sacMemberId'           => array
		(
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'multiSRC'              => array
		(
			'inputType' => 'fileTree',
			'eval'      => array('path'=> Config::get('SAC_EVT_EVENT_STORIES_UPLOAD_PATH') . '/' . Input::get('id'), 'doNotCopy' => true, 'isGallery' => true, 'extensions' => 'jpg,jpeg', 'multiple' => true, 'fieldType' => 'checkbox', 'orderField' => 'orderSRC', 'files' => true, 'mandatory' => false, 'tl_class' => 'clr'),
			'sql'       => "blob NULL",
		),
		'orderSRC'              => array
		(
			'eval'  => array('doNotCopy' => true),
			'sql'   => "blob NULL",
		),
		'organizers'            => array(
			'search'     => true,
			'filter'     => true,
			'sorting'    => true,
			'inputType'  => 'select',
			'foreignKey' => 'tl_event_organizer.title',
			'relation'   => array('type' => 'hasMany', 'load' => 'lazy'),
			'eval'       => array('multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'),
			'sql'        => "blob NULL",
		),
		'securityToken'         => array(
			'sql' => "varchar(255) NOT NULL default ''",
		),
		'addedOn'               => array
		(
			'default'   => time(),
			'flag'      => 8,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => array('rgxp' => 'date', 'mandatory' => true, 'doNotCopy' => false, 'datepicker' => true, 'tl_class' => 'w50 wizard'),
			'sql'       => "int(10) unsigned NULL",
		),
		'tourWaypoints'                  => array
		(
			'inputType' => 'textarea',
			'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'tl_class' => 'clr'),
			'sql'       => "mediumtext NULL",
		),
		'tourProfile'                  => array
		(
			'inputType' => 'textarea',
			'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'tl_class' => 'clr'),
			'sql'       => "mediumtext NULL",
		),
		'tourTechDifficulty'                  => array
		(
			'inputType' => 'textarea',
			'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'tl_class' => 'clr'),
			'sql'       => "mediumtext NULL",
		),
		'tourHighlights'                  => array
		(
			'inputType' => 'textarea',
			'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'tl_class' => 'clr'),
			'sql'       => "mediumtext NULL",
		),
		'tourPublicTransportInfo'                  => array
		(
			'inputType' => 'textarea',
			'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'tl_class' => 'clr'),
			'sql'       => "mediumtext NULL",
		),
	),
);
