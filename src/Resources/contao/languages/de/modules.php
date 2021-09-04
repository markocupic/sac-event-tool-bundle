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
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\TourDifficultyListController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\CsvEventMemberExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardUpcomingEventsController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardPastEventsController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardEventReportListController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardEditProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardDeleteProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardAvatarController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardAvatarUploadController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\CsvExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventFilterFormController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\JahresprogrammExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventStoryListController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventStoryReaderController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventListController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\PilatusExport2021Controller;

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
$GLOBALS['TL_LANG']['FMD'][CsvEventMemberExportController::TYPE] = array('SAC-Event-Teilnehmer Export');
$GLOBALS['TL_LANG']['FMD'][CsvExportController::TYPE] = array('SAC-Member- und Backend-User CSV-Export-Funktion');
$GLOBALS['TL_LANG']['FMD'][EventFilterFormController::TYPE] = array('SAC-Event-Liste-Filter');
$GLOBALS['TL_LANG']['FMD'][EventListController::TYPE] = array('SAC Event Auflistungs Modul');
$GLOBALS['TL_LANG']['FMD'][EventRegistrationCheckoutLinkController::TYPE] = array('Link zur Checkout-Seite für Event-Anmeldung');
$GLOBALS['TL_LANG']['FMD'][EventRegistrationController::TYPE] = array('SAC Event Registrierungsformular');
$GLOBALS['TL_LANG']['FMD'][EventStoryListController::TYPE] = array('SAC Tourenberichte Listen Modul');
$GLOBALS['TL_LANG']['FMD'][EventStoryReaderController::TYPE] = array('SAC Tourenberichte Reader Modul');
$GLOBALS['TL_LANG']['FMD'][JahresprogrammExportController::TYPE] = array('SAC Event-Export für Jahresprogramm');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardAvatarController::TYPE] = array('SAC Mitgliederkonto Dashboard - Mitglieder Avatar');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardAvatarUploadController::TYPE] = array('SAC Mitgliederkonto Dashboard - Avatar-Upload-Formular');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardDeleteProfileController::TYPE] = array('SAC Mitgliederkonto Dashboard - Profil löschen');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardEditProfileController::TYPE] = array('SAC Mitgliederkonto Dashboard - Profil bearbeiten');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardEventReportListController::TYPE] = array('SAC Mitgliederkonto Dashboard - Meine Tourenberichte');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardPastEventsController::TYPE] = array('SAC Mitgliederkonto Dashboard - Meine absolvierten Events');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardProfileController::TYPE] = array('SAC Mitgliederkonto Dashboard - Profil');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardUpcomingEventsController::TYPE] = array('SAC Mitgliederkonto Dashboard - Meine nächsten Events');
$GLOBALS['TL_LANG']['FMD'][MemberDashboardWriteEventArticleController::TYPE] = array('SAC Mitgliederkonto Dashboard - Tourenbericht schreiben');
$GLOBALS['TL_LANG']['FMD'][PilatusExport2021Controller::TYPE] = array('SAC Event-Export 2021 für Monatszeitschrift');
$GLOBALS['TL_LANG']['FMD'][TourDifficultyListController::TYPE] = array('Schwierigkeitsgrade Tabelle mit Erklärungen als Modalfenster');
