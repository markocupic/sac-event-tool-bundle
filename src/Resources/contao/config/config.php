<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

$rootDir = Contao\System::getContainer()->getParameter('kernel.project_dir');


/*** purge script cache for development use only **/
// purgeScriptCache() is called in Markocupic\SacEventToolBundle\ContaoHooks\GeneratePage::generatePage;
$GLOBALS['TL_CONFIG']['purgeScriptCache'] = false;

// Add notification center configs
require_once($rootDir . '/vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/config/notification_center_config.php');

// include some constants like FTP Credentials SAC Switzerland etc.
require_once $rootDir . '/sac_event_tool_parameters.php';

// include custom functions
require_once($rootDir . '/vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/functions/functions.php');


if (TL_MODE == 'BE')
{
    // Add Backend CSS
    $GLOBALS['TL_CSS'][] = 'bundles/markocupicsaceventtool/css/be_sac-event_tool-bundle.css';
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

    'sac_tour_difficulty_tool' => array(
        'tables' => array('tl_tour_difficulty_category', 'tl_tour_difficulty'),
        'table'  => array('TableWizard', 'importTable'),
        'list'   => array('ListWizard', 'importList'),
    ),
    'sac_tour_type_tool'       => array(
        'tables' => array('tl_tour_type'),
    ),
    'sac_event_release_tool'   => array(
        'tables' => array('tl_event_release_level_policy_package', 'tl_event_release_level_policy'),
        'table'  => array('TableWizard', 'importTable'),
        'list'   => array('ListWizard', 'importList'),
    ),
    'sac_event_organizer_tool' => array(
        'tables' => array('tl_event_organizer'),
        'table'  => array('TableWizard', 'importTable'),
        'list'   => array('ListWizard', 'importList'),
    ),
    'sac_event_journey_tool'   => array(
        'tables' => array('tl_calendar_events_journey'),
    ),
    'sac_cabanne_tool'         => array(
        'tables' => array('tl_cabanne_sac'),
    ),
);

// Add permissions
$GLOBALS['TL_PERMISSIONS'][] = 'calendar_containers';
$GLOBALS['TL_PERMISSIONS'][] = 'calendar_containerp';


// Frontend Modules
$GLOBALS['FE_MOD']['sac_event_tool_fe_modules'] = array(
    'eventToolFrontendUserDashboard'    => 'Markocupic\SacEventToolBundle\ModuleSacEventToolMemberDashboard',
    'eventToolEventRegistrationForm'    => 'Markocupic\SacEventToolBundle\ModuleSacEventToolEventRegistrationForm',
    'eventToolCalendarEventStoryList'   => 'Markocupic\SacEventToolBundle\ModuleSacEventToolCalendarEventStoryList',
    'eventToolCalendarEventStoryReader' => 'Markocupic\SacEventToolBundle\ModuleSacEventToolCalendarEventStoryReader',
    'eventToolCalendarEventlist'        => 'Markocupic\SacEventToolBundle\ModuleSacEventToolEventlist',
);

// Content Elements
$GLOBALS['TL_CTE']['sac_calendar_newsletter'] = array(
    'calendar_newsletter' => 'CalendarNewsletter',
);
$GLOBALS['TL_CTE']['sac_content_elements'] = array(
    'ce_user_portrait' => 'Markocupic\SacEventToolBundle\ContentUserPortrait',
    'cabanneSacDetail' => 'Markocupic\CabanneSacDetail',

);

// Do not index a page if one of the following parameters is set
$GLOBALS['TL_NOINDEX_KEYS'][] = 'xhrAction';


// TL_CONFIG
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'] = array(
    'course'         => 'Kurs',
    'tour'           => 'Tour',
    'lastMinuteTour' => 'Last Minute Tour',
);

$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['role'] = array(
    'tourguide',
    'courseguide',
    'president',
    'executivemember',
    'tourchief',
    'coursechief',
);

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
    '',
    '1/2 Tag',
    '1 Tag',
    '1 1/2 Tage',
    '2 Tage',
    '2 1/2 Tage',
    '3 Tage',
    '3 1/2 Tage',
    '4 Tage',
    '4 1/2 Tage',
    '5 Tage',
    '5 1/2 Tage',
    '6 Tage',
    '6 1/2 Tage',
    '7 Tage',
    '7 Tage',
    '1 Abend',
    '2 Abende',
    '3 Abende',
    '4 Abende',
    '5 Abende',
    '6 Abende',
    '7 Abende',
    '1 Abend und 1 Tag',
);


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

/*** Prepare USer accounts (create user directories, etc. **/
$GLOBALS['TL_HOOKS']['postLogin'][] = array('markocupic_sac_event_tool.contao_hooks.post_login', 'prepareBeUserAccount');


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
