<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

$rootDir = Contao\System::getContainer()->getParameter('kernel.project_dir');


// Add notification center configs
require_once($rootDir . '/vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/config/notification_center_config.php');

// include custom functions
require_once($rootDir . '/vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/functions/functions.php');


if (TL_MODE == 'BE')
{
    // Add Backend CSS
    $GLOBALS['TL_CSS'][] = 'bundles/markocupicsaceventtool/css/be_stylesheet.css';
}


$GLOBALS['BE_MOD']['content']['calendar']['tables'] = array('tl_calendar_container', 'tl_calendar', 'tl_calendar_events', 'tl_calendar_events_instructor_invoice', 'tl_calendar_feed', 'tl_content', 'tl_calendar_events_member');
$GLOBALS['BE_MOD']['sac_be_modules'] = array(
    'sac_calendar_events_tool'         => array
    (
        'tables' => array('tl_calendar_container', 'tl_calendar', 'tl_calendar_events', 'tl_calendar_events_instructor_invoice', 'tl_calendar_feed', 'tl_content', 'tl_calendar_events_member'),
        'table'  => array('TableWizard', 'importTable'),
        'list'   => array('ListWizard', 'importList'),
    ),
    'sac_calendar_events_stories_tool' => array(
        'tables' => array('tl_calendar_events_story'),
    ),
    'sac_course_main_types_tool'       => array(
        'tables' => array('tl_course_main_type'),
    ),
    'sac_course_sub_types_tool'        => array(
        'tables' => array('tl_course_sub_type'),
    ),
    'sac_event_type_tool'              => array(
        'tables' => array('tl_event_type'),
    ),
    'sac_tour_difficulty_tool'         => array(
        'tables' => array('tl_tour_difficulty_category', 'tl_tour_difficulty'),
        'table'  => array('TableWizard', 'importTable'),
        'list'   => array('ListWizard', 'importList'),
    ),
    'sac_tour_type_tool'               => array(
        'tables' => array('tl_tour_type'),
    ),
    'sac_event_release_tool'           => array(
        'tables' => array('tl_event_release_level_policy_package', 'tl_event_release_level_policy'),
        'table'  => array('TableWizard', 'importTable'),
        'list'   => array('ListWizard', 'importList'),
    ),
    'sac_event_organizer_tool'         => array(
        'tables' => array('tl_event_organizer'),
        'table'  => array('TableWizard', 'importTable'),
        'list'   => array('ListWizard', 'importList'),
    ),
    'sac_event_journey_tool'           => array(
        'tables' => array('tl_calendar_events_journey'),
    ),
    'sac_cabanne_tool'                 => array(
        'tables' => array('tl_cabanne_sac'),
    ),
    'sac_user_role_tool'               => array(
        'tables' => array('tl_user_role'),
    ),
    'sac_user_temp'                    => array(
        'tables' => array('tl_user_temp'),
    ),
);

// Add permissions
$GLOBALS['TL_PERMISSIONS'][] = 'calendar_containers';
$GLOBALS['TL_PERMISSIONS'][] = 'calendar_containerp';


// Frontend Modules
$GLOBALS['FE_MOD']['sac_event_tool_fe_modules'] = array(
    'eventToolFrontendUserDashboard'         => 'Markocupic\SacEventToolBundle\ModuleSacEventToolMemberDashboard',
    'eventToolEventRegistrationForm'         => 'Markocupic\SacEventToolBundle\ModuleSacEventToolEventRegistrationForm',
    'eventToolCalendarEventStoryList'        => 'Markocupic\SacEventToolBundle\ModuleSacEventToolCalendarEventStoryList',
    'eventToolCalendarEventStoryReader'      => 'Markocupic\SacEventToolBundle\ModuleSacEventToolCalendarEventStoryReader',
    'eventToolCalendarEventlist'             => 'Markocupic\SacEventToolBundle\ModuleSacEventToolEventlist',
    'eventTourDifficultyExplanationList'     => 'Markocupic\SacEventToolBundle\ModuleSacEventToolTourDifficultyExplanationList',
    'eventToolCalendarEventPreviewReader'    => 'Markocupic\SacEventToolBundle\ModuleSacEventToolEventPreviewReader',
    'eventToolEventToolPilatusExport'        => 'Markocupic\SacEventToolBundle\ModuleSacEventToolPilatusExport',
    'eventToolEventToolJahresprogrammExport' => 'Markocupic\SacEventToolBundle\ModuleSacEventToolJahresprogrammExport',
    'eventToolActivateMemberAccount'         => 'Markocupic\SacEventToolBundle\ModuleSacEventToolActivateMemberAccount',
    'eventToolCsvExport'                     => 'Markocupic\SacEventToolBundle\ModuleSacEventToolCsvExport',
);

// Content Elements
$GLOBALS['TL_CTE']['sac_calendar_newsletter'] = array(
    'calendar_newsletter' => 'CalendarNewsletter',
);
$GLOBALS['TL_CTE']['sac_content_elements'] = array(
    'userPortrait'     => 'Markocupic\SacEventToolBundle\ContentUserPortrait',
    'userPortraitList' => 'Markocupic\SacEventToolBundle\ContentUserPortraitList',
    'cabanneSacList'   => 'Markocupic\SacEventToolBundle\ContentCabanneSacList',
    'cabanneSacDetail' => 'Markocupic\SacEventToolBundle\ContentCabanneSacDetail',
);

// Maintenance
$GLOBALS['TL_PURGE']['tables']['sac_event_story']['callback'] = array(
    'Markocupic\SacEventToolBundle\Maintenance\MaintainModuleEventStory',
    'run',
);

// Do not index a page if one of the following parameters is set
$GLOBALS['TL_NOINDEX_KEYS'][] = 'xhrAction';


// TL_CONFIG
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'] = array(
    'course',
    'tour',
    'lastMinuteTour',
    'generalEvent',
);

// Event state
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-STATE'] = array(
    'event_fully_booked',
    'event_canceled',
    'event_deferred',
);

// Tour report
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EXECUTION-STATE'] = array(
    'event_executed_like_predicted',
    'event_adapted',
    'event_canceled',
    'event_deferred',
);

// Backend user roles
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['SAC-EVENT-TOOL-AVALANCHE-LEVEL'] = array(
    'avalanche_level_0',
    'avalanche_level_1',
    'avalanche_level_2',
    'avalanche_level_3',
    'avalanche_level_4',
    'avalanche_level_5',
);


// Guide qualifications
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['leiterQualifikation'] = array(
    1 => "Tourenleiter SAC",
    2 => "Bergführer IVBV",
    3 => "Psychologe FSP",
    4 => "Scheesportlehrer",
    5 => "Dr. med.",
);

// TL_CONFIG
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'] = array(
    1  => "1",
    2  => "2",
    3  => "3",
    4  => "4",
    5  => "5",
    6  => "1 - 2",
    7  => "1 - 3",
    8  => "1 - 4",
    9  => "1 - 5",
    10 => "2 - 3",
    11 => "2 - 4",
    12 => "2 - 5",
    13 => "3 - 4",
    14 => "3 - 5",
    15 => "4 - 5",
);


$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'] = array(
    'ca. 1 h'           => array('dateRows' => 1),
    'ca. 2 h'           => array('dateRows' => 1),
    'ca. 3 h'           => array('dateRows' => 1),
    'ca. 4 h'           => array('dateRows' => 1),
    'ca. 5 h'           => array('dateRows' => 1),
    'ca. 6 h'           => array('dateRows' => 1),
    'ca. 7 h'           => array('dateRows' => 1),
    'ca. 8 h'           => array('dateRows' => 1),
    'ca. 9 h'           => array('dateRows' => 1),
    '1/2 Tag'           => array('dateRows' => 1),
    '1 Tag'             => array('dateRows' => 1),
    '1 1/2 Tage'        => array('dateRows' => 2),
    '2 Tage'            => array('dateRows' => 2),
    '2 1/2 Tage'        => array('dateRows' => 3),
    '3 Tage'            => array('dateRows' => 3),
    '3 1/2 Tage'        => array('dateRows' => 4),
    '4 Tage'            => array('dateRows' => 4),
    '4 1/2 Tage'        => array('dateRows' => 5),
    '5 Tage'            => array('dateRows' => 5),
    '5 1/2 Tage'        => array('dateRows' => 6),
    '6 Tage'            => array('dateRows' => 6),
    '6 1/2 Tage'        => array('dateRows' => 7),
    '7 Tage'            => array('dateRows' => 7),
    '7 1/2 Tage'        => array('dateRows' => 8),
    '8 Tage'            => array('dateRows' => 8),
    '8 1/2 Tage'        => array('dateRows' => 9),
    '9 Tage'            => array('dateRows' => 9),
    '9 1/2 Tage'        => array('dateRows' => 10),
    '10 Tage'           => array('dateRows' => 10),
    '10 1/2 Tage'       => array('dateRows' => 11),
    '11 Tage'           => array('dateRows' => 11),
    '11 1/2 Tage'       => array('dateRows' => 12),
    '12 Tage'           => array('dateRows' => 12),
    '12 1/2 Tage'       => array('dateRows' => 13),
    '13 Tage'           => array('dateRows' => 13),
    '13 1/2 Tage'       => array('dateRows' => 14),
    '14 Tage'           => array('dateRows' => 14),
    '1 Abend'           => array('dateRows' => 1),
    '2 Abende'          => array('dateRows' => 2),
    '3 Abende'          => array('dateRows' => 3),
    '4 Abende'          => array('dateRows' => 4),
    '5 Abende'          => array('dateRows' => 5),
    '6 Abende'          => array('dateRows' => 6),
    '7 Abende'          => array('dateRows' => 7),
    '1 Abend und 1 Tag' => array('dateRows' => 2)
);

// Car seats info used in the event registration form
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'] = array(
    'kein Auto',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
);

// Ticket info used in the event registration form
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'] = array(
    'GA',
    'Halbtax-Abo',
    'Nichts',
);

// Get system messages
$GLOBALS['TL_HOOKS']['getSystemMessages'][] = array('Markocupic\SacEventToolBundle\ContaoHooks\GetSystemMessages', 'listUntreatedEventSubscriptions');

// Custom Hook publish Event
$GLOBALS['TL_HOOKS']['publishEvent'] = array(array('Markocupic\SacEventToolBundle\ContaoHooks\PublishEvent', 'publishEvent'));

// Custom Hook change event release level
$GLOBALS['TL_HOOKS']['changeEventReleaseLevel'] = array(array('Markocupic\SacEventToolBundle\ContaoHooks\ChangeEventReleaseLevel', 'changeEventReleaseLevel'));

// Route cronjob calls,ajax calls etc...
$GLOBALS['TL_HOOKS']['initializeSystem'][] = array('markocupic_sac_event_tool.contao_hooks.initialize_system', 'initializeSystem');

// Route cronjob calls,ajax calls etc...
$GLOBALS['TL_HOOKS']['generatePage'][] = array('markocupic_sac_event_tool.contao_hooks.generate_page', 'generatePage');

/*** Handle Ajax calls from the backend **/
$GLOBALS['TL_HOOKS']['executePreActions'][] = array('markocupic_sac_event_tool.contao_hooks.execute_pre_actions', 'executePreActions');

/*** Handle custom rgxp in the backend **/
$GLOBALS['TL_HOOKS']['addCustomRegexp'][] = array('markocupic_sac_event_tool.contao_hooks.add_custom_regexp', 'addCustomRegexp');

/*** Handle event listing **/
$GLOBALS['TL_HOOKS']['getAllEvents'][] = array('markocupic_sac_event_tool.contao_hooks.get_all_events', 'getAllEvents');

/** Allow event preview from the backend if the event token is passed to the url */
$GLOBALS['TL_HOOKS']['getContentElement'][] = array('Markocupic\SacEventToolBundle\ContaoHooks\GetContentElement', 'getContentElement');

/*** Prepare User accounts (create user directories, etc.
 * @deprecated PostLogin Hook will be be removed in Contao 5.0.
 **/
$GLOBALS['TL_HOOKS']['postLogin'][] = array('Markocupic\SacEventToolBundle\ContaoHooks\PostLogin', 'prepareBeUserAccount');

/** Allow backend users to authenticate with their sacMemberId **/
$GLOBALS['TL_HOOKS']['importUser'][] = array('Markocupic\SacEventToolBundle\ContaoHooks\ImportUser', 'allowBackendUserToAuthenticateWithSacMemberId');


// Form HOOKS (f.ex. Kursanmeldung)
$GLOBALS['TL_HOOKS']['postUpload'][] = array('markocupic_sac_event_tool.contao_hooks.validate_forms', 'postUpload');
$GLOBALS['TL_HOOKS']['compileFormFields'][] = array('markocupic_sac_event_tool.contao_hooks.validate_forms', 'compileFormFields');
$GLOBALS['TL_HOOKS']['loadFormField'][] = array('markocupic_sac_event_tool.contao_hooks.validate_forms', 'loadFormField');
$GLOBALS['TL_HOOKS']['validateFormField'][] = array('markocupic_sac_event_tool.contao_hooks.validate_forms', 'validateFormField');
$GLOBALS['TL_HOOKS']['storeFormData'][] = array('markocupic_sac_event_tool.contao_hooks.validate_forms', 'storeFormData');
$GLOBALS['TL_HOOKS']['prepareFormData'][] = array('markocupic_sac_event_tool.contao_hooks.validate_forms', 'prepareFormData');
$GLOBALS['TL_HOOKS']['processFormData'][] = array('markocupic_sac_event_tool.contao_hooks.validate_forms', 'processFormData');

// Parse backend template hook
$GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = array('markocupic_sac_event_tool.contao_hooks.parse_backend_template', 'parseBackendTemplate');

// Cron jobs
$GLOBALS['TL_CRON']['daily']['replaceDefaultPassword'] = array('Markocupic\SacEventToolBundle\ReplaceDefaultPassword', 'sendNewPassword');
$GLOBALS['TL_CRON']['hourly']['syncSacMemberDatabase'] = array('Markocupic\SacEventToolBundle\Controller\CronjobController', 'syncSacMemberDatabase');
$GLOBALS['TL_CRON']['daily']['printSACWorkshops'] = array('Markocupic\SacEventToolBundle\Controller\CronjobController', 'printSacWorkshops');

// Replace insert tags
$GLOBALS['TL_HOOKS']['replaceInsertTags'][] = array('Markocupic\SacEventToolBundle\ContaoHooks\ReplaceInsertTags', 'replaceInsertTags');

// Parse template
$GLOBALS['TL_HOOKS']['parseTemplate'][] = array('Markocupic\SacEventToolBundle\ContaoHooks\ParseTemplate', 'checkIfAccountIsActivated');

