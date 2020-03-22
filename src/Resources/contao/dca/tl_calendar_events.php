<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Keys
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['mountainguide'] = 'index';
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['eventState'] = 'index';
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['sql']['keys']['eventReleaseLevel'] = 'index';

// ctable
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['ctable'][] = 'tl_calendar_events_instructor';

// Callbacks
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = ['tl_calendar_events_sac_event_tool', 'onloadCallback'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = ['tl_calendar_events_sac_event_tool', 'setPaletteWhenCreatingNew'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = ['tl_calendar_events_sac_event_tool', 'onloadCallbackExportCalendar'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = ['tl_calendar_events_sac_event_tool', 'onloadCallbackShiftEventDates'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = ['tl_calendar_events_sac_event_tool', 'onloadCallbackSetPalettes'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = ['tl_calendar_events_sac_event_tool', 'onloadCallbackDeleteInvalidEvents'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onload_callback'][] = ['tl_calendar_events_sac_event_tool', 'setFilterSearchAndSortingBoard'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['oncreate_callback'][] = ['tl_calendar_events_sac_event_tool', 'oncreateCallback'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['oncopy_callback'][] = ['tl_calendar_events_sac_event_tool', 'oncopyCallback'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['ondelete_callback'][] = ['tl_calendar_events_sac_event_tool', 'ondeleteCallback'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = ['tl_calendar_events_sac_event_tool', 'onsubmitCallback'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = ['tl_calendar_events_sac_event_tool', 'adjustEndDate'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = ['tl_calendar_events_sac_event_tool', 'adjustRegistrationPeriod'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = ['tl_calendar_events_sac_event_tool', 'adjustImageSize'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = ['tl_calendar_events_sac_event_tool', 'adjustEventReleaseLevel'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = ['tl_calendar_events_sac_event_tool', 'adjustDurationInfo'];
$GLOBALS['TL_DCA']['tl_calendar_events']['config']['onsubmit_callback'][] = ['tl_calendar_events_sac_event_tool', 'setEventToken'];

// Buttons callback
$GLOBALS['TL_DCA']['tl_calendar_events']['edit']['buttons_callback'][] = ['tl_calendar_events_sac_event_tool', 'buttonsCallback'];

// List
// Sortierung nach Datum neuste Events zu letzt
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['disableGrouping'] = true;
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['fields'] = ['startDate ASC'];
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['child_record_callback'] = ['tl_calendar_events_sac_event_tool', 'listEvents'];

// Subpalettes
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['allowDeregistration'] = 'deregistrationLimit';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['addGallery'] = 'multiSRC';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['setRegistrationPeriod'] = 'registrationStartDate,registrationEndDate';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['addMinAndMaxMembers'] = 'minMembers,maxMembers';
$GLOBALS['TL_DCA']['tl_calendar_events']['subpalettes']['customizeEventRegistrationConfirmationEmailText'] = 'customEventRegistrationConfirmationEmailText';

// Reset palettes
$strLegends = '
{tour_report_legend:hide};{event_type_legend};
{broschuere_legend:hide};{title_legend:hide};{date_legend:hide};{recurring_legend:hide};{details_legend:hide};
{min_max_member_legend:hide};{registration_legend:hide};{deregistration_legend:hide};{event_registration_confirmation_legend:hide};{image_legend:hide};{gallery_legend:hide};
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
    ->addField(['eventType'], 'event_type_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['singleSRCBroschuere'], 'broschuere_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['title', 'alias', 'courseId', 'eventState', 'author', 'instructor', 'mountainguide', 'organizers', 'tourType', 'tourTechDifficulty', 'teaser'], 'title_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['suitableForBeginners', 'courseLevel', 'courseTypeLevel0', 'courseTypeLevel1'], 'title_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['eventDates', 'durationInfo'], 'date_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['addTime', 'startTime', 'endTime'], 'date_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['isRecurringEvent'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['recurring'], 'recurring_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['location', 'journey', 'tourDetailText', 'tourProfile', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous'], 'details_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['terms', 'issues'], 'details_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['addMinAndMaxMembers'], 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'], 'registration_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['allowDeregistration'], 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
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
    ->addField(['location', 'journey', 'tourDetailText', 'tourProfile', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous'], 'details_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['addMinAndMaxMembers'], 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'], 'registration_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['allowDeregistration'], 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
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
    ->addField(['location', 'journey', 'generalEventDetailText', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous'], 'details_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['addMinAndMaxMembers'], 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'], 'registration_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['allowDeregistration'], 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
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
    ->addField(['teaser', 'terms', 'issues', 'location', 'journey', 'requirements', 'leistungen', 'equipment', 'meetingPoint', 'bookingEvent', 'miscellaneous'], 'details_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['addMinAndMaxMembers'], 'min_max_member_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration', 'setRegistrationPeriod', 'registrationGoesTo'], 'registration_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['allowDeregistration'], 'deregistration_legend', PaletteManipulator::POSITION_APPEND)
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
    'label'      => &$GLOBALS['TL_LANG']['MSC']['plus1year'],
    'href'       => 'transformDates=+52weeks',
    'icon'       => 'bundles/markocupicsaceventtool/icons/calendar-plus.svg',
    'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['plus1yearConfirm'] . '\'))return false;Backend.getScrollOffset()" accesskey="e"',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year'] = [
    'label'      => &$GLOBALS['TL_LANG']['MSC']['minus1year'],
    'href'       => 'transformDates=-52weeks',
    'icon'       => 'bundles/markocupicsaceventtool/icons/calendar-minus.svg',
    'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['minus1yearConfirm'] . '\'))return false;Backend.getScrollOffset()" accesskey="e"',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['onloadCallbackExportCalendar'] = [
    'label'      => &$GLOBALS['TL_LANG']['MSC']['onloadCallbackExportCalendar'],
    'href'       => 'action=onloadCallbackExportCalendar',
    'icon'       => 'bundles/markocupicsaceventtool/icons/excel-file.svg',
    'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
];

// Operations
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['toggle'] = [
    'label'           => &$GLOBALS['TL_LANG']['tl_calendar_events']['toggle'],
    'icon'            => 'visible.svg',
    'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
    'button_callback' => ['tl_calendar_events_sac_event_tool', 'toggleIcon'],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['registrations'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['registrations'],
    'href'  => 'table=tl_calendar_events_member',
    'icon'  => 'bundles/markocupicsaceventtool/icons/group.png',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['releaseLevelPrev'] = [
    'label'           => &$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelPrev'],
    'href'            => 'action=releaseLevelPrev',
    'icon'            => 'bundles/markocupicsaceventtool/icons/arrow_down.png',
    'button_callback' => ['tl_calendar_events_sac_event_tool', 'releaseLevelPrev'],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['releaseLevelNext'] = [
    'label'           => &$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelNext'],
    'href'            => 'action=releaseLevelNext',
    'icon'            => 'bundles/markocupicsaceventtool/icons/arrow_up.png',
    'button_callback' => ['tl_calendar_events_sac_event_tool', 'releaseLevelNext'],
];

// Operations Button Callbacks
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['delete']['button_callback'] = ['tl_calendar_events_sac_event_tool', 'deleteIcon'];
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['copy']['button_callback'] = ['tl_calendar_events_sac_event_tool', 'copyIcon'];

// alias
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['alias']['input_field_callback'] = ['tl_calendar_events_sac_event_tool', 'showFieldValue'];
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

// minMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseId'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['courseId'],
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
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['suitableForBeginners'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default ''",
];

// isRecurringEvent
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['isRecurringEvent'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['isRecurringEvent'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default ''",
];

// eventType
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventType'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventType'],
    'reference'        => &$GLOBALS['TL_LANG']['MSC'],
    'exclude'          => true,
    'filter'           => true,
    'inputType'        => 'select',
    'options_callback' => ['tl_calendar_events_sac_event_tool', 'optionsCallbackEventType'],
    'save_callback'    => [['tl_calendar_events_sac_event_tool', 'saveCallbackEventType']],
    'eval'             => ['submitOnChange' => true, 'includeBlankOption' => true, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => true],
    'sql'              => "varchar(32) NOT NULL default ''",
];

// mountainguide
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['mountainguide'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['mountainguide'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default ''",
];

// Hauptleiter (main instructor) is set automatically (the first instructor in the list is set as "Hauptleiter"
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['mainInstructor'] = [
    'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['mainInstructor'],
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
    'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events']['instructor'],
    'exclude'       => true,
    'search'        => true,
    'inputType'     => 'multiColumnWizard',
    // Save instructors in a child table tl_calendar_events_instructors
    'save_callback' => [['tl_calendar_events_sac_event_tool', 'saveCallbackSetMaininstructor']],
    'eval'          => [
        'mandatory'    => true,
        'columnFields' => [
            'instructorId' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['instructorId'],
                'exclude'    => true,
                'inputType'  => 'select',
                'default'    => BackendUser::getInstance()->id,
                'filter'     => true,
                'reference'  => &$GLOBALS['TL_LANG']['tl_calendar_events'],
                'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
                'foreignKey' => "tl_user.CONCAT(lastname, ' ', firstname, ', ', city)",
                'eval'       => [
                    'style'              => 'width:350px',
                    'mandatory'          => true,
                    'includeBlankOption' => true,
                    'chosen'             => true,
                    'multiple'           => false,
                ],
            ],
        ],
    ],
    'sql'           => "blob NULL",
];

// Terms/Ziele
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['terms'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['terms'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
    'sql'       => "text NULL",
];

// issues
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['issues'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['issues'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
    'sql'       => "text NULL",
];

// requirements
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['requirements'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['requirements'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
    'sql'       => "text NULL",
];

// leistungen
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['leistungen'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['leistungen'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => "text NULL",
];

// courseLevel
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['courseLevel'],
    'exclude'   => true,
    'search'    => true,
    'filter'    => true,
    'inputType' => 'select',
    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'],
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
    'sql'       => "int(10) unsigned NULL",
];

// Course Type Level_0
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel0'],
    'exclude'          => true,
    'search'           => true,
    'filter'           => true,
    'inputType'        => 'select',
    'options_callback' => ['tl_calendar_events_sac_event_tool', 'optionsCallbackCourseTypeLevel0'],
    'eval'             => ['tl_class' => 'clr m12', 'submitOnChange' => true, 'includeBlankOption' => true, 'multiple' => false, 'mandatory' => true],
    'sql'              => "int(10) unsigned NOT NULL default '0'",
];

// Course Type Level_1
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel1'],
    'exclude'          => true,
    'search'           => true,
    'filter'           => true,
    'inputType'        => 'select',
    'foreignKey'       => 'tl_course_sub_type.name',
    'relation'         => ['type' => 'hasOne', 'load' => 'lazy'],
    'options_callback' => ['tl_calendar_events_sac_event_tool', 'optionsCallbackCourseSubType'],
    'eval'             => ['tl_class' => 'clr m12', 'multiple' => false, 'mandatory' => true],
    'sql'              => "int(10) unsigned NOT NULL default '0'",
];

// organizers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['organizers'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['organizers'],
    'exclude'          => true,
    'search'           => true,
    'filter'           => true,
    'sorting'          => true,
    'inputType'        => 'select',
    'options_callback' => ['tl_calendar_events_sac_event_tool', 'optionsCallbackGetOrganizers'],
    'eval'             => ['multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'],
    'sql'              => "blob NULL",
];

// equipment
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['equipment'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['equipment'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => "text NULL",
];

// durationInfo
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['durationInfo'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['durationInfo'],
    'search'           => true,
    'filter'           => true,
    'exclude'          => true,
    'inputType'        => 'select',
    'options_callback' => ['tl_calendar_events_sac_event_tool', 'optionsCallbackGetEventDuration'],
    'eval'             => ['includeBlankOption' => true, 'tl_class' => 'clr m12', 'mandatory' => true],
    'sql'              => "varchar(32) NOT NULL default ''",
];

// Add minimum an maximum members
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addMinAndMaxMembers'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['addMinAndMaxMembers'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

// minMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['minMembers'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['minMembers'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'clr m12', 'rgxp' => 'digit', 'mandatory' => true],
    'sql'       => "int(3) unsigned NULL",
];

// maxMembers
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['maxMembers'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['maxMembers'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'clr m12', 'rgxp' => 'digit', 'mandatory' => true],
    'sql'       => "int(3) unsigned NULL",
];

// bookingEvent
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['bookingEvent'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['bookingEvent'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => "text NULL",
];

// miscellaneous
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['miscellaneous'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['miscellaneous'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => "text NULL",
];

// eventDates
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventDates'] = [
    'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventDates'],
    'exclude'       => true,
    'inputType'     => 'multiColumnWizard',
    'load_callback' => [['tl_calendar_events_sac_event_tool', 'loadCallbackeventDates']],
    'eval'          => [
        'columnsCallback' => ['tl_calendar_events_sac_event_tool', 'listFixedDates'],
        'buttons'         => ['up' => false, 'down' => false],
        'mandatory'       => true,
    ],
    'sql'           => "blob NULL",
];

// eventState
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventState'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventState'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'select',
    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-STATE'],
    'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
    'eval'      => ['submitOnChange' => false, 'includeBlankOption' => true, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => false],
    //'eval'      => array('submitOnChange' => true, 'includeBlankOption' => true, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => false),
    'sql'       => "varchar(32) NOT NULL default ''",
];

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
 **/

// meetingPoint
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['meetingPoint'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['meetingPoint'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => '', 'mandatory' => false],
    'sql'       => "text NULL",
];

// singleSRCBroschuere
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['singleSRCBroschuere'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['singleSRCBroschuere'],
    'exclude'   => true,
    'inputType' => 'fileTree',
    'eval'      => ['filesOnly' => true, 'extensions' => Config::get('validImageTypes'), 'fieldType' => 'radio', 'mandatory' => false],
    'sql'       => "binary(16) NULL",
];

// Disable online registration
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['generateMainInstructorContactDataFromDb'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['generateMainInstructorContactDataFromDb'],
    'filter'    => true,
    'sorting'   => true,
    'exclude'   => true,
    'default'   => \Contao\BackendUser::getInstance()->generateMainInstructorContactDataFromDb,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => false],
    'sql'       => "char(1) NOT NULL default ''",
];

// Disable online registration
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['disableOnlineRegistration'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['disableOnlineRegistration'],
    'filter'    => true,
    'sorting'   => true,
    'exclude'   => true,
    'default'   => \Contao\BackendUser::getInstance()->disableOnlineRegistration,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => false],
    'sql'       => "char(1) NOT NULL default ''",
];

// registrationGoesTo
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationGoesTo'] = [
    'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['registrationGoesTo'],
    'exclude'    => true,
    'filter'     => true,
    'inputType'  => 'select',
    'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
    'foreignKey' => 'tl_user.name',
    'eval'       => ['multiple' => false, 'chosen' => false, 'includeBlankOption' => true, 'tl_class' => 'clr'],
    'sql'        => "int(10) unsigned NOT NULL default '0'",
];

// Set registration period
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['setRegistrationPeriod'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['setRegistrationPeriod'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

// Set registration start date
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationStartDate'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['registrationStartDate'],
    'default'   => strtotime(\Contao\Date::parse('Y-m-d')),
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'date', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql'       => "int(10) unsigned NULL",
];

// Set registration end date
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['registrationEndDate'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['registrationEndDate'],
    'default'   => strtotime(\Contao\Date::parse('Y-m-d')) + (2 * 24 * 3600) - 60,
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'datim', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql'       => "int(10) unsigned NULL",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['allowDeregistration'] = [

    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['allowDeregistration'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['deregistrationLimit'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['deregistrationLimit'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'select',
    'options'   => range(1, 720),
    'eval'      => ['rgxp' => 'natural', 'nospace' => true, 'tl_class' => 'w50'],
    'sql'       => "int(10) unsigned NOT NULL default '0'",
];

// addGallery
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['addGallery'] = [

    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['addGallery'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

// multiSRC
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['multiSRC'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['multiSRC'],
    'exclude'   => true,
    'inputType' => 'fileTree',
    'eval'      => ['multiple' => true, 'extensions' => 'jpg,jpeg,png', 'fieldType' => 'checkbox', 'orderField' => 'orderSRC', 'files' => true, 'filesOnly' => true, 'mandatory' => true],
    'sql'       => "blob NULL",
];

// orderSRC
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['orderSRC'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['orderSRC'],
    'sql'   => "blob NULL",
];

// tour type
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType'] = [
    'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourType'],
    'exclude'    => true,
    'filter'     => true,
    'inputType'  => 'select',
    'foreignKey' => 'tl_tour_type.title',
    'relation'   => ['type' => 'hasMany', 'load' => 'eager'],
    'eval'       => ['multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr m12'],
    'sql'        => "blob NULL",
];

// tourTechDifficulty
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourTechDifficulty'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficulty'],
    'exclude'   => true,
    'inputType' => 'multiColumnWizard',
    'eval'      => [
        'mandatory'    => true,
        'columnFields' => [
            'tourTechDifficultyMin' => [
                'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMin'],
                'exclude'          => true,
                'inputType'        => 'select',
                'reference'        => &$GLOBALS['TL_LANG']['tl_calendar_events'],
                'options_callback' => ['tl_calendar_events_sac_event_tool', 'optionsCallbackTourDifficulties'],
                'relation'         => ['type' => 'hasMany', 'load' => 'eager'],
                'foreignKey'       => 'tl_tour_difficulty.shortcut',
                'eval'             => [
                    'style'              => 'width:150px',
                    'mandatory'          => true,
                    'includeBlankOption' => true,
                ],
            ],
            'tourTechDifficultyMax' => [
                'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMax'],
                'exclude'          => true,
                'inputType'        => 'select',
                'reference'        => &$GLOBALS['TL_LANG']['tl_calendar_events'],
                'options_callback' => ['tl_calendar_events_sac_event_tool', 'optionsCallbackTourDifficulties'],
                'relation'         => ['type' => 'hasMany', 'load' => 'eager'],
                'foreignKey'       => 'tl_tour_difficulty.shortcut',
                'eval'             => [
                    'style'              => 'width:150px',
                    'mandatory'          => false,
                    'includeBlankOption' => true,
                ],
            ],
        ],
    ],
    'sql'       => "blob NULL",
];

// tourProfile
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourProfile'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfile'],
    'exclude'   => true,
    'inputType' => 'multiColumnWizard',
    'eval'      => [
        'mandatory'    => false,
        'columnFields' => [
            'tourProfileAscentMeters'  => [
                'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentMeters'],
                'inputType' => 'text',
                'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
                'eval'      => [
                    'rgxp'      => 'natural',
                    'style'     => 'width:150px',
                    'mandatory' => false,
                ],
            ],
            'tourProfileAscentTime'    => [
                'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentTime'],
                'inputType' => 'text',
                'eval'      => [
                    'rgxp'      => 'digit',
                    'style'     => 'width:150px',
                    'mandatory' => false,
                ],
            ],
            'tourProfileDescentMeters' => [
                'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentMeters'],
                'inputType' => 'text',
                'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
                'eval'      => [
                    'rgxp'      => 'natural',
                    'style'     => 'width:150px',
                    'mandatory' => false,
                ],
            ],
            'tourProfileDescentTime'   => [
                'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentTime'],
                'inputType' => 'text',
                'eval'      => [
                    'rgxp'      => 'digit',
                    'style'     => 'width:150px',
                    'mandatory' => false,
                ],
            ],
        ],
    ],
    'sql'       => "blob NULL",
];

// tourDetailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourDetailText'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourDetailText'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
    'sql'       => "text NULL",
];

// generalEventDetailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['generalEventDetailText'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['generalEventDetailText'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => "text NULL",
];

// eventReleaseLevel
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventReleaseLevel'],
    'exclude'          => true,
    'filter'           => true,
    'sorting'          => true,
    'inputType'        => 'select',
    'foreignKey'       => 'tl_event_release_level_policy.title',
    'relation'         => ['type' => 'hasOne', 'load' => 'lazy'],
    'options_callback' => ['tl_calendar_events_sac_event_tool', 'optionsCallbackListReleaseLevels'],
    'save_callback'    => [['tl_calendar_events_sac_event_tool', 'saveCallbackEventReleaseLevel']],
    'eval'             => ['mandatory' => true, 'tl_class' => 'clr m12'],
    'sql'              => "int(10) unsigned NOT NULL default '0'",
];
if (!Contao\Input::get('act') || Contao\Input::get('act') === 'select')
{
    // Display the field correctly in the filter menu
    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel']['options_callback'] = null;
}

// customizeEventRegistrationConfirmationEmailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['customizeEventRegistrationConfirmationEmailText'] = [

    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['customizeEventRegistrationConfirmationEmailText'],
    'exclude'   => true,
    'filter'    => false,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

// customEventRegistrationConfirmationEmailText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['customEventRegistrationConfirmationEmailText'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['customEventRegistrationConfirmationEmailText'],
    'exclude'   => true,
    'default'   => str_replace('{{br}}', "\n", Config::get('SAC_EVT_ACCEPT_REGISTRATION_EMAIL_TEXT')),
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false, 'preserveTags' => true, 'allowHtml' => true, 'decodeEntities' => false],
    'sql'       => "text NULL",
];

// Tour report fields:
// This field is autofilled, if a user has filled in the event report
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['filledInEventReportForm'] = [
    'label'   => &$GLOBALS['TL_LANG']['tl_calendar_events']['filledInEventReportForm'],
    'exclude' => false,
    'eval'    => ['doNotShow' => true],
    'sql'     => "char(1) NOT NULL default ''",
];

// executionState
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['executionState'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['executionState'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'select',
    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EXECUTION-STATE'],
    'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
    'eval'      => ['includeBlankOption' => true, 'doNotShow' => true, 'tl_class' => 'clr m12', 'mandatory' => true],
    'sql'       => "varchar(32) NOT NULL default ''",
];

// journey
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['journey'] = [
    'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events']['journey'],
    'exclude'    => true,
    'filter'     => true,
    'inputType'  => 'select',
    'foreignKey' => 'tl_calendar_events_journey.title',
    'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
    'eval'       => ['multiple' => false, 'mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'clr m12'],
    'sql'        => "varchar(255) NOT NULL default ''",
];

// eventSubstitutionText
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventSubstitutionText'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventSubstitutionText'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['mandatory' => false, 'tl_class' => 'clr m12'],
    'sql'       => "text NULL",
];

// tourWeatherConditions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourWeatherConditions'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourWeatherConditions'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['mandatory' => true, 'tl_class' => 'clr m12'],
    'sql'       => "text NULL",
];

// tourAvalancheConditions
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourAvalancheConditions'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourAvalancheConditions'],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['SAC-EVENT-TOOL-AVALANCHE-LEVEL'],
    'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events'],
    'eval'      => ['multiple' => false, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

// tourSpecialIncidents
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourSpecialIncidents'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['tourSpecialIncidents'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => "text NULL",
];

// eventReportAdditionalNotices
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReportAdditionalNotices'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['eventReportAdditionalNotices'],
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => "text NULL",
];

// Allow for these fields editing on first release level only
$allowEdititingOnFirstReleaseLevelOnly = [
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
];
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
$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventDeferDate']['eval']['doNotCopy'] = false;


