<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\System;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use Markocupic\SacEventToolBundle\ContaoScope\ContaoScope;
use Markocupic\SacEventToolBundle\Cron\Contao\DailyCron;
use Markocupic\SacEventToolBundle\Cron\Contao\HourlyCron;

$projectDir = System::getContainer()->getParameter('kernel.project_dir');

/** @var ContaoScope $contaoScope */
$contaoScope = System::getContainer()->get('Markocupic\SacEventToolBundle\ContaoScope\ContaoScope');

// Add notification center configs
require_once $projectDir.'/vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/config/notification_center_config.php';

// include custom functions
require_once $projectDir.'/vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/functions/functions.php';

if ($contaoScope->isBackend()) {
    // Add Backend CSS
    $GLOBALS['TL_CSS'][] = 'bundles/markocupicsaceventtool/css/be_stylesheet.css|static';

    // Add Backend javascript
    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicsaceventtool/js/backend_edit_all_navbar_helper.js';
}

$GLOBALS['BE_MOD']['content']['calendar']['tables'] = ['tl_calendar_container', 'tl_calendar', 'tl_calendar_events', 'tl_calendar_events_instructor_invoice', 'tl_calendar_feed', 'tl_content', 'tl_calendar_events_member'];
$GLOBALS['BE_MOD']['sac_be_modules'] = [
    'sac_section_tool' => [
        'tables' => ['tl_sac_section'],
    ],
    'sac_calendar_events_tool' => [
        'tables' => ['tl_calendar_container', 'tl_calendar', 'tl_calendar_events', 'tl_calendar_events_instructor_invoice', 'tl_calendar_feed', 'tl_content', 'tl_calendar_events_member'],
        'table' => ['TableWizard', 'importTable'],
        'list' => ['ListWizard', 'importList'],
    ],
    'sac_calendar_events_stories_tool' => [
        'tables' => ['tl_calendar_events_story'],
    ],
    'sac_course_main_types_tool' => [
        'tables' => ['tl_course_main_type'],
    ],
    'sac_course_sub_types_tool' => [
        'tables' => ['tl_course_sub_type'],
    ],
    'sac_event_type_tool' => [
        'tables' => ['tl_event_type'],
    ],
    'sac_tour_difficulty_tool' => [
        'tables' => ['tl_tour_difficulty_category', 'tl_tour_difficulty'],
        'table' => ['TableWizard', 'importTable'],
        'list' => ['ListWizard', 'importList'],
    ],
    'sac_tour_type_tool' => [
        'tables' => ['tl_tour_type'],
    ],
    'sac_event_release_tool' => [
        'tables' => ['tl_event_release_level_policy_package', 'tl_event_release_level_policy'],
        'table' => ['TableWizard', 'importTable'],
        'list' => ['ListWizard', 'importList'],
    ],
    'sac_event_organizer_tool' => [
        'tables' => ['tl_event_organizer'],
        'table' => ['TableWizard', 'importTable'],
        'list' => ['ListWizard', 'importList'],
    ],
    'sac_event_journey_tool' => [
        'tables' => ['tl_calendar_events_journey'],
    ],
    'sac_user_role_tool' => [
        'tables' => ['tl_user_role'],
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

// TL_CONFIG
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'] = [
    'course',
    'tour',
    'lastMinuteTour',
    'generalEvent',
];

// Event member subscription state !Please do not change these settings because the states are hardcoded
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'] = [
    EventSubscriptionLevel::SUBSCRIPTION_NOT_CONFIRMED,
    EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED,
    EventSubscriptionLevel::SUBSCRIPTION_REJECTED,
    EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED,
    EventSubscriptionLevel::USER_HAS_UNSUBSCRIBED,
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
    1 => 'Tourenleiter/in SAC',
    2 => 'Bergführer/in IVBV',
    3 => 'Psychologe/in FSP',
    4 => 'Scheesportlehrer/in',
    5 => 'Dr. med.',
    6 => 'J+S Leiter/in',
    7 => 'Wanderleiter/in',
    8 => 'IGKA Instruktor/in',
];

$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['userRescissionCause'] = [
    'deceased', // verstorben
    'recission', // Rücktritt
    'leaving', // Austritt
];

// TL_CONFIG
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'] = [
    1 => '1',
    2 => '2',
    3 => '3',
    4 => '4',
    5 => '5',
    6 => '1 - 2',
    7 => '1 - 3',
    8 => '1 - 4',
    9 => '1 - 5',
    10 => '2 - 3',
    11 => '2 - 4',
    12 => '2 - 5',
    13 => '3 - 4',
    14 => '3 - 5',
    15 => '4 - 5',
];

$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'] = [
    'ca. 1 h' => ['dateRows' => 1],
    'ca. 2 h' => ['dateRows' => 1],
    'ca. 3 h' => ['dateRows' => 1],
    'ca. 4 h' => ['dateRows' => 1],
    'ca. 5 h' => ['dateRows' => 1],
    'ca. 6 h' => ['dateRows' => 1],
    'ca. 7 h' => ['dateRows' => 1],
    'ca. 8 h' => ['dateRows' => 1],
    'ca. 9 h' => ['dateRows' => 1],
    '1/2 Tag' => ['dateRows' => 1],
    '1 Tag' => ['dateRows' => 1],
    '1 1/2 Tage' => ['dateRows' => 2],
    '2 Tage' => ['dateRows' => 2],
    '2 1/2 Tage' => ['dateRows' => 3],
    '3 Tage' => ['dateRows' => 3],
    '3 1/2 Tage' => ['dateRows' => 4],
    '4 Tage' => ['dateRows' => 4],
    '4 1/2 Tage' => ['dateRows' => 5],
    '5 Tage' => ['dateRows' => 5],
    '5 1/2 Tage' => ['dateRows' => 6],
    '6 Tage' => ['dateRows' => 6],
    '6 1/2 Tage' => ['dateRows' => 7],
    '7 Tage' => ['dateRows' => 7],
    '7 1/2 Tage' => ['dateRows' => 8],
    '8 Tage' => ['dateRows' => 8],
    '8 1/2 Tage' => ['dateRows' => 9],
    '9 Tage' => ['dateRows' => 9],
    '9 1/2 Tage' => ['dateRows' => 10],
    '10 Tage' => ['dateRows' => 10],
    '10 1/2 Tage' => ['dateRows' => 11],
    '11 Tage' => ['dateRows' => 11],
    '11 1/2 Tage' => ['dateRows' => 12],
    '12 Tage' => ['dateRows' => 12],
    '12 1/2 Tage' => ['dateRows' => 13],
    '13 Tage' => ['dateRows' => 13],
    '13 1/2 Tage' => ['dateRows' => 14],
    '14 Tage' => ['dateRows' => 14],
    '1 Abend' => ['dateRows' => 1],
    '2 Abende' => ['dateRows' => 2],
    '3 Abende' => ['dateRows' => 3],
    '4 Abende' => ['dateRows' => 4],
    '5 Abende' => ['dateRows' => 5],
    '6 Abende' => ['dateRows' => 6],
    '7 Abende' => ['dateRows' => 7],
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
    'Nichts',
    'GA',
    'Halbtax-Abo',
];

/* Cron jobs */
$GLOBALS['TL_CRON']['daily']['SAC_EVT_DAILY'] = [DailyCron::class, 'dailyCron'];
$GLOBALS['TL_CRON']['hourly']['SAC_EVT_HOURLY'] = [HourlyCron::class, 'hourlyCron'];
