<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Markocupic\SacEventToolBundle\Controller\FrontendModule\CsvEventMemberExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\CsvUserExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventFilterFormController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventListController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationCheckoutLinkController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\JahresprogrammExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardAvatarController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardAvatarUploadController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardDeleteProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardEditProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardPastEventsController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardUpcomingEventsController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\PilatusExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\TourDifficultyListController;

// Backend Modules
$GLOBALS['TL_LANG']['MOD']['sac_be_modules'] = ['SAC Module'];
$GLOBALS['TL_LANG']['MOD']['calendar'] = ['SAC Event-Tool'];
$GLOBALS['TL_LANG']['MOD']['sac_course_main_types_tool'] = ['Kurs-Hauptkategorien'];
$GLOBALS['TL_LANG']['MOD']['sac_course_sub_types_tool'] = ['Kurs-Unterkategorien'];
$GLOBALS['TL_LANG']['MOD']['sac_event_journey_tool'] = ['Event Anreise-Art-Tool'];
$GLOBALS['TL_LANG']['MOD']['sac_event_organizer_tool'] = ['Organisierende Gruppen'];
$GLOBALS['TL_LANG']['MOD']['sac_event_release_tool'] = ['Event-Freigabestufen-Tool'];
$GLOBALS['TL_LANG']['MOD']['sac_event_type_tool'] = ['SAC Event-Art-Tool'];
$GLOBALS['TL_LANG']['MOD']['sac_member_database'] = ['SAC Mitgliederdatenbank'];
$GLOBALS['TL_LANG']['MOD']['sac_section_tool'] = ['SAC Sektionen und OG'];
$GLOBALS['TL_LANG']['MOD']['sac_tour_difficulty_tool'] = ['Schwierigkeitsgrade für Touren'];
$GLOBALS['TL_LANG']['MOD']['sac_tour_type_tool'] = ['Tourentypen'];
$GLOBALS['TL_LANG']['MOD']['sac_user_role_tool'] = ['Vereinsfunktionen-Tool'];

// Contao legacy frontend modules
$GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventPreviewReader'] = ['Event Reader Vorschau'];
$GLOBALS['TL_LANG']['FMD']['sac_event_tool_frontend_modules'] = ['SAC Frontend Module'];

// Contao 5 ready frontend modules
$GLOBALS['TL_LANG']['FMD'][CsvEventMemberExportController::TYPE] = ['SAC-Event-Teilnehmer Export'];
$GLOBALS['TL_LANG']['FMD'][CsvUserExportController::TYPE] = ['SAC-Member- und Backend-User CSV-Export-Funktion'];
$GLOBALS['TL_LANG']['FMD'][EventFilterFormController::TYPE] = ['SAC-Event-Liste-Filter'];
$GLOBALS['TL_LANG']['FMD'][EventListController::TYPE] = ['SAC Event Auflistungs Modul'];
$GLOBALS['TL_LANG']['FMD'][EventRegistrationCheckoutLinkController::TYPE] = ['Link zur Checkout-Seite für Event-Anmeldung'];
$GLOBALS['TL_LANG']['FMD'][EventRegistrationController::TYPE] = ['SAC Event Registrierungsformular'];
$GLOBALS['TL_LANG']['FMD'][JahresprogrammExportController::TYPE] = ['SAC Event-Export für Jahresprogramm'];
$GLOBALS['TL_LANG']['FMD'][MemberDashboardAvatarController::TYPE] = ['SAC Mitgliederkonto Dashboard - Mitglieder Avatar'];
$GLOBALS['TL_LANG']['FMD'][MemberDashboardAvatarUploadController::TYPE] = ['SAC Mitgliederkonto Dashboard - Avatar-Upload-Formular'];
$GLOBALS['TL_LANG']['FMD'][MemberDashboardDeleteProfileController::TYPE] = ['SAC Mitgliederkonto Dashboard - Profil löschen'];
$GLOBALS['TL_LANG']['FMD'][MemberDashboardEditProfileController::TYPE] = ['SAC Mitgliederkonto Dashboard - Profil bearbeiten'];
$GLOBALS['TL_LANG']['FMD'][MemberDashboardPastEventsController::TYPE] = ['SAC Mitgliederkonto Dashboard - Meine absolvierten Events'];
$GLOBALS['TL_LANG']['FMD'][MemberDashboardProfileController::TYPE] = ['SAC Mitgliederkonto Dashboard - Profil'];
$GLOBALS['TL_LANG']['FMD'][MemberDashboardUpcomingEventsController::TYPE] = ['SAC Mitgliederkonto Dashboard - Meine nächsten Events'];
$GLOBALS['TL_LANG']['FMD'][PilatusExportController::TYPE] = ['SAC Event-Export 2021 für Monatszeitschrift'];
$GLOBALS['TL_LANG']['FMD'][TourDifficultyListController::TYPE] = ['Schwierigkeitsgrade Tabelle mit Erklärungen als Modalfenster'];
