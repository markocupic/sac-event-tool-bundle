<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$projectDir = \Contao\System::getContainer()->getParameter('kernel.project_dir');

/** @var Markocupic\SacEventToolBundle\ContaoMode\ContaoMode $contaoMode */
$contaoMode = \Contao\System::getContainer()->get('Markocupic\SacEventToolBundle\ContaoMode\ContaoMode');

// Add notification center configs
require_once($projectDir . '/vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/config/notification_center_config.php');

// include custom functions
require_once($projectDir . '/vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/functions/functions.php');

if ($contaoMode->isBackend())
{
    // Add Backend CSS
    $GLOBALS['TL_CSS'][] = 'bundles/markocupicsaceventtool/css/be_stylesheet.css|static';

    // Add Backend javascript
    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicsaceventtool/js/backend_edit_all_navbar_helper.js';
}

$GLOBALS['BE_MOD']['content']['calendar']['tables'] = ['tl_calendar_container', 'tl_calendar', 'tl_calendar_events', 'tl_calendar_events_instructor_invoice', 'tl_calendar_feed', 'tl_content', 'tl_calendar_events_member'];
$GLOBALS['BE_MOD']['sac_be_modules'] = [
    'sac_calendar_events_tool'         => [
        'tables' => ['tl_calendar_container', 'tl_calendar', 'tl_calendar_events', 'tl_calendar_events_instructor_invoice', 'tl_calendar_feed', 'tl_content', 'tl_calendar_events_member'],
        'table'  => ['TableWizard', 'importTable'],
        'list'   => ['ListWizard', 'importList'],
    ],
    'sac_calendar_events_stories_tool' => [
        'tables' => ['tl_calendar_events_story'],
    ],
    'sac_course_main_types_tool'       => [
        'tables' => ['tl_course_main_type'],
    ],
    'sac_course_sub_types_tool'        => [
        'tables' => ['tl_course_sub_type'],
    ],
    'sac_event_type_tool'              => [
        'tables' => ['tl_event_type'],
    ],
    'sac_tour_difficulty_tool'         => [
        'tables' => ['tl_tour_difficulty_category', 'tl_tour_difficulty'],
        'table'  => ['TableWizard', 'importTable'],
        'list'   => ['ListWizard', 'importList'],
    ],
    'sac_tour_type_tool'               => [
        'tables' => ['tl_tour_type'],
    ],
    'sac_event_release_tool'           => [
        'tables' => ['tl_event_release_level_policy_package', 'tl_event_release_level_policy'],
        'table'  => ['TableWizard', 'importTable'],
        'list'   => ['ListWizard', 'importList'],
    ],
    'sac_event_organizer_tool'         => [
        'tables' => ['tl_event_organizer'],
        'table'  => ['TableWizard', 'importTable'],
        'list'   => ['ListWizard', 'importList'],
    ],
    'sac_event_journey_tool'           => [
        'tables' => ['tl_calendar_events_journey'],
    ],
    'sac_cabanne_tool'                 => [
        'tables' => ['tl_cabanne_sac'],
    ],
    'sac_user_role_tool'               => [
        'tables' => ['tl_user_role'],
    ],
    'sac_user_temp'                    => [
        'tables' => ['tl_user_temp'],
    ],
];

// Add permissions
$GLOBALS['TL_PERMISSIONS'][] = 'calendar_containers';
$GLOBALS['TL_PERMISSIONS'][] = 'calendar_containerp';

// Frontend Modules Contao 4 style
// Contao 5 ready fe modules are registered in controller-frontend-module.yml
$GLOBALS['FE_MOD']['sac_event_tool_frontend_modules'] = [
    'eventToolCalendarEventPreviewReader' => 'Markocupic\SacEventToolBundle\ModuleSacEventToolEventPreviewReader',
];

// Maintenance
// Delete unused event-story folders
$GLOBALS['TL_PURGE']['custom']['sac_event_story'] = [
    'callback' => ['Markocupic\SacEventToolBundle\ContaoBackendMaintenance\MaintainModuleEventStory', 'run'],
];

// Do not index a page if one of the following parameters is set
//$GLOBALS['TL_NOINDEX_KEYS'][] = 'xhrAction';

// TL_CONFIG
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'] = [
    'course',
    'tour',
    'lastMinuteTour',
    'generalEvent',
];

// Event state
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-STATE'] = [
    'event_fully_booked',
    'event_canceled',
    'event_deferred',
];

// Tour report
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EXECUTION-STATE'] = [
    'event_executed_like_predicted',
    'event_adapted',
    'event_canceled',
    'event_deferred',
];

// Backend user roles
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['SAC-EVENT-TOOL-AVALANCHE-LEVEL'] = [
    'avalanche_level_0',
    'avalanche_level_1',
    'avalanche_level_2',
    'avalanche_level_3',
    'avalanche_level_4',
    'avalanche_level_5',
];

// Guide qualifications
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['leiterQualifikation'] = [
    1 => "Tourenleiter SAC",
    2 => "Bergführer IVBV",
    3 => "Psychologe FSP",
    4 => "Scheesportlehrer",
    5 => "Dr. med.",
];

$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['userRescissionCause'] = [
    'deceased', // verstorben
    'recission', // Rücktritt
    'leaving', // Austritt
];

// TL_CONFIG
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'] = [
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
];

$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'] = [
    'ca. 1 h'           => ['dateRows' => 1],
    'ca. 2 h'           => ['dateRows' => 1],
    'ca. 3 h'           => ['dateRows' => 1],
    'ca. 4 h'           => ['dateRows' => 1],
    'ca. 5 h'           => ['dateRows' => 1],
    'ca. 6 h'           => ['dateRows' => 1],
    'ca. 7 h'           => ['dateRows' => 1],
    'ca. 8 h'           => ['dateRows' => 1],
    'ca. 9 h'           => ['dateRows' => 1],
    '1/2 Tag'           => ['dateRows' => 1],
    '1 Tag'             => ['dateRows' => 1],
    '1 1/2 Tage'        => ['dateRows' => 2],
    '2 Tage'            => ['dateRows' => 2],
    '2 1/2 Tage'        => ['dateRows' => 3],
    '3 Tage'            => ['dateRows' => 3],
    '3 1/2 Tage'        => ['dateRows' => 4],
    '4 Tage'            => ['dateRows' => 4],
    '4 1/2 Tage'        => ['dateRows' => 5],
    '5 Tage'            => ['dateRows' => 5],
    '5 1/2 Tage'        => ['dateRows' => 6],
    '6 Tage'            => ['dateRows' => 6],
    '6 1/2 Tage'        => ['dateRows' => 7],
    '7 Tage'            => ['dateRows' => 7],
    '7 1/2 Tage'        => ['dateRows' => 8],
    '8 Tage'            => ['dateRows' => 8],
    '8 1/2 Tage'        => ['dateRows' => 9],
    '9 Tage'            => ['dateRows' => 9],
    '9 1/2 Tage'        => ['dateRows' => 10],
    '10 Tage'           => ['dateRows' => 10],
    '10 1/2 Tage'       => ['dateRows' => 11],
    '11 Tage'           => ['dateRows' => 11],
    '11 1/2 Tage'       => ['dateRows' => 12],
    '12 Tage'           => ['dateRows' => 12],
    '12 1/2 Tage'       => ['dateRows' => 13],
    '13 Tage'           => ['dateRows' => 13],
    '13 1/2 Tage'       => ['dateRows' => 14],
    '14 Tage'           => ['dateRows' => 14],
    '1 Abend'           => ['dateRows' => 1],
    '2 Abende'          => ['dateRows' => 2],
    '3 Abende'          => ['dateRows' => 3],
    '4 Abende'          => ['dateRows' => 4],
    '5 Abende'          => ['dateRows' => 5],
    '6 Abende'          => ['dateRows' => 6],
    '7 Abende'          => ['dateRows' => 7],
    '1 Abend und 1 Tag' => ['dateRows' => 2],
];

// Car seats info used in the event registration form
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'] = [
    'kein Auto',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
];

// Ticket info used in the event registration form
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'] = [
    'GA',
    'Halbtax-Abo',
    'Nichts',
];

// CONTAO HOOKS:
/** Get page layout: purge script cache in dev mode **/
$GLOBALS['TL_HOOKS']['getPageLayout'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\GetPageLayoutListener', 'purgeScriptCacheInDebugMode'];

/** Get system messages: list untreated event subscriptions in the backend (start page) **/
$GLOBALS['TL_HOOKS']['getSystemMessages'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\GetSystemMessagesListener', 'listUntreatedEventSubscriptions'];

/** Custom Hook publish Event **/
if (!isset($GLOBALS['TL_HOOKS']['publishEvent']) || !is_array($GLOBALS['TL_HOOKS']['publishEvent']))
{
    $GLOBALS['TL_HOOKS']['publishEvent'] = [];
}
$GLOBALS['TL_HOOKS']['publishEvent'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\PublishEventListener', 'onPublishEvent'];

/** Custom Hook change event release level **/
if (!isset($GLOBALS['TL_HOOKS']['changeEventReleaseLevel']) || !is_array($GLOBALS['TL_HOOKS']['changeEventReleaseLevel']))
{
    $GLOBALS['TL_HOOKS']['changeEventReleaseLevel'] = [];
}
$GLOBALS['TL_HOOKS']['changeEventReleaseLevel'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\ChangeEventReleaseLevelListener', 'onChangeEventReleaseLevel'];

/** Route prepare plugin environment **/
$GLOBALS['TL_HOOKS']['initializeSystem'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\InitializeSystemListener', 'preparePluginEnvironment'];

/** Handle Ajax calls from the backend **/
$GLOBALS['TL_HOOKS']['executePreActions'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\ExecutePreActionsListener', 'onExecutePreActions'];

/** Handle custom rgxp in the backend **/
$GLOBALS['TL_HOOKS']['addCustomRegexp'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\AddCustomRegexpListener', 'onAddCustomRegexp'];

/** Prepare User accounts (create user directories, etc.
 * @deprecated PostLogin Hook will be be removed in Contao 5.0.
 **/
$GLOBALS['TL_HOOKS']['postLogin'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\PostLoginListener', 'onPostLogin'];

/** Allow backend users to authenticate with their sacMemberId **/
$GLOBALS['TL_HOOKS']['importUser'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\ImportUserListener', 'onImportUser'];

/** Parse backend template hook **/
$GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\ParseBackendTemplateListener', 'onParseBackendTemplate'];

/** Replace insert tags **/
$GLOBALS['TL_HOOKS']['replaceInsertTags'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\ReplaceInsertTagsListener', 'onReplaceInsertTags'];

/** Parse template (Check if frontend login is allowed, if not replace the default error message and redirect to account activation page) */
$GLOBALS['TL_HOOKS']['parseTemplate'][] = ['Markocupic\SacEventToolBundle\EventListener\Contao\ParseTemplateListener', 'onParseTemplate'];

/** Cron jobs **/
$GLOBALS['TL_CRON']['monthly']['replaceDefaultPassword'] = [Markocupic\SacEventToolBundle\Cron\Contao\MonthlyCron::class, 'replaceDefaultPasswordAndSendNew'];
$GLOBALS['TL_CRON']['hourly']['syncSacMemberDatabase'] = [Markocupic\SacEventToolBundle\Cron\Contao\HourlyCron::class, 'syncSacMemberDatabase'];
$GLOBALS['TL_CRON']['daily']['generateWorkshopPdfBooklet'] = [Markocupic\SacEventToolBundle\Cron\Contao\DailyCron::class, 'generateWorkshopPdfBooklet'];
$GLOBALS['TL_CRON']['daily']['anonymizeOrphanedCalendarEventsMemberDataRecords'] = [Markocupic\SacEventToolBundle\Cron\Contao\DailyCron::class, 'anonymizeOrphanedCalendarEventsMemberDataRecords'];

