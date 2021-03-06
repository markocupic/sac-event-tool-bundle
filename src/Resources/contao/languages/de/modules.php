<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */


use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationCheckoutLinkController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardWriteEventArticleController;

// Backend Modules
$GLOBALS['TL_LANG']['MOD']['sac_be_modules'] = array('SAC Module');
$GLOBALS['TL_LANG']['MOD']['sac_course_main_types_tool'] = array('Kurs-Hauptkategorien');
$GLOBALS['TL_LANG']['MOD']['sac_course_sub_types_tool'] = array('Kurs-Unterkategorien');
$GLOBALS['TL_LANG']['MOD']['sac_calendar_events_tool'] = array('SAC Event-Tool');
$GLOBALS['TL_LANG']['MOD']['sac_member_database'] = array('SAC Mitgliederdatenbank');
$GLOBALS['TL_LANG']['MOD']['sac_calendar_events_stories_tool'] = array('Touren-/Kursberichte Tool');
$GLOBALS['TL_LANG']['MOD']['sac_tour_difficulty_tool'] = array('Schwierigkeitsgrade für Touren');
$GLOBALS['TL_LANG']['MOD']['sac_tour_type_tool'] = array('Tourentypen');
$GLOBALS['TL_LANG']['MOD']['sac_event_release_tool'] = array('Event-Freigabestufen-Tool');
$GLOBALS['TL_LANG']['MOD']['sac_event_organizer_tool'] = array('Organisierende Gruppen');
$GLOBALS['TL_LANG']['MOD']['sac_event_journey_tool'] = array('Event Anreise-Art-Tool');
$GLOBALS['TL_LANG']['MOD']['sac_cabanne_tool'] = array('Hütten-Tool');
$GLOBALS['TL_LANG']['MOD']['sac_user_role_tool'] = array('Vereinsfunktionen-Tool');
$GLOBALS['TL_LANG']['MOD']['sac_event_type_tool'] = array('SAC Event-Art-Tool');

// Frontend modules
$GLOBALS['TL_LANG']['FMD']['sac_event_tool_frontend_modules'] = array('SAC Frontend Module');
$GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventPreviewReader'] = array('Event Reader Vorschau');

// Contao 5 ready frontend modules
$GLOBALS['TL_LANG']['FMD']['tour_difficulty_list'] = array('Schwierigkeitsgrade Tabelle mit Erklärungen als Modalfenster');
$GLOBALS['TL_LANG']['FMD']['csv_event_member_export'] = array('SAC-Event-Teilnehmer Export');
$GLOBALS['TL_LANG']['FMD'][EventRegistrationController::TYPE] = array('SAC Event Registrierungsformular');
$GLOBALS['TL_LANG']['FMD'][EventRegistrationCheckoutLinkController::TYPE] = array('Link zur Checkout-Seite für Event-Anmeldung');
$GLOBALS['TL_LANG']['FMD']['member_dashboard_upcoming_events'] = array('SAC Mitgliederkonto Dashboard - Meine nächsten Events');
$GLOBALS['TL_LANG']['FMD']['member_dashboard_past_events'] = array('SAC Mitgliederkonto Dashboard - Meine absolvierten Events');
$GLOBALS['TL_LANG']['FMD']['member_dashboard_event_report_list'] = array('SAC Mitgliederkonto Dashboard - Meine Tourenberichte');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardWriteEventArticleController::TYPE] = array('SAC Mitgliederkonto Dashboard - Tourenbericht schreiben');
$GLOBALS['TL_LANG']['FMD']['member_dashboard_edit_profile'] = array('SAC Mitgliederkonto Dashboard - Profil bearbeiten');
$GLOBALS['TL_LANG']['FMD']['member_dashboard_delete_profile'] = array('SAC Mitgliederkonto Dashboard - Profil löschen');
$GLOBALS['TL_LANG']['FMD']['member_dashboard_avatar_upload'] = array('SAC Mitgliederkonto Dashboard - Avatar-Upload-Formular');
$GLOBALS['TL_LANG']['FMD']['member_dashboard_avatar'] = array('SAC Mitgliederkonto Dashboard - Mitglieder Avatar');
$GLOBALS['TL_LANG']['FMD']['csv_export'] = array('SAC-Member- und Backend-User CSV-Export-Funktion');
$GLOBALS['TL_LANG']['FMD']['event_filter_form'] = array('SAC-Event-Liste-Filter');
$GLOBALS['TL_LANG']['FMD']['pilatus_export'] = array('SAC Event-Export für Monatszeitschrift');
$GLOBALS['TL_LANG']['FMD']['pilatus_export_2021'] = array('SAC Event-Export 2021 für Monatszeitschrift');
$GLOBALS['TL_LANG']['FMD']['jahresprogramm_export'] = array('SAC Event-Export für Jahresprogramm');
$GLOBALS['TL_LANG']['FMD']['event_story_list'] = array('SAC Tourenberichte Listen Modul');
$GLOBALS['TL_LANG']['FMD']['event_story_reader'] = array('SAC Tourenberichte Reader Modul');
$GLOBALS['TL_LANG']['FMD']['event_list'] = array('SAC Event Auflistungs Modul');
