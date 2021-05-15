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

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\Date;
use Contao\Input;
use Markocupic\SacEventToolBundle\Dca\TlCalendarEvents;

// Keys
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['mountainguide'] = 'index';
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['eventState'] = 'index';
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['eventReleaseLevel'] = 'index';

// ctable
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['ctable'][] = 'tl_calendar_events_instructor';

// Callbacks
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = array(TlCalendarEvents::class, 'onloadCallback');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = array(TlCalendarEvents::class,  'setPaletteWhenCreatingNew');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = array(TlCalendarEvents::class,  'onloadCallbackExportCalendar');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = array(TlCalendarEvents::class, 'onloadCallbackShiftEventDates');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = array(TlCalendarEvents::class, 'onloadCallbackSetPalettes');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = array(TlCalendarEvents::class, 'onloadCallbackDeleteInvalidEvents');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = array(TlCalendarEvents::class, 'setFilterSearchAndSortingBoard');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['oncreate_callback'][] = array(TlCalendarEvents::class, 'oncreateCallback');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['oncopy_callback'][] = array(TlCalendarEvents::class, 'oncopyCallback');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['ondelete_callback'][] = array(TlCalendarEvents::class, 'ondeleteCallback');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = array(TlCalendarEvents::class, 'onsubmitCallback');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = array(TlCalendarEvents::class, 'adjustEndDate');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = array(TlCalendarEvents::class, 'adjustRegistrationPeriod');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = array(TlCalendarEvents::class, 'adjustImageSize');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = array(TlCalendarEvents::class, 'adjustEventReleaseLevel');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = array(TlCalendarEvents::class, 'adjustDurationInfo');
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = array(TlCalendarEvents::class, 'setEventToken');

// Buttons callback
$GLOBALS['TL_DCA']['tl_calendar_events']['edit']['buttons_callback'][] = array(TlCalendarEvents::class, 'buttonsCallback');

// List
// Sortierung nach Datum neuste Events zu letzt
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['disableGrouping'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['fields'] = array('startDate ASC');
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['child_record_callback'] = array(TlCalendarEvents::class, 'listEvents');

// Subpalettes
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['allowDeregistration'] = 'deregistrationLimit';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['addGallery'] = 'multiSRC';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['setRegistrationPeriod'] = 'registrationStartDate,registrationEndDate';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['addMinAndMaxMembers'] = 'minMembers,maxMembers';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['customizeEventRegistrationConfirmationEmailText'] = 'customEventRegistrationConfirmationEmailText';

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
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'setRegistrationPeriod';
$GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['__selector__'][] = 'customizeEventRegistrationConfirmationEmailText';

// Default palettes (define it for any case, f.ex edit all mode)
// Put here all defined fields in the dca
PaletteManipulator::create()
	->addField(array('eventType'), 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('singleSRCBroschuere'), 'broschuere_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('title', 'alias', 'courseId', 'eventState', 'author', 'instructor', 'mountainguide', 'organizers', 'tourType', 'tourTechDifficulty', 'teaser'), 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('suitableForBeginners', 'courseLevel', 'courseTypeLevel0', 'courseTypeLevel1'), 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('eventDates', 'durationInfo'), 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addTime', 'startTime', 'endTime'), 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('isRecurringEvent'), 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('recurring'), 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('location', 'journey', 'tourDetailText', 'tourProfile', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous'), 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('terms', 'issues'), 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addMinAndMaxMembers'), 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'), 'registration_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('allowDeregistration'), 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('askForAhvNumber'), 'sign_up_form_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('customizeEventRegistrationConfirmationEmailText'), 'event_registration_confirmation_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addImage'), 'image_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addGallery'), 'gallery_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addEnclosure'), 'enclosure_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('source'), 'source_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('cssClass', 'noComments'), 'expert_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('default', 'tl_calendar_events');

// Tour and lastMinuteTour palette
PaletteManipulator::create()
	->addField(array('eventType'), 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('title', 'alias', 'eventState', 'author', 'instructor', 'mountainguide', 'organizers', 'tourType', 'suitableForBeginners', 'tourTechDifficulty', 'teaser'), 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('eventDates', 'durationInfo'), 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('isRecurringEvent'), 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('recurring'), 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('location', 'journey', 'tourDetailText', 'tourProfile', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous'), 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addMinAndMaxMembers'), 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'), 'registration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('allowDeregistration'), 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('askForAhvNumber'), 'sign_up_form_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('customizeEventRegistrationConfirmationEmailText'), 'event_registration_confirmation_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addGallery'), 'gallery_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addEnclosure'), 'enclosure_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('source'), 'source_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('cssClass', 'noComments'), 'expert_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('tour', 'tl_calendar_events')
	->applyToPalette('lastMinuteTour', 'tl_calendar_events');

// generalEvent
// same like tour but remove Fields: 'suitableForBeginners', 'tourTechDifficulty', 'tourProfile', 'mountainguide','tourDetailText', 'requirements'
// Add field: 'generalEventDetailText'
PaletteManipulator::create()
	->addField(array('eventType'), 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('title', 'alias', 'eventState', 'author', 'instructor', 'organizers', 'tourType', 'teaser'), 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('eventDates', 'durationInfo'), 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('isRecurringEvent'), 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('recurring'), 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('location', 'journey', 'generalEventDetailText', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous'), 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addMinAndMaxMembers'), 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'), 'registration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('allowDeregistration'), 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('askForAhvNumber'), 'sign_up_form_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('customizeEventRegistrationConfirmationEmailText'), 'event_registration_confirmation_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addGallery'), 'gallery_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addEnclosure'), 'enclosure_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('source'), 'source_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('cssClass', 'noComments'), 'expert_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('generalEvent', 'tl_calendar_events');

// Course palette
PaletteManipulator::create()
	->addField(array('eventType'), 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('singleSRCBroschuere'), 'broschuere_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('title', 'alias', 'courseId', 'eventState', 'author', 'instructor', 'mountainguide', 'organizers', 'courseLevel', 'courseTypeLevel0', 'courseTypeLevel1'), 'title_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('eventDates', 'durationInfo'), 'date_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('isRecurringEvent'), 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('recurring'), 'recurring_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('teaser', 'terms', 'issues', 'location', 'journey', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous'), 'details_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addMinAndMaxMembers'), 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'), 'registration_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('allowDeregistration'), 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('askForAhvNumber'), 'sign_up_form_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('customizeEventRegistrationConfirmationEmailText'), 'event_registration_confirmation_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addImage'), 'image_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addGallery'), 'gallery_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('addEnclosure'), 'enclosure_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('source'), 'source_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('cssClass', 'noComments'), 'expert_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('course', 'tl_calendar_events');

// Tour report palette
PaletteManipulator::create()
	->addField(array('executionState', 'eventSubstitutionText', 'tourWeatherConditions', 'tourAvalancheConditions', 'tourSpecialIncidents', 'eventReportAdditionalNotices'), 'tour_report_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('tour_report', 'tl_calendar_events');

// Global operations
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['plus1year'] = array(
	'label'      => &$GLOBALS['TL_LANG']['MSC']['plus1year'],
	'href'       => 'transformDates=+52weeks',
	'class'      => 'global_op_icon_class',
	//'class'      => 'header_icon',
	'icon'       => 'bundles/markocupicsaceventtool/icons/calendar-plus.svg',
	'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['plus1yearConfirm'] . '\'))return false;Backend.getScrollOffset()" accesskey="e"',
);

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year'] = array(
	'label'      => &$GLOBALS['TL_LANG']['MSC']['minus1year'],
	'href'       => 'transformDates=-52weeks',
	'class'      => 'global_op_icon_class',
	//'class'      => 'header_icon',
	'icon'       => 'bundles/markocupicsaceventtool/icons/calendar-minus.svg',
	'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['minus1yearConfirm'] . '\'))return false;Backend.getScrollOffset()" accesskey="e"',
);

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['onloadCallbackExportCalendar'] = array(
	'label'      => &$GLOBALS['TL_LANG']['MSC']['onloadCallbackExportCalendar'],
	'href'       => 'action=onloadCallbackExportCalendar',
	//'class'      => 'global_op_icon_class',
	'class'      => 'header_icon',
	'icon'       => 'bundles/markocupicsaceventtool/icons/excel.svg',
	'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
);

// Operations
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['toggle'] = array(
	'label'           => &$GLOBALS['TL_LANG']['tl_calendar_events']['toggle'],
	'icon'            => 'visible.svg',
	'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
	'button_callback' => array(TlCalendarEvents::class, 'toggleIcon'),
);

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['registrations'] = array(
	'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['registrations'],
	'href'  => 'table=tl_calendar_events_member',
	'icon'  => 'bundles/markocupicsaceventtool/icons/group.png',
);

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['releaseLevelPrev'] = array(
	'label'           => &$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelPrev'],
	'href'            => 'action=releaseLevelPrev',
	'icon'            => 'bundles/markocupicsaceventtool/icons/arrow_down.png',
	'button_callback' => array(TlCalendarEvents::class, 'releaseLevelPrev'),
);

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['releaseLevelNext'] = array(
	'label'           => &$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelNext'],
	'href'            => 'action=releaseLevelNext',
	'icon'            => 'bundles/markocupicsaceventtool/icons/arrow_up.png',
	'button_callback' => array(TlCalendarEvents::class, 'releaseLevelNext'),
);

// Operations Button Callbacks
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['delete']['button_callback'] = array(TlCalendarEvents::class, 'deleteIcon');
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['copy']['button_callback'] = array(TlCalendarEvents::class, 'copyIcon');

// alias
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['alias']['input_field_callback'] = array(TlCalendarEvents::class, 'showFieldValue');
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
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseId'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['courseId'],
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false),
	'sql'       => "varchar(255) NOT NULL default ''",
);

// eventToken
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventToken'] = array(
	'sql' => "varchar(255) NOT NULL default ''",
);

// suitableForBeginners
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['suitableForBeginners'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['suitableForBeginners'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
);

// isRecurringEvent
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['isRecurringEvent'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['isRecurringEvent'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
);

// eventType
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventType'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventType'],
	'reference'        => &$GLOBALS['TL_LANG']['MSC'],
	'exclude'          => true,
	'filter'           => true,
	'inputType'        => 'select',
	'options_callback' => array(TlCalendarEvents::class, 'optionsCallbackEventType'),
	'save_callback'    => array(array(TlCalendarEvents::class, 'saveCallbackEventType')),
	'eval'             => array('submitOnChange' => true, 'includeBlankOption' => true, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => true),
	'sql'              => "varchar(32) NOT NULL default ''",
);

// mountainguide
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['mountainguide'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['mountainguide'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
);

// Hauptleiter (main instructor) is set automatically (the first instructor in the list is set as "Hauptleiter"
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['mainInstructor'] = array(
	'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['mainInstructor'],
	'exclude'    => true,
	'search'     => true,
	'filter'     => true,
	'sorting'    => true,
	'inputType'  => 'radio',
	'flag'       => 11,
	'foreignKey' => 'tl_user.name',
	'eval'       => array('mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr'),
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
);

// Instructor
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['instructor'] = array(
	'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events']['instructor'],
	'exclude'       => true,
	'search'        => true,
	'inputType'     => 'multiColumnWizard',
	// Save instructors in a child table tl_calendar_events_instructors
	'save_callback' => array(array(TlCalendarEvents::class, 'saveCallbackSetMaininstructor')),
	'eval'          => array(
		'mandatory'    => true,
		'columnFields' => array(
			'instructorId' => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['instructorId'],
				'exclude'    => true,
				'inputType'  => 'select',
				'default'    => BackendUser::getInstance()->id,
				'filter'     => true,
				'reference'  => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
				'foreignKey' => "tl_user.CONCAT(lastname, ' ', firstname, ', ', city)",
				'eval'       => array(
					'style'              => 'width:350px',
					'mandatory'          => true,
					'includeBlankOption' => true,
					'chosen'             => true,
					'multiple'           => false,
				),
			),
		),
	),
	'sql'           => "blob NULL",
);

// Terms/Ziele
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['terms'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['terms'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => true),
	'sql'       => "text NULL",
);

// issues
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['issues'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['issues'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => true),
	'sql'       => "text NULL",
);

// requirements
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['requirements'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['requirements'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => true, 'maxlength' => 300),
	'sql'       => "text NULL",
);

// leistungen
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['leistungen'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['leistungen'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false, 'maxlength' => 300),
	'sql'       => "text NULL",
);

// courseLevel
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['courseLevel'],
	'exclude'   => true,
	'search'    => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'],
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => true),
	'sql'       => "int(10) unsigned NULL",
);

// Course Type Level_0
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel0'],
	'exclude'          => true,
	'search'           => true,
	'filter'           => true,
	'inputType'        => 'select',
	'options_callback' => array(TlCalendarEvents::class, 'optionsCallbackCourseTypeLevel0'),
	'eval'             => array('tl_class' => 'clr m12', 'submitOnChange' => true, 'includeBlankOption' => true, 'multiple' => false, 'mandatory' => true),
	'sql'              => "int(10) unsigned NOT NULL default '0'",
);

// Course Type Level_1
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel1'],
	'exclude'          => true,
	'search'           => true,
	'filter'           => true,
	'inputType'        => 'select',
	'foreignKey'       => 'tl_course_sub_type.name',
	'relation'         => array('type' => 'hasOne', 'load' => 'lazy'),
	'options_callback' => array(TlCalendarEvents::class, 'optionsCallbackCourseSubType'),
	'eval'             => array('tl_class' => 'clr m12', 'multiple' => false, 'mandatory' => true),
	'sql'              => "int(10) unsigned NOT NULL default '0'",
);

// organizers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['organizers'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['organizers'],
	'exclude'          => true,
	'search'           => true,
	'filter'           => true,
	'sorting'          => true,
	'inputType'        => 'select',
	'options_callback' => array(TlCalendarEvents::class, 'optionsCallbackGetOrganizers'),
	'eval'             => array('multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'),
	'sql'              => "blob NULL",
);

// equipment
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['equipment'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['equipment'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false),
	'sql'       => "text NULL",
);

// durationInfo
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['durationInfo'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['durationInfo'],
	'search'           => true,
	'filter'           => true,
	'exclude'          => true,
	'inputType'        => 'select',
	'options_callback' => array(TlCalendarEvents::class, 'optionsCallbackGetEventDuration'),
	'eval'             => array('includeBlankOption' => true, 'tl_class' => 'clr m12', 'mandatory' => true),
	'sql'              => "varchar(32) NOT NULL default ''",
);

// Add minimum an maximum members
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addMinAndMaxMembers'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['addMinAndMaxMembers'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true),
	'sql'       => "char(1) NOT NULL default ''",
);

// minMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['minMembers'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['minMembers'],
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'clr m12', 'rgxp' => 'digit', 'mandatory' => true),
	'sql'       => "int(3) unsigned NULL",
);

// maxMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['maxMembers'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['maxMembers'],
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'clr m12', 'rgxp' => 'digit', 'mandatory' => true),
	'sql'       => "int(3) unsigned NULL",
);

// bookingEvent
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['bookingEvent'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['bookingEvent'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false),
	'sql'       => "text NULL",
);

// miscellaneous
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['miscellaneous'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['miscellaneous'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false),
	'sql'       => "text NULL",
);

// eventDates
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventDates'] = array(
	'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventDates'],
	'exclude'       => true,
	'inputType'     => 'multiColumnWizard',
	'load_callback' => array(array(TlCalendarEvents::class, 'loadCallbackeventDates')),
	'eval'          => array(
		'columnsCallback' => array(TlCalendarEvents::class, 'listFixedDates'),
		'buttons'         => array('up' => false, 'down' => false),
		'mandatory'       => true,
	),
	'sql'           => "blob NULL",
);

// eventState
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventState'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventState'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-STATE'],
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
	'eval'      => array('submitOnChange' => false, 'includeBlankOption' => true, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => false),
	//'eval'      => array('submitOnChange' => true, 'includeBlankOption' => true, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => false),
	'sql'       => "varchar(32) NOT NULL default ''",
);

/** @todo Falls verschoben, kann hier das Verschiebedatum angegeben werden. */
// eventDeferDate
/**
 * $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventDeferDate'] = array(
 * 'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventDeferDate'],
 * 'exclude'   => true,
 * 'inputType' => 'text',
 * 'eval'      => array('rgxp' => 'date', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => true, 'tl_class' => 'clr wizard'),
 * 'sql'       => "int(10) unsigned NULL"
 * );
 */

// meetingPoint
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['meetingPoint'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['meetingPoint'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => '', 'mandatory' => false),
	'sql'       => "text NULL",
);

// singleSRCBroschuere
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['singleSRCBroschuere'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['singleSRCBroschuere'],
	'exclude'   => true,
	'inputType' => 'fileTree',
	'eval'      => array('filesOnly' => true, 'extensions' => Config::get('validImageTypes'), 'fieldType' => 'radio', 'mandatory' => false),
	'sql'       => "binary(16) NULL",
);

// askForAhvNumber
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['askForAhvNumber'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['askForAhvNumber'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default ''",
);

// Disable online registration
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['generateMainInstructorContactDataFromDb'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['generateMainInstructorContactDataFromDb'],
	'filter'    => true,
	'sorting'   => true,
	'exclude'   => true,
	'default'   => BackendUser::getInstance()->generateMainInstructorContactDataFromDb,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => false),
	'sql'       => "char(1) NOT NULL default ''",
);

// Disable online registration
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['disableOnlineRegistration'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['disableOnlineRegistration'],
	'filter'    => true,
	'sorting'   => true,
	'exclude'   => true,
	'default'   => BackendUser::getInstance()->disableOnlineRegistration,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => false),
	'sql'       => "char(1) NOT NULL default ''",
);

// registrationGoesTo
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationGoesTo'] = array(
	'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['registrationGoesTo'],
	'exclude'    => true,
	'filter'     => true,
	'inputType'  => 'select',
	'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
	'foreignKey' => 'tl_user.CONCAT(name,", ",city)',
	'eval'       => array('multiple' => false, 'chosen' => false, 'includeBlankOption' => true, 'tl_class' => 'clr'),
	'sql'        => "int(10) unsigned NOT NULL default '0'",
);

// Set registration period
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['setRegistrationPeriod'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['setRegistrationPeriod'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true),
	'sql'       => "char(1) NOT NULL default ''",
);

// Set registration start date
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationStartDate'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['registrationStartDate'],
	'default'   => strtotime(Date::parse('Y-m-d')),
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'date', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'),
	'sql'       => "int(10) unsigned NULL",
);

// Set registration end date
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationEndDate'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['registrationEndDate'],
	'default'   => strtotime(Date::parse('Y-m-d')) + (2 * 24 * 3600) - 60,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'datim', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'),
	'sql'       => "int(10) unsigned NULL",
);

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['allowDeregistration'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['allowDeregistration'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['deregistrationLimit'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['deregistrationLimit'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => range(1, 720),
	'eval'      => array('rgxp' => 'natural', 'nospace' => true, 'tl_class' => 'w50'),
	'sql'       => "int(10) unsigned NOT NULL default '0'",
);

// addGallery
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addGallery'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['addGallery'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true),
	'sql'       => "char(1) NOT NULL default ''",
);

// multiSRC
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['multiSRC'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['multiSRC'],
	'exclude'   => true,
	'inputType' => 'fileTree',
	'eval'      => array('multiple' => true, 'extensions' => 'jpg,jpeg,png', 'fieldType' => 'checkbox', 'orderField' => 'orderSRC', 'files' => true, 'filesOnly' => true, 'mandatory' => true),
	'sql'       => "blob NULL",
);

// orderSRC
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['orderSRC'] = array(
	'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['orderSRC'],
	'sql'   => "blob NULL",
);

// tour type
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType'] = array(
	'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourType'],
	'exclude'    => true,
	'filter'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_tour_type.title',
	'relation'   => array('type' => 'hasMany', 'load' => 'eager'),
	'eval'       => array('multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr m12'),
	'sql'        => "blob NULL",
);

// tourTechDifficulty
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourTechDifficulty'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficulty'],
	'exclude'   => true,
	'inputType' => 'multiColumnWizard',
	'eval'      => array(
		'mandatory'    => true,
		'columnFields' => array(
			'tourTechDifficultyMin' => array(
				'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMin'],
				'exclude'          => true,
				'inputType'        => 'select',
				'reference'        => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'options_callback' => array(TlCalendarEvents::class, 'optionsCallbackTourDifficulties'),
				'relation'         => array('type' => 'hasMany', 'load' => 'eager'),
				'foreignKey'       => 'tl_tour_difficulty.shortcut',
				'eval'             => array(
					'style'              => 'width:150px',
					'mandatory'          => true,
					'includeBlankOption' => true,
				),
			),
			'tourTechDifficultyMax' => array(
				'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMax'],
				'exclude'          => true,
				'inputType'        => 'select',
				'reference'        => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'options_callback' => array(TlCalendarEvents::class, 'optionsCallbackTourDifficulties'),
				'relation'         => array('type' => 'hasMany', 'load' => 'eager'),
				'foreignKey'       => 'tl_tour_difficulty.shortcut',
				'eval'             => array(
					'style'              => 'width:150px',
					'mandatory'          => false,
					'includeBlankOption' => true,
				),
			),
		),
	),
	'sql'       => "blob NULL",
);

// tourProfile
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourProfile'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfile'],
	'exclude'   => true,
	'inputType' => 'multiColumnWizard',
	'eval'      => array(
		'mandatory'    => false,
		'columnFields' => array(
			'tourProfileAscentMeters'  => array(
				'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentMeters'],
				'inputType' => 'text',
				'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'eval'      => array(
					'rgxp'      => 'natural',
					'style'     => 'width:150px',
					'mandatory' => false,
				),
			),
			'tourProfileAscentTime'    => array(
				'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentTime'],
				'inputType' => 'text',
				'eval'      => array(
					'rgxp'      => 'digit',
					'style'     => 'width:150px',
					'mandatory' => false,
				),
			),
			'tourProfileDescentMeters' => array(
				'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentMeters'],
				'inputType' => 'text',
				'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
				'eval'      => array(
					'rgxp'      => 'natural',
					'style'     => 'width:150px',
					'mandatory' => false,
				),
			),
			'tourProfileDescentTime'   => array(
				'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentTime'],
				'inputType' => 'text',
				'eval'      => array(
					'rgxp'      => 'digit',
					'style'     => 'width:150px',
					'mandatory' => false,
				),
			),
		),
	),
	'sql'       => "blob NULL",
);

// tourDetailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourDetailText'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourDetailText'],
	'exclude'   => true,
	'inputType' => 'textarea',
	/** @todo maxlength 700 */
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => true),
	'sql'       => "text NULL",
);

// generalEventDetailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['generalEventDetailText'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['generalEventDetailText'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false),
	'sql'       => "text NULL",
);

// eventReleaseLevel
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventReleaseLevel'],
	'exclude'          => true,
	'filter'           => true,
	'sorting'          => true,
	'inputType'        => 'select',
	'foreignKey'       => 'tl_event_release_level_policy.title',
	'relation'         => array('type' => 'hasOne', 'load' => 'lazy'),
	'options_callback' => array(TlCalendarEvents::class, 'optionsCallbackListReleaseLevels'),
	'save_callback'    => array(array(TlCalendarEvents::class, 'saveCallbackEventReleaseLevel')),
	'eval'             => array('mandatory' => true, 'tl_class' => 'clr m12'),
	'sql'              => "int(10) unsigned NOT NULL default '0'",
);

if (!Input::get('act') || Input::get('act') === 'select')
{
	// Display the field correctly in the filter menu
	$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel']['options_callback'] = null;
}

// customizeEventRegistrationConfirmationEmailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['customizeEventRegistrationConfirmationEmailText'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['customizeEventRegistrationConfirmationEmailText'],
	'exclude'   => true,
	'filter'    => false,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true),
	'sql'       => "char(1) NOT NULL default ''",
);

// customEventRegistrationConfirmationEmailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['customEventRegistrationConfirmationEmailText'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['customEventRegistrationConfirmationEmailText'],
	'exclude'   => true,
	'default'   => str_replace('{{br}}', "\n", Config::get('SAC_EVT_ACCEPT_REGISTRATION_EMAIL_TEXT')),
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false, 'preserveTags' => true, 'allowHtml' => true, 'decodeEntities' => false),
	'sql'       => "text NULL",
);

// Tour report fields:
// This field is autofilled, if a user has filled in the event report
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['filledInEventReportForm'] = array(
	'label'   => &$GLOBALS['TL_LANG']['tl_calendar_events']['filledInEventReportForm'],
	'exclude' => false,
	'eval'    => array('doNotShow' => true),
	'sql'     => "char(1) NOT NULL default ''",
);

// executionState
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['executionState'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['executionState'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EXECUTION-STATE'],
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
	'eval'      => array('includeBlankOption' => true, 'doNotShow' => true, 'tl_class' => 'clr m12', 'mandatory' => true),
	'sql'       => "varchar(32) NOT NULL default ''",
);

// journey
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['journey'] = array(
	'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['journey'],
	'exclude'    => true,
	'filter'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_calendar_events_journey.title',
	'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
	'eval'       => array('multiple' => false, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr m12'),
	'sql'        => "varchar(255) NOT NULL default ''",
);

// eventSubstitutionText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventSubstitutionText'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventSubstitutionText'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('mandatory' => false, 'tl_class' => 'clr m12'),
	'sql'       => "text NULL",
);

// tourWeatherConditions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourWeatherConditions'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourWeatherConditions'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('mandatory' => true, 'tl_class' => 'clr m12'),
	'sql'       => "text NULL",
);

// tourAvalancheConditions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourAvalancheConditions'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourAvalancheConditions'],
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['SAC-EVENT-TOOL-AVALANCHE-LEVEL'],
	'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
	'eval'      => array('multiple' => false, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'),
	'sql'       => "varchar(255) NOT NULL default ''",
);

// tourSpecialIncidents
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourSpecialIncidents'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourSpecialIncidents'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false),
	'sql'       => "text NULL",
);

// eventReportAdditionalNotices
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReportAdditionalNotices'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventReportAdditionalNotices'],
	'exclude'   => true,
	'inputType' => 'textarea',
	'eval'      => array('tl_class' => 'clr m12', 'mandatory' => false),
	'sql'       => "text NULL",
);

// Allow for these fields editing on first release level only
$allowEdititingOnFirstReleaseLevelOnly = array(
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
);

foreach ($allowEdititingOnFirstReleaseLevelOnly as $field)
{
	$GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field]['allowEdititingOnFirstReleaseLevelOnly'] = true;
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
