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

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\Date;
use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Config\EventExecutionState;
use Markocupic\SacEventToolBundle\Config\EventMountainGuide;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\DataContainer\CalendarEvents;

// Keys
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['mountainguide'] = 'index';
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['eventState'] = 'index';
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['eventReleaseLevel'] = 'index';

// ctable
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['ctable'][] = 'tl_calendar_events_instructor';

// List
// Sortierung nach Datum neuste Events zu letzt
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['disableGrouping'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['fields'] = ['startDate ASC'];

// Subpalettes
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['allowDeregistration'] = 'deregistrationLimit';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['addGallery'] = 'multiSRC';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['setRegistrationPeriod'] = 'registrationStartDate,registrationEndDate';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['addMinAndMaxMembers'] = 'minMembers,maxMembers';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['customizeEventRegistrationConfirmationEmailText'] = 'customEventRegistrationConfirmationEmailText';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['addIban'] = 'iban, ibanBeneficiary';

// Reset palettes
$strLegends = '
{tour_report_legend:hide};{event_type_legend};
{title_legend:hide};{tech_difficulty_legend:hide};{date_legend:hide};{recurring_legend:hide};{details_legend:hide};
{min_max_member_legend:hide};{registration_legend:hide};{deregistration_legend:hide};{sign_up_form_legend:hide};{event_registration_confirmation_legend:hide};
{image_legend:hide};{gallery_legend:hide};{broschuere_legend:hide};
{enclosure_legend:hide};{source_legend:hide};{expert_legend:hide}
';

$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $strLegends;
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][EventType::TOUR] = $strLegends;
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][EventType::LAST_MINUTE_TOUR] = $strLegends;
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][EventType::COURSE] = $strLegends;
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][EventType::GENERAL_EVENT] = $strLegends;
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['tour_report'] = $strLegends;

// Define selectors
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'allowDeregistration';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'addMinAndMaxMembers';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'addGallery';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'addIban';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'setRegistrationPeriod';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'customizeEventRegistrationConfirmationEmailText';

// Default palettes (define it for any case, f.ex edit all mode)
// Put here all defined fields in the dca
PaletteManipulator::create()
	->addField(['eventType'], 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['singleSRCBroschuere'], 'broschuere_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['title', 'teaser', 'alias', 'courseId', 'eventState', 'rescheduledEventDate', 'author', 'instructor', 'mountainguide', 'organizers', 'tourType'], 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['tourTechDifficulty'], 'tech_difficulty_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['suitableForBeginners', 'courseLevel', 'courseTypeLevel0', 'courseTypeLevel1'], 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['eventDates', 'durationInfo'], 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addTime', 'startTime', 'endTime'], 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['isRecurringEvent'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['recurring'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['location', 'coordsCH1903', 'journey', 'tourDetailText', 'tourProfile', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous', 'linkSacRoutePortal', 'addIban'], 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['terms', 'issues'], 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addMinAndMaxMembers'], 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'], 'registration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['allowDeregistration'], 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['askForAhvNumber'], 'sign_up_form_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['customizeEventRegistrationConfirmationEmailText'], 'event_registration_confirmation_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addImage'], 'image_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addGallery'], 'gallery_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addEnclosure'], 'enclosure_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['source'], 'source_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['cssClass', 'noComments'], 'expert_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('default', 'tl_calendar_events');

// Tour and lastMinuteTour palette
PaletteManipulator::create()
	->addField(['eventType'], 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['title', 'teaser', 'alias', 'eventState', 'rescheduledEventDate', 'author', 'instructor', 'mountainguide', 'organizers', 'tourType'], 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['suitableForBeginners', 'tourTechDifficulty'], 'tech_difficulty_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['eventDates', 'durationInfo'], 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['isRecurringEvent'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['recurring'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['location', 'coordsCH1903', 'journey', 'tourDetailText', 'tourProfile', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous', 'linkSacRoutePortal', 'addIban'], 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addMinAndMaxMembers'], 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'], 'registration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['allowDeregistration'], 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['askForAhvNumber'], 'sign_up_form_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['customizeEventRegistrationConfirmationEmailText'], 'event_registration_confirmation_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addGallery'], 'gallery_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addEnclosure'], 'enclosure_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['source'], 'source_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['cssClass', 'noComments'], 'expert_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette(EventType::TOUR, 'tl_calendar_events')
	->applyToPalette(EventType::LAST_MINUTE_TOUR, 'tl_calendar_events');

// generalEvent
// same like tour but remove Fields: 'suitableForBeginners', 'tourTechDifficulty', 'tourProfile', 'mountainguide', 'tourDetailText', 'requirements'
// Add field: 'generalEventDetailText'
PaletteManipulator::create()
	->addField(['eventType'], 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['title', 'teaser', 'alias', 'eventState', 'rescheduledEventDate', 'author', 'instructor', 'organizers', 'tourType'], 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['eventDates', 'durationInfo'], 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['isRecurringEvent'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['recurring'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['location', 'coordsCH1903', 'journey', 'generalEventDetailText', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous', 'addIban'], 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addMinAndMaxMembers'], 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'], 'registration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['allowDeregistration'], 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['askForAhvNumber'], 'sign_up_form_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['customizeEventRegistrationConfirmationEmailText'], 'event_registration_confirmation_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addGallery'], 'gallery_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addEnclosure'], 'enclosure_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['source'], 'source_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['cssClass', 'noComments'], 'expert_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette(EventType::GENERAL_EVENT, 'tl_calendar_events');

// Course palette
PaletteManipulator::create()
	->addField(['eventType'], 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['singleSRCBroschuere'], 'broschuere_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['title', 'teaser', 'alias', 'courseId', 'eventState', 'rescheduledEventDate', 'author', 'instructor', 'mountainguide', 'organizers', 'courseLevel', 'courseTypeLevel0', 'courseTypeLevel1'], 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['eventDates', 'durationInfo'], 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['isRecurringEvent'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['recurring'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['terms', 'issues', 'location', 'coordsCH1903', 'journey', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous', 'addIban'], 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addMinAndMaxMembers'], 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'], 'registration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['allowDeregistration'], 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['askForAhvNumber'], 'sign_up_form_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['customizeEventRegistrationConfirmationEmailText'], 'event_registration_confirmation_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addImage'], 'image_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addGallery'], 'gallery_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['addEnclosure'], 'enclosure_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['source'], 'source_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['cssClass', 'noComments'], 'expert_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette(EventType::COURSE, 'tl_calendar_events');

// Tour report palette
PaletteManipulator::create()
	->addField(['eventState', 'rescheduledEventDate', 'executionState', 'eventSubstitutionText', 'tourWeatherConditions', 'tourAvalancheConditions', 'tourSpecialIncidents', 'eventReportAdditionalNotices'], 'tour_report_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('tour_report', 'tl_calendar_events');

// Global operations
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['plus1year'] = [
	'href'                   => 'transformDates=+52weeks',
	'class'                  => 'global_op_icon_class',
	'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/calendar-plus-regular.svg',
	'attributes'             => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['plus1yearConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()" accesskey="e"',
	'custom_glob_op'         => true,
	'custom_glob_op_options' => ['add_to_menu_group' => 'super', 'sorting' => 10],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year'] = [
	'href'                   => 'transformDates=-52weeks',
	'class'                  => 'global_op_icon_class',
	'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/calendar-minus-regular.svg',
	'attributes'             => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['minus1yearConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()" accesskey="e"',
	'custom_glob_op'         => true,
	'custom_glob_op_options' => ['add_to_menu_group' => 'super', 'sorting' => 8],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['onloadCallbackExportCalendar'] = [
	'href'                   => 'action=onloadCallbackExportCalendar',
	'class'                  => 'header_icon',
	'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/file-excel-regular.svg',
	'attributes'             => 'onclick="Backend.getScrollOffset()" accesskey="e"',
	'custom_glob_op'         => true,
	'custom_glob_op_options' => ['add_to_menu_group' => 'super', 'sorting' => -10],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['onloadCallbackExportCalendar'] = [
	'href'                   => 'action=onloadCallbackExportCalendar',
	'class'                  => 'header_icon',
	'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/file-excel-regular.svg',
	'attributes'             => 'onclick="Backend.getScrollOffset()" accesskey="e"',
	'custom_glob_op'         => true,
	'custom_glob_op_options' => ['add_to_menu_group' => 'super', 'sorting' => -10],
];

// Operations
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['toggle']['showInHeader'] = false;

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['preview'] = [
	'href'       => 'action=preview', // use a button callback to generate the url
	'attributes' => 'target="_blank"',
	'icon'       => Bundle::ASSET_DIR.'/icons/fontawesome/default/presentation-screen-solid.svg',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['registrations'] = [
	'href' => 'table=tl_calendar_events_member',
	'icon' => Bundle::ASSET_DIR.'/icons/fontawesome/default/people-group-solid.svg',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['releaseLevelPrev'] = [
	'href' => 'action=releaseLevelPrev', // use a button callback to generate the url
	'icon' => Bundle::ASSET_DIR.'/icons/fontawesome/default/square-arrow-down-solid.svg',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['releaseLevelNext'] = [
	'href' => 'action=releaseLevelNext', // use a button callback to generate the url
	'icon' => Bundle::ASSET_DIR.'/icons/fontawesome/default/square-arrow-up-solid.svg',
];

// Override DCA: tl_class
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['location']['eval']['tl_class'] = 'm12 clr';
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['title']['eval']['tl_class'] = 'm12 clr';
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['author']['eval']['tl_class'] = 'm12 clr';

// Override DCA: startTime
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['startTime']['sorting'] = false;

// Override DCA: startDate
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['startDate']['sorting'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['startDate']['flag'] = DataContainer::SORT_DAY_ASC;

// Override DCA: teaser
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['teaser']['eval']['rte'] = null;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['teaser']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['teaser']['eval']['maxlength'] = 520;

// Add new field courseId
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseId'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => false, 'tl_class' => 'm12 clr'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

// Add new field suitableForBeginners
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['suitableForBeginners'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field isRecurringEvent
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['isRecurringEvent'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field eventType
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventType'] = [
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'eval'      => ['submitOnChange' => true, 'includeBlankOption' => true, 'doNotShow' => false, 'mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => "varchar(32) NOT NULL default ''",
];

// Add new field mountainguide
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['mountainguide'] = [
	'exclude'   => true,
	'filter'    => true,
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events']['mountainguide_reference'],
	'inputType' => 'select',
	'eval'      => ['tl_class' => 'm12 clr'],
	'options'   => EventMountainGuide::ALL,
	// Attention! This field is not of type boolean
	'sql'       => "int(1) unsigned NOT NULL default 0",
];

// Add new field mainInstructor
// Hauptleiter (main instructor) is set automatically (the first instructor in the list is set as "Hauptleiter"
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['mainInstructor'] = [
	'exclude'    => true,
	'search'     => true,
	'filter'     => true,
	'sorting'    => true,
	'inputType'  => 'radio',
	'flag'       => DataContainer::SORT_ASC,
	'foreignKey' => 'tl_user.name',
	'eval'       => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr'],
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
];

// Add new field instructor
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['instructor'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'multiColumnWizard',
	// Save instructors in a child table tl_calendar_events_instructors
	'eval'      => [
		'tl_class'     => 'mcwColumnCount_1 m12 clr',
		'helpWizard'   => false,
		'mandatory'    => true,
		'columnFields' => [
			'instructorId' => [
				'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['instructorId'],
				'exclude'          => true,
				'inputType'        => 'select',
				'default'          => BackendUser::getInstance()->id,
				'filter'           => true,
				'relation'         => ['type' => 'hasOne', 'load' => 'eager'],
				'options_callback' => [System::getContainer()->get(CalendarEvents::class), 'listInstructors'],
				'eval'             => ['style' => 'width:350px', 'mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'multiple' => false, 'tl_class' => 'hidelabel'],
			],
		],
	],
	'sql'       => 'blob NULL',
];

// Add new field terms
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['terms'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field issues
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['issues'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field requirements
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['requirements'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => true, 'maxlength' => 300, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field leistungen
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['leistungen'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => false, 'maxlength' => 200, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field courseLevel
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel'] = [
	'exclude'   => true,
	'search'    => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'],
	'eval'      => ['mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => 'int(10) unsigned NULL',
];

// Add new field courseTypeLevel0
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0'] = [
	'exclude'   => true,
	'search'    => true,
	'filter'    => true,
	'inputType' => 'select',
	'eval'      => ['submitOnChange' => true, 'includeBlankOption' => true, 'multiple' => false, 'mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => "int(10) unsigned NOT NULL default '0'",
];

// Add new field courseTypeLevel1
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1'] = [
	'exclude'    => true,
	'search'     => true,
	'filter'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_course_sub_type.name',
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
	'eval'       => ['multiple' => false, 'mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'        => "int(10) unsigned NOT NULL default '0'",
];

// Add new field organizers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['organizers'] = [
	'exclude'    => true,
	'search'     => true,
	'filter'     => true,
	'sorting'    => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_event_organizer.title',
	'eval'       => ['multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'm12 clr'],
	'sql'        => 'blob NULL',
];

// Add new field equipment
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['equipment'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => false, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field durationInfo
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['durationInfo'] = [
	'search'    => true,
	'filter'    => true,
	'exclude'   => true,
	'inputType' => 'select',
	'eval'      => ['includeBlankOption' => true, 'mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => "varchar(32) NOT NULL default ''",
];

// Add new field addMinMaxMembers
// Add minimum and maximum members (set up to "true" by default, when creating a new event)
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addMinAndMaxMembers'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => true],
];

// Add new field minMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['minMembers'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'digit', 'mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => 'int(3) unsigned NULL',
];

// Add new field maxMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['maxMembers'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'digit', 'mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => 'int(3) unsigned NULL',
];

// Add new field bookingEvent
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['bookingEvent'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => false, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field miscellaneous
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['miscellaneous'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => false, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field addIban
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addIban'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'mandatory' => false, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field iban
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['iban'] = [
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => true, 'rgxp' => 'alnum', 'tl_class' => 'm12 clr'],
	'sql'       => "varchar(32) NOT NULL default ''",
];

// Add new field ibanBeneficiary
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ibanBeneficiary'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'search'    => true,
	'eval'      => ['mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field eventDates
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventDates'] = [
	'exclude'   => true,
	'inputType' => 'multiColumnWizard',
	'eval'      => [
		'tl_class'        => 'mcwColumnCount_1 m12 clr',
		'columnsCallback' => [CalendarEvents::class, 'listFixedDates'],
		'buttons'         => ['up' => false, 'down' => false],
		'mandatory'       => true,
	],
	'sql'       => 'blob NULL',
];

// Add new field eventState
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventState'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => EventState::ALL,
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
	'eval'      => ['mandatory' => false, 'submitOnChange' => true, 'includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_calendar_events']['noSpecificEventState'], 'doNotShow' => false, 'tl_class' => 'm12 clr'],
	'sql'       => "varchar(64) NOT NULL default ''",
];

// Add new field rescheduledEventDate
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['rescheduledEventDate'] = [
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'date', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => true, 'tl_class' => 'm12 clr wizard'],
	'sql'       => "bigint(20) unsigned NULL",
];

// Add new field meetingPoint
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['meetingPoint'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'm12 clr', 'mandatory' => false],
	'sql'       => 'text NULL',
];

// Add new field singleSRCBroschuere
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['singleSRCBroschuere'] = [
	'exclude'   => true,
	'inputType' => 'fileTree',
	'eval'      => ['filesOnly' => true, 'extensions' => Config::get('validImageTypes'), 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'm12 clr'],
	'sql'       => 'binary(16) NULL',
];

// Add new field askForAhvNumber
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['askForAhvNumber'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field generateMainInstructorContactDataFromDb
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['generateMainInstructorContactDataFromDb'] = [
	'filter'    => true,
	'sorting'   => true,
	'exclude'   => true,
	'default'   => BackendUser::getInstance()->generateMainInstructorContactDataFromDb,
	'inputType' => 'checkbox',
	'eval'      => ['tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field disableOnlineRegistration
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['disableOnlineRegistration'] = [
	'filter'    => true,
	'sorting'   => true,
	'exclude'   => true,
	'default'   => (int)BackendUser::getInstance()->disableOnlineRegistration,
	'inputType' => 'checkbox',
	'eval'      => ['tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field registrationGoesTo
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationGoesTo'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'eval'      => ['multiple' => false, 'chosen' => false, 'includeBlankOption' => true, 'tl_class' => 'm12 clr'],
	'sql'       => "int(10) unsigned NOT NULL default '0'",
];

// Add new field setRegistrationPeriod
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['setRegistrationPeriod'] = [
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field registrationStartDate
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationStartDate'] = [
	'default'   => strtotime(Date::parse('Y-m-d')),
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'date', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => 'bigint(20) unsigned NULL',
];

// Add new field registrationEndDate
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationEndDate'] = [
	'default'   => strtotime(Date::parse('Y-m-d')) + (2 * 24 * 3600) - 60,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'datim', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => 'bigint(20) unsigned NULL',
];

// Add new field allowDeregistration
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['allowDeregistration'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field deregistrationLimit
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['deregistrationLimit'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => range(1, 720),
	'eval'      => ['rgxp' => 'natural', 'nospace' => true, 'tl_class' => 'm12 clr'],
	'sql'       => "int(10) unsigned NOT NULL default '0'",
];

// Add new field addGallery
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addGallery'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field multiSRC
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['multiSRC'] = [
	'exclude'   => true,
	'inputType' => 'fileTree',
	'eval'      => ['multiple' => true, 'extensions' => 'jpg,jpeg,png', 'fieldType' => 'checkbox', 'orderField' => 'orderSRC', 'files' => true, 'filesOnly' => true, 'mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => 'blob NULL',
];

// Add new field orderSRC
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['orderSRC'] = [
	'sql' => 'blob NULL',
];

// Add new field tour type
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType'] = [
	'exclude'    => true,
	'filter'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_tour_type.title',
	'relation'   => ['type' => 'hasMany', 'load' => 'eager'],
	'eval'       => ['multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'm12 clr'],
	'sql'        => 'blob NULL',
];

// Add new field tourTechDifficulty
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourTechDifficulty'] = [
	'exclude'   => true,
	'inputType' => 'multiColumnWizard',
	'eval'      => [
		'mandatory'    => true,
		'tl_class'     => 'mcwColumnCount_2 m12 clr',
		'columnFields' => [
			'tourTechDifficultyMin' => [
				'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMin'],
				'exclude'          => true,
				'inputType'        => 'select',
				'reference'        => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'options_callback' => [CalendarEvents::class, 'getTourDifficulties'],
				'relation'         => [
					'type' => 'hasMany',
					'load' => 'eager',
				],
				'foreignKey'       => 'tl_tour_difficulty.shortcut',
				'eval'             => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'hidelabel'],
			],
			'tourTechDifficultyMax' => [
				'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMax'],
				'exclude'          => true,
				'inputType'        => 'select',
				'reference'        => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'options_callback' => [CalendarEvents::class, 'getTourDifficulties'],
				'relation'         => ['type' => 'hasMany', 'load' => 'eager'],
				'foreignKey'       => 'tl_tour_difficulty.shortcut',
				'eval'             => ['mandatory' => false, 'includeBlankOption' => true, 'tl_class' => 'hidelabel'],
			],
		],
	],
	'sql'       => 'blob NULL',
];

// Add new field tourProfile
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourProfile'] = [
	'exclude'   => true,
	'inputType' => 'multiColumnWizard',
	'eval'      => [
		'mandatory'    => false,
		'tl_class'     => 'mcwColumnCount_4 m12 clr',
		'columnFields' => [
			'tourProfileAscentMeters'  => [
				'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentMeters'],
				'inputType' => 'text',
				'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'eval'      => ['datepicker' => false, 'rgxp' => 'natural', 'mandatory' => false, 'tl_class' => 'hidelabel'],
			],
			'tourProfileAscentTime'    => [
				'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentTime'],
				'inputType' => 'text',
				'eval'      => ['datepicker' => false, 'rgxp' => 'digit', 'mandatory' => false, 'tl_class' => 'hidelabel'],
			],
			'tourProfileDescentMeters' => [
				'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentMeters'],
				'inputType' => 'text',
				'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'eval'      => ['datepicker' => false, 'rgxp' => 'natural', 'mandatory' => false, 'tl_class' => 'hidelabel'],
			],
			'tourProfileDescentTime'   => [
				'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentTime'],
				'inputType' => 'text',
				'eval'      => ['datepicker' => false, 'rgxp' => 'digit', 'mandatory' => false, 'tl_class' => 'hidelabel'],
			],
		],
	],
	'sql'       => 'blob NULL',
];

// Add new field tourDetailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourDetailText'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	/** @todo maxlength 700 */
	'eval'      => ['mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field generalEventDetailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['generalEventDetailText'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => false, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field eventReleaseLevel
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel'] = [
	'exclude'    => true,
	'filter'     => true,
	'sorting'    => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_event_release_level_policy.title',
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
	'eval'       => ['mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'        => "int(10) unsigned NOT NULL default '0'",
];

if (!Input::get('act') || 'select' === Input::get('act')) {
	// Display the field correctly in the filter menu
	$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel']['options_callback'] = null;
}

// Add new field customizeEventRegistrationConfirmationEmailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['customizeEventRegistrationConfirmationEmailText'] = [
	'exclude'   => true,
	'filter'    => false,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Add new field customEventRegistrationConfirmationEmailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['customEventRegistrationConfirmationEmailText'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => false, 'preserveTags' => true, 'allowHtml' => true, 'decodeEntities' => false, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// ****** Tour report fields **********:

// Add new field filledInEventReportForm
// This field is autofilled, if a user has filled in the event report
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['filledInEventReportForm'] = [
	'exclude' => false,
	'eval'    => ['doNotShow' => true, 'tl_class' => 'm12 clr'],
	'sql'     => ['type' => 'boolean', 'default' => false],
];

// Add new field executionState
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['executionState'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => EventExecutionState::ALL,
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
	'eval'      => ['submitOnChange' => true, 'includeBlankOption' => true, 'doNotShow' => true, 'tl_class' => 'm12 clr', 'mandatory' => true],
	'sql'       => "varchar(64) NOT NULL default ''",
];

// Add new field coordsCH1903
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['coordsCH1903'] = [
	'exclude'   => true,
	'search'    => true,
	'sorting'   => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'm12 clr'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

// Add new field journey
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['journey'] = [
	'exclude'    => true,
	'filter'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_calendar_events_journey.title',
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
	'eval'       => ['multiple' => false, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'm12 clr'],
	'sql'        => "varchar(255) NOT NULL default ''",
];

// Add new field linkSacRoutePortal
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['linkSacRoutePortal'] = [
	'exclude'   => true,
	'search'    => true,
	'sorting'   => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'm12 clr'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

// Add new field eventSubstitutionText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventSubstitutionText'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field tourWeatherConditions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourWeatherConditions'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => true, 'tl_class' => 'm12 clr'],
	'sql'       => 'text NULL',
];

// Add new field tourAvalancheConditions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourAvalancheConditions'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['SAC-EVENT-TOOL-AVALANCHE-LEVEL'],
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
	'eval'      => ['multiple' => false, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'm12 clr'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

// Add new field tourSpecialIncidents
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourSpecialIncidents'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'm12 clr', 'mandatory' => false],
	'sql'       => 'text NULL',
];

// Add new field eventReportAdditionalNotices
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReportAdditionalNotices'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'm12 clr', 'mandatory' => false],
	'sql'       => 'text NULL',
];

// Allow for these fields editing on first release level only
$allowEditingOnFirstReleaseLevelOnly = [
	'suitableForBeginners',
	'eventType',
	'title',
	'author',
	'organizers',
	'instructor',
	'mountainguide',
	'courseLevel',
	'courseTypeLevel0',
	'courseTypeLevel1',
	'eventDates',
	'durationInfo',
	'tourType',
	'tourTechDifficulty',
	'addMinAndMaxMembers',
	'maxMembers',
];

foreach ($allowEditingOnFirstReleaseLevelOnly as $field) {
	$GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field]['allowEditingOnFirstReleaseLevelOnly'] = true;
}

// DoNotCopy - Settings
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseId']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['author']['eval']['doNotCopy'] = false;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['startDate']['eval']['doNotCopy'] = false;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['endDate']['eval']['doNotCopy'] = false;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['startTime']['eval']['doNotCopy'] = false;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['endTime']['eval']['doNotCopy'] = false;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel']['eval']['doNotCopy'] = false;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['setRegistrationPeriod']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationStartDate']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationEndDate']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventState']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['executionState']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourAvalancheConditions']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourSpecialIncidents']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourWeatherConditions']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventSubstitutionText']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['filledInEventReportForm']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['disableOnlineRegistration']['eval']['doNotCopy'] = false;
