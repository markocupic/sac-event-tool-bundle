<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\Date;
use Contao\Input;
use Markocupic\SacEventToolBundle\Config\EventExecutionState;
use Markocupic\SacEventToolBundle\Config\EventState;
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
{title_legend:hide};{date_legend:hide};{recurring_legend:hide};{details_legend:hide};
{min_max_member_legend:hide};{registration_legend:hide};{deregistration_legend:hide};{sign_up_form_legend:hide};{event_registration_confirmation_legend:hide};
{image_legend:hide};{gallery_legend:hide};{broschuere_legend:hide};
{enclosure_legend:hide};{source_legend:hide};{expert_legend:hide}
';

$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $strLegends;
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['tour'] = $strLegends;
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['lastMinuteTour'] = $strLegends;
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['course'] = $strLegends;
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['generalEvent'] = $strLegends;
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
	->addField(['title', 'alias', 'courseId', 'eventState', 'author', 'instructor', 'mountainguide', 'organizers', 'tourType', 'tourTechDifficulty', 'teaser'], 'title_legend', PaletteManipulator::POSITION_APPEND)
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
	->addField(['title', 'alias', 'eventState', 'author', 'instructor', 'mountainguide', 'organizers', 'tourType', 'suitableForBeginners', 'tourTechDifficulty', 'teaser'], 'title_legend', PaletteManipulator::POSITION_APPEND)
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
	->applyToPalette('tour', 'tl_calendar_events')
	->applyToPalette('lastMinuteTour', 'tl_calendar_events');

// generalEvent
// same like tour but remove Fields: 'suitableForBeginners', 'tourTechDifficulty', 'tourProfile', 'mountainguide','tourDetailText', 'requirements'
// Add field: 'generalEventDetailText'
PaletteManipulator::create()
	->addField(['eventType'], 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['title', 'alias', 'eventState', 'author', 'instructor', 'organizers', 'tourType', 'teaser'], 'title_legend', PaletteManipulator::POSITION_APPEND)
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
	->applyToPalette('generalEvent', 'tl_calendar_events');

// Course palette
PaletteManipulator::create()
	->addField(['eventType'], 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['singleSRCBroschuere'], 'broschuere_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['title', 'alias', 'courseId', 'eventState', 'author', 'instructor', 'mountainguide', 'organizers', 'courseLevel', 'courseTypeLevel0', 'courseTypeLevel1'], 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['eventDates', 'durationInfo'], 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['isRecurringEvent'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['recurring'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['teaser', 'terms', 'issues', 'location', 'coordsCH1903', 'journey', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous', 'addIban'], 'details_legend', PaletteManipulator::POSITION_APPEND)
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
	->applyToPalette('course', 'tl_calendar_events');

// Tour report palette
PaletteManipulator::create()
	->addField(['executionState', 'eventSubstitutionText', 'tourWeatherConditions', 'tourAvalancheConditions', 'tourSpecialIncidents', 'eventReportAdditionalNotices'], 'tour_report_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('tour_report', 'tl_calendar_events');

// Global operations
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['plus1year'] = [
	'href'       => 'transformDates=+52weeks',
	'class'      => 'global_op_icon_class',
	'icon'       => 'bundles/markocupicsaceventtool/icons/calendar-plus.svg',
	'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['plus1yearConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()" accesskey="e"',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year'] = [
	'href'       => 'transformDates=-52weeks',
	'class'      => 'global_op_icon_class',
	'icon'       => 'bundles/markocupicsaceventtool/icons/calendar-minus.svg',
	'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['minus1yearConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()" accesskey="e"',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['onloadCallbackExportCalendar'] = [
	'href'       => 'action=onloadCallbackExportCalendar',
	'class'      => 'header_icon',
	'icon'       => 'bundles/markocupicsaceventtool/icons/excel.svg',
	'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
];

// Operations
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['toggle']['showInHeader'] = false;

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['registrations'] = [
	'href' => 'table=tl_calendar_events_member',
	'icon' => 'bundles/markocupicsaceventtool/icons/group.png',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['releaseLevelPrev'] = [
	'href' => 'action=releaseLevelPrev',
	'icon' => 'bundles/markocupicsaceventtool/icons/arrow_down.png',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['releaseLevelNext'] = [
	'href' => 'action=releaseLevelNext',
	'icon' => 'bundles/markocupicsaceventtool/icons/arrow_up.png',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['alias']['eval']['tl_class'] = 'clr';

// title
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['title']['eval']['tl_class'] = 'clr';

// author
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['author']['eval']['tl_class'] = 'clr';

// startTime
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['startTime']['sorting'] = false;

// startDate
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['startDate']['sorting'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['startDate']['flag'] = 5;

// teaser
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['teaser']['eval']['rte'] = null;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['teaser']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['teaser']['eval']['maxlength'] = 520;

// minMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseId'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => "varchar(255) NOT NULL default ''",
];

// eventToken
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventToken'] = [
	'sql' => "varchar(255) NOT NULL default ''",
];

// suitableForBeginners
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['suitableForBeginners'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
];

// isRecurringEvent
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['isRecurringEvent'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
];

// eventType
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventType'] = [
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'eval'      => ['submitOnChange' => true, 'includeBlankOption' => true, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => true],
	'sql'       => "varchar(32) NOT NULL default ''",
];

// mountainguide
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['mountainguide'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
];

// Hauptleiter (main instructor) is set automatically (the first instructor in the list is set as "Hauptleiter"
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['mainInstructor'] = [
	'exclude'    => true,
	'search'     => true,
	'filter'     => true,
	'sorting'    => true,
	'inputType'  => 'radio',
	'flag'       => 11,
	'foreignKey' => 'tl_user.name',
	'eval'       => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr'],
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
];

// Instructor
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['instructor'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'multiColumnWizard',
	// Save instructors in a child table tl_calendar_events_instructors
	'eval'      => [
		'tl_class'     => 'mcwColumnCount_1',
		'helpWizard'   => false,
		'mandatory'    => true,
		'columnFields' => [
			'instructorId' => [
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['instructorId'],
				'exclude'    => true,
				'inputType'  => 'select',
				'default'    => BackendUser::getInstance()->id,
				'filter'     => true,
				//'reference'  => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
				'foreignKey' => "tl_user.CONCAT(lastname, ' ', firstname, ', ', city)",
				'eval'       => ['style' => 'width:350px', 'mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'multiple' => false, 'tl_class' => 'hidelabel'],
			],
		],
	],
	'sql'       => 'blob NULL',
];

// Terms/Ziele
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['terms'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
	'sql'       => 'text NULL',
];

// issues
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['issues'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
	'sql'       => 'text NULL',
];

// requirements
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['requirements'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true, 'maxlength' => 300],
	'sql'       => 'text NULL',
];

// leistungen
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['leistungen'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false, 'maxlength' => 200],
	'sql'       => 'text NULL',
];

// courseLevel
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel'] = [
	'exclude'   => true,
	'search'    => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'],
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
	'sql'       => 'int(10) unsigned NULL',
];

// Course Type Level_0
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0'] = [
	'exclude'   => true,
	'search'    => true,
	'filter'    => true,
	'inputType' => 'select',
	'eval'      => ['tl_class' => 'clr m12', 'submitOnChange' => true, 'includeBlankOption' => true, 'multiple' => false, 'mandatory' => true],
	'sql'       => "int(10) unsigned NOT NULL default '0'",
];

// Course Type Level_1
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1'] = [
	'exclude'    => true,
	'search'     => true,
	'filter'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_course_sub_type.name',
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
	'eval'       => ['tl_class' => 'clr m12', 'multiple' => false, 'mandatory' => true],
	'sql'        => "int(10) unsigned NOT NULL default '0'",
];

// organizers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['organizers'] = [
	'exclude'   => true,
	'search'    => true,
	'filter'    => true,
	'sorting'   => true,
	'inputType' => 'select',
	'eval'      => ['multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'],
	'sql'       => 'blob NULL',
];

// equipment
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['equipment'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => 'text NULL',
];

// durationInfo
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['durationInfo'] = [
	'search'    => true,
	'filter'    => true,
	'exclude'   => true,
	'inputType' => 'select',
	'eval'      => ['includeBlankOption' => true, 'tl_class' => 'clr m12', 'mandatory' => true],
	'sql'       => "varchar(32) NOT NULL default ''",
];

// Add minimum and maximum members (set up to "true" by default, when creating a new event)
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addMinAndMaxMembers'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true],
	'sql'       => "char(1) NOT NULL default '1'",
];

// minMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['minMembers'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'clr m12', 'rgxp' => 'digit', 'mandatory' => true],
	'sql'       => 'int(3) unsigned NULL',
];

// maxMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['maxMembers'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'clr m12', 'rgxp' => 'digit', 'mandatory' => true],
	'sql'       => 'int(3) unsigned NULL',
];

// bookingEvent
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['bookingEvent'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => 'text NULL',
];

// miscellaneous
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['miscellaneous'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => 'text NULL',
];

// addIban
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addIban'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => "char(1) NOT NULL default ''",
];

// iban
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['iban'] = [
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => true, 'rgxp' => 'alnum', 'tl_class' => 'w50'],
	'sql'       => "varchar(32) NOT NULL default ''",
];

// ibanBeneficiary
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ibanBeneficiary'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'search'    => true,
	'eval'      => ['mandatory' => true, 'tl_class' => 'clr'],
	'sql'       => 'text NULL',
];

// eventDates
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventDates'] = [
	'exclude'   => true,
	'inputType' => 'multiColumnWizard',
	'eval'      => [
		'tl_class'        => 'mcwColumnCount_1',
		'columnsCallback' => [CalendarEvents::class, 'listFixedDates'],
		'buttons'         => ['up' => false, 'down' => false],
		'mandatory'       => true,
	],
	'sql'       => 'blob NULL',
];

// eventState
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventState'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => [EventState::STATE_FULLY_BOOKED, EventState::STATE_DEFERRED, EventState::STATE_CANCELED],
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
	'eval'      => ['submitOnChange' => false, 'includeBlankOption' => true, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => "varchar(32) NOT NULL default ''",
];

/** @todo Falls verschoben, kann hier das Verschiebedatum angegeben werden. */
// eventDeferDate
/*
 * $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventDeferDate'] = array(
 * 'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventDeferDate'],
 * 'exclude' => true,
 * 'inputType' => 'text',
 * 'eval' => array('rgxp' => 'date', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => true, 'tl_class' => 'clr wizard'),
 * 'sql' => "int(10) unsigned NULL"
 * );
 */

// meetingPoint
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['meetingPoint'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => '', 'mandatory' => false],
	'sql'       => 'text NULL',
];

// singleSRCBroschuere
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['singleSRCBroschuere'] = [
	'exclude'   => true,
	'inputType' => 'fileTree',
	'eval'      => ['filesOnly' => true, 'extensions' => Config::get('validImageTypes'), 'fieldType' => 'radio', 'mandatory' => false],
	'sql'       => 'binary(16) NULL',
];

// askForAhvNumber
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['askForAhvNumber'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
];

// Disable online registration
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['generateMainInstructorContactDataFromDb'] = [
	'filter'    => true,
	'sorting'   => true,
	'exclude'   => true,
	'default'   => BackendUser::getInstance()->generateMainInstructorContactDataFromDb,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => false],
	'sql'       => "char(1) NOT NULL default ''",
];

// Disable online registration
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['disableOnlineRegistration'] = [
	'filter'    => true,
	'sorting'   => true,
	'exclude'   => true,
	'default'   => BackendUser::getInstance()->disableOnlineRegistration,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => false],
	'sql'       => "char(1) NOT NULL default ''",
];

// registrationGoesTo
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationGoesTo'] = [
	'exclude'    => true,
	'filter'     => true,
	'inputType'  => 'select',
	'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
	'foreignKey' => 'tl_user.CONCAT(name,", ",city)',
	'eval'       => ['multiple' => false, 'chosen' => false, 'includeBlankOption' => true, 'tl_class' => 'clr'],
	'sql'        => "int(10) unsigned NOT NULL default '0'",
];

// Set registration period
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['setRegistrationPeriod'] = [
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true],
	'sql'       => "char(1) NOT NULL default ''",
];

// Set registration start date
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationStartDate'] = [
	'default'   => strtotime(Date::parse('Y-m-d')),
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'date', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => 'int(10) unsigned NULL',
];

// Set registration end date
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationEndDate'] = [
	'default'   => strtotime(Date::parse('Y-m-d')) + (2 * 24 * 3600) - 60,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'datim', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => 'int(10) unsigned NULL',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['allowDeregistration'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true],
	'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['deregistrationLimit'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => range(1, 720),
	'eval'      => ['rgxp' => 'natural', 'nospace' => true, 'tl_class' => 'w50'],
	'sql'       => "int(10) unsigned NOT NULL default '0'",
];

// addGallery
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addGallery'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true],
	'sql'       => "char(1) NOT NULL default ''",
];

// multiSRC
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['multiSRC'] = [
	'exclude'   => true,
	'inputType' => 'fileTree',
	'eval'      => ['multiple' => true, 'extensions' => 'jpg,jpeg,png', 'fieldType' => 'checkbox', 'orderField' => 'orderSRC', 'files' => true, 'filesOnly' => true, 'mandatory' => true],
	'sql'       => 'blob NULL',
];

// orderSRC
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['orderSRC'] = [
	'sql' => 'blob NULL',
];

// tour type
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType'] = [
	'exclude'    => true,
	'filter'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_tour_type.title',
	'relation'   => ['type' => 'hasMany', 'load' => 'eager'],
	'eval'       => ['multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr m12'],
	'sql'        => 'blob NULL',
];

// tourTechDifficulty
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourTechDifficulty'] = [
	'exclude'   => true,
	'inputType' => 'multiColumnWizard',
	'eval'      => [
		'mandatory'    => true,
		'tl_class'     => 'mcwColumnCount_2',
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

// tourProfile
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourProfile'] = [
	'exclude'   => true,
	'inputType' => 'multiColumnWizard',
	'eval'      => [
		'mandatory'    => false,
		'tl_class'     => 'mcwColumnCount_4',
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

// tourDetailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourDetailText'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	/** @todo maxlength 700 */
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
	'sql'       => 'text NULL',
];

// generalEventDetailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['generalEventDetailText'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => 'text NULL',
];

// eventReleaseLevel
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel'] = [
	'exclude'    => true,
	'filter'     => true,
	'sorting'    => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_event_release_level_policy.title',
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
	'eval'       => ['mandatory' => true, 'tl_class' => 'clr m12'],
	'sql'        => "int(10) unsigned NOT NULL default '0'",
];

if (!Input::get('act') || 'select' === Input::get('act')) {
	// Display the field correctly in the filter menu
	$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel']['options_callback'] = null;
}

// customizeEventRegistrationConfirmationEmailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['customizeEventRegistrationConfirmationEmailText'] = [
	'exclude'   => true,
	'filter'    => false,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true],
	'sql'       => "char(1) NOT NULL default ''",
];

// customEventRegistrationConfirmationEmailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['customEventRegistrationConfirmationEmailText'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false, 'preserveTags' => true, 'allowHtml' => true, 'decodeEntities' => false],
	'sql'       => 'text NULL',
];

// Tour report fields:
// This field is autofilled, if a user has filled in the event report
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['filledInEventReportForm'] = [
	'exclude' => false,
	'eval'    => ['doNotShow' => true],
	'sql'     => "char(1) NOT NULL default ''",
];

// executionState
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['executionState'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => [EventExecutionState::STATE_EXECUTED_LIKE_PREDICTED, EventExecutionState::STATE_DEFERRED, EventExecutionState::STATE_ADAPTED, EventExecutionState::STATE_CANCELED],
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
	'eval'      => ['includeBlankOption' => true, 'doNotShow' => true, 'tl_class' => 'clr m12', 'mandatory' => true],
	'sql'       => "varchar(32) NOT NULL default ''",
];

// coordsCH1903
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['coordsCH1903'] = [
	'exclude'   => true,
	'search'    => true,
	'sorting'   => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

// journey
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['journey'] = [
	'exclude'    => true,
	'filter'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_calendar_events_journey.title',
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
	'eval'       => ['multiple' => false, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr m12'],
	'sql'        => "varchar(255) NOT NULL default ''",
];

// linkSacRoutePortal
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['linkSacRoutePortal'] = [
	'exclude'   => true,
	'search'    => true,
	'sorting'   => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

// eventSubstitutionText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventSubstitutionText'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => false, 'tl_class' => 'clr m12'],
	'sql'       => 'text NULL',
];

// tourWeatherConditions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourWeatherConditions'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['mandatory' => true, 'tl_class' => 'clr m12'],
	'sql'       => 'text NULL',
];

// tourAvalancheConditions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourAvalancheConditions'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['SAC-EVENT-TOOL-AVALANCHE-LEVEL'],
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
	'eval'      => ['multiple' => false, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

// tourSpecialIncidents
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourSpecialIncidents'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => 'text NULL',
];

// eventReportAdditionalNotices
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReportAdditionalNotices'] = [
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
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
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventToken']['eval']['doNotCopy'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['disableOnlineRegistration']['eval']['doNotCopy'] = false;
//$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventDeferDate']['eval']['doNotCopy'] = false;
