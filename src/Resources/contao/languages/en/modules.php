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

use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationCheckoutLinkController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardWriteEventArticleController;

// Backend Modules
$GLOBALS['TL_LANG']['MOD']['sac_be_modules'] = ['SAC Module'];
$GLOBALS['TL_LANG']['MOD']['sac_section_tool'] = ['SAC Sektionen und OG'];
$GLOBALS['TL_LANG']['MOD']['sac_course_main_types_tool'] = ['Kurs-Hauptkategorien'];
$GLOBALS['TL_LANG']['MOD']['sac_course_sub_types_tool'] = ['Kurs-Unterkategorien'];
$GLOBALS['TL_LANG']['MOD']['sac_calendar_events_tool'] = ['SAC Event-Tool'];
$GLOBALS['TL_LANG']['MOD']['sac_member_database'] = ['SAC Mitgliederdatenbank'];
$GLOBALS['TL_LANG']['MOD']['sac_calendar_events_stories_tool'] = ['Touren-/Kursberichte Tool'];
$GLOBALS['TL_LANG']['MOD']['sac_tour_difficulty_tool'] = ['Schwierigkeitsgrade für Touren'];
$GLOBALS['TL_LANG']['MOD']['sac_tour_type_tool'] = ['Tourentypen'];
$GLOBALS['TL_LANG']['MOD']['sac_event_release_tool'] = ['Event-Freigabestufen-Tool'];
$GLOBALS['TL_LANG']['MOD']['sac_event_organizer_tool'] = ['Organisierende Gruppen'];
$GLOBALS['TL_LANG']['MOD']['sac_event_journey_tool'] = ['Event Anreise-Art-Tool'];
$GLOBALS['TL_LANG']['MOD']['sac_user_role_tool'] = ['Vereinsfunktionen-Tool'];
$GLOBALS['TL_LANG']['MOD']['sac_event_type_tool'] = ['SAC Event-Art-Tool'];

// Frontend modules
$GLOBALS['TL_LANG']['FMD']['sac_event_tool_frontend_modules'] = ['SAC Frontend Module'];
$GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventPreviewReader'] = ['Event Reader Vorschau'];

// Contao 5 ready frontend modules
$GLOBALS['TL_LANG']['FMD']['tour_difficulty_list'] = ['Schwierigkeitsgrade Tabelle mit Erklärungen als Modalfenster'];
$GLOBALS['TL_LANG']['FMD']['csv_event_member_export'] = ['SAC-Event-Teilnehmer Export'];
$GLOBALS['TL_LANG']['FMD'][EventRegistrationController::TYPE] = ['SAC Event Registrierungsformular'];
$GLOBALS['TL_LANG']['FMD'][EventRegistrationCheckoutLinkController::TYPE] = ['Link zur Checkout-Seite für Event-Anmeldung'];
$GLOBALS['TL_LANG']['FMD']['member_dashboard_upcoming_events'] = ['SAC Mitgliederkonto Dashboard - Meine nächsten Events'];
$GLOBALS['TL_LANG']['FMD']['member_dashboard_past_events'] = ['SAC Mitgliederkonto Dashboard - Meine absolvierten Events'];
$GLOBALS['TL_LANG']['FMD']['member_dashboard_event_report_list'] = ['SAC Mitgliederkonto Dashboard - Meine Tourenberichte'];
$GLOBALS['TL_LANG']['FMD'][MemberDashboardWriteEventArticleController::TYPE] = ['SAC Mitgliederkonto Dashboard - Tourenbericht schreiben'];
$GLOBALS['TL_LANG']['FMD'][MemberDashboardProfileController::TYPE] = ['SAC Mitgliederkonto Dashboard - Profil'];
$GLOBALS['TL_LANG']['FMD']['member_dashboard_edit_profile'] = ['SAC Mitgliederkonto Dashboard - Profil bearbeiten'];
$GLOBALS['TL_LANG']['FMD']['member_dashboard_delete_profile'] = ['SAC Mitgliederkonto Dashboard - Profil löschen'];
$GLOBALS['TL_LANG']['FMD']['member_dashboard_avatar_upload'] = ['SAC Mitgliederkonto Dashboard - Avatar-Upload-Formular'];
$GLOBALS['TL_LANG']['FMD']['member_dashboard_avatar'] = ['SAC Mitgliederkonto Dashboard - Mitglieder Avatar'];
$GLOBALS['TL_LANG']['FMD']['csv_export'] = ['SAC-Member- und Backend-User CSV-Export-Funktion'];
$GLOBALS['TL_LANG']['FMD']['event_filter_form'] = ['SAC-Event-Liste-Filter'];
$GLOBALS['TL_LANG']['FMD']['pilatus_export'] = ['SAC Event-Export für Monatszeitschrift'];
$GLOBALS['TL_LANG']['FMD']['pilatus_export_2021'] = ['SAC Event-Export 2021 für Monatszeitschrift'];
$GLOBALS['TL_LANG']['FMD']['jahresprogramm_export'] = ['SAC Event-Export für Jahresprogramm'];
$GLOBALS['TL_LANG']['FMD']['event_story_list'] = ['SAC Tourenberichte Listen Modul'];
$GLOBALS['TL_LANG']['FMD']['event_story_reader'] = ['SAC Tourenberichte Reader Modul'];
$GLOBALS['TL_LANG']['FMD']['event_list'] = ['SAC Event Auflistungs Modul'];
