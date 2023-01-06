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

use Contao\System;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use Markocupic\SacEventToolBundle\ContaoBackendMaintainance\MaintainBackendUser;
use Markocupic\SacEventToolBundle\Model\CalendarContainerModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\CourseMainTypeModel;
use Markocupic\SacEventToolBundle\Model\CourseSubTypeModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyPackageModel;
use Markocupic\SacEventToolBundle\Model\EventTypeModel;
use Markocupic\SacEventToolBundle\Model\SacSectionModel;
use Markocupic\SacEventToolBundle\Model\TourDifficultyCategoryModel;
use Markocupic\SacEventToolBundle\Model\TourDifficultyModel;
use Markocupic\SacEventToolBundle\Model\TourTypeModel;
use Markocupic\SacEventToolBundle\Model\UserRoleModel;

$projectDir = System::getContainer()->getParameter('kernel.project_dir');

/**
 * Add notification center configs
 */
require_once $projectDir.'/vendor/markocupic/sac-event-tool-bundle/contao/config/notification_center_config.php';

/*
 * Contao backend modules
 */
$GLOBALS['BE_MOD']['content']['calendar']['tables'] = ['tl_calendar_container', 'tl_calendar', 'tl_calendar_events', 'tl_calendar_events_instructor_invoice', 'tl_calendar_feed', 'tl_content', 'tl_calendar_events_member'];
$GLOBALS['BE_MOD']['sac_be_modules'] = [
	'sac_section_tool'           => [
		'tables' => ['tl_sac_section'],
	],
	'sac_calendar_events_tool'   => [
		'tables' => ['tl_calendar_container', 'tl_calendar', 'tl_calendar_events', 'tl_calendar_events_instructor_invoice', 'tl_calendar_feed', 'tl_content', 'tl_calendar_events_member'],
		'table'  => ['TableWizard', 'importTable'],
		'list'   => ['ListWizard', 'importList'],
	],
	'sac_course_main_types_tool' => [
		'tables' => ['tl_course_main_type'],
	],
	'sac_course_sub_types_tool'  => [
		'tables' => ['tl_course_sub_type'],
	],
	'sac_event_type_tool'        => [
		'tables' => ['tl_event_type'],
	],
	'sac_tour_difficulty_tool'   => [
		'tables' => ['tl_tour_difficulty_category', 'tl_tour_difficulty'],
		'table'  => ['TableWizard', 'importTable'],
		'list'   => ['ListWizard', 'importList'],
	],
	'sac_tour_type_tool'         => [
		'tables' => ['tl_tour_type'],
	],
	'sac_event_release_tool'     => [
		'tables' => ['tl_event_release_level_policy_package', 'tl_event_release_level_policy'],
		'table'  => ['TableWizard', 'importTable'],
		'list'   => ['ListWizard', 'importList'],
	],
	'sac_event_organizer_tool'   => [
		'tables' => ['tl_event_organizer'],
		'table'  => ['TableWizard', 'importTable'],
		'list'   => ['ListWizard', 'importList'],
	],
	'sac_event_journey_tool'     => [
		'tables' => ['tl_calendar_events_journey'],
	],
	'sac_user_role_tool'         => [
		'tables' => ['tl_user_role'],
	],
];

/**
 * Register the models
 */
$GLOBALS['TL_MODELS'][CalendarContainerModel::getTable()] = CalendarContainerModel::class;
$GLOBALS['TL_MODELS'][CalendarEventsInstructorInvoiceModel::getTable()] = CalendarEventsInstructorInvoiceModel::class;
$GLOBALS['TL_MODELS'][CalendarEventsInstructorModel::getTable()] = CalendarEventsInstructorModel::class;
$GLOBALS['TL_MODELS'][CalendarEventsJourneyModel::getTable()] = CalendarEventsJourneyModel::class;
$GLOBALS['TL_MODELS'][CalendarEventsMemberModel::getTable()] = CalendarEventsMemberModel::class;
$GLOBALS['TL_MODELS'][CourseMainTypeModel::getTable()] = CourseMainTypeModel::class;
$GLOBALS['TL_MODELS'][CourseSubTypeModel::getTable()] = CourseSubTypeModel::class;
$GLOBALS['TL_MODELS'][EventOrganizerModel::getTable()] = EventOrganizerModel::class;
$GLOBALS['TL_MODELS'][EventReleaseLevelPolicyModel::getTable()] = EventReleaseLevelPolicyModel::class;
$GLOBALS['TL_MODELS'][EventReleaseLevelPolicyPackageModel::getTable()] = EventReleaseLevelPolicyPackageModel::class;
$GLOBALS['TL_MODELS'][EventTypeModel::getTable()] = EventTypeModel::class;
$GLOBALS['TL_MODELS'][SacSectionModel::getTable()] = SacSectionModel::class;
$GLOBALS['TL_MODELS'][TourDifficultyCategoryModel::getTable()] = TourDifficultyCategoryModel::class;
$GLOBALS['TL_MODELS'][TourDifficultyModel::getTable()] = TourDifficultyModel::class;
$GLOBALS['TL_MODELS'][TourTypeModel::getTable()] = TourTypeModel::class;
$GLOBALS['TL_MODELS'][UserRoleModel::getTable()] = UserRoleModel::class;

/*
 * Backend maintenance: Clear backend user permissions,
 * who inherit group permissions from tl_user_group
 * and tl_user-admin = ''
 * and tl_user.inherit = 'extend'
 */
$GLOBALS['TL_PURGE']['custom']['reset_backend_user_rights'] = [
	'callback' => [MaintainBackendUser::class, 'resetBackendUserRights'],
];

/*
 * Contao backend permissions
 */
$GLOBALS['TL_PERMISSIONS'][] = 'calendar_containers';
$GLOBALS['TL_PERMISSIONS'][] = 'calendar_containerp';

/*
 * Legacy Contao frontend modules
 * Contao 5 ready fe modules are registered in controller-frontend-module.yml
 */
$GLOBALS['FE_MOD']['sac_event_tool_frontend_modules'] = [
	'eventToolCalendarEventPreviewReader' => 'Markocupic\SacEventToolBundle\ModuleSacEventToolEventPreviewReader',
];

// TL_CONFIG
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'] = [
	'course',
	'tour',
	'lastMinuteTour',
	'generalEvent',
];

/**
 * Event member subscription state !Please do not change these settings because the states are hardcoded
 */
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'] = [
	EventSubscriptionLevel::SUBSCRIPTION_NOT_CONFIRMED,
	EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED,
	EventSubscriptionLevel::SUBSCRIPTION_REJECTED,
	EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED,
	EventSubscriptionLevel::USER_HAS_UNSUBSCRIBED,
];

/**
 * Avalanche levels
 */
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['SAC-EVENT-TOOL-AVALANCHE-LEVEL'] = [
	'avalanche_level_0',
	'avalanche_level_1',
	'avalanche_level_2',
	'avalanche_level_3',
	'avalanche_level_4',
	'avalanche_level_5',
];

/**
 * Tourguide qualifications
 */
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

/**
 * Backend user rescission/retirement cause
 */
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['userRescissionCause'] = [
	'deceased', // verstorben
	'recission', // Rücktritt
	'leaving', // Austritt
];

/**
 * Course levels
 */
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'] = [
	1  => '1',
	2  => '2',
	3  => '3',
	4  => '4',
	5  => '5',
	6  => '1 - 2',
	7  => '1 - 3',
	8  => '1 - 4',
	9  => '1 - 5',
	10 => '2 - 3',
	11 => '2 - 4',
	12 => '2 - 5',
	13 => '3 - 4',
	14 => '3 - 5',
	15 => '4 - 5',
];

/**
 * Event durations
 */
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

/**
 * Car seats info: We use that in the event registration form
 */
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

/**
 * Ticket info: We use that in the event registration form.
 */
$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'] = [
	'Nichts',
	'GA',
	'Halbtax-Abo',
];
