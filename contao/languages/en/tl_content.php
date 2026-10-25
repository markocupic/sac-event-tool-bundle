<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

// Legends
$GLOBALS['TL_LANG']['tl_content']['jumpTo_legend'] = 'Weiterleitungs-Einstellungen';
$GLOBALS['TL_LANG']['tl_content']['config_legend'] = 'Element-Einstellungen';
$GLOBALS['TL_LANG']['tl_content']['swisstopo_map_legend'] = 'Swisstopo-Karte';
$GLOBALS['TL_LANG']['tl_content']['member_dashboard_my_events_legend'] = 'Member Dashboard: Meine Events';

// Fields
$GLOBALS['TL_LANG']['tl_content']['userList_selectMode'] = ['Auswahltyp', 'Wählen Sie einen Auswahltyp aus.'];
$GLOBALS['TL_LANG']['tl_content']['userList_users'] = ['Anzuzeigende Benutzer', 'Wählen Sie einen oder mehrere Benutzer aus, die angezeigt werden, aus.'];
$GLOBALS['TL_LANG']['tl_content']['userList_userRoles'] = ['Benutzer abhängig nach ihrer Rolle auswählen', 'Wählen Sie eine oder mehrere Rollen aus.'];
$GLOBALS['TL_LANG']['tl_content']['userList_replacePrivateAdressWithRoleAdress'] = ['Private Adressen-Angaben mit Rollen-Adressen-Angaben überschreiben', 'Geben Sie an, welche private Adressen-Angaben durch Rollen-Adressen-Angaben überschrieben werden sollen.'];
$GLOBALS['TL_LANG']['tl_content']['userList_queryType'] = ['Abfragetyp (UND/ODER)', 'Wählen Sie einen Abfragetyp aus.'];
$GLOBALS['TL_LANG']['tl_content']['userList_showFieldsToGuests'] = ['Zeige Felder unangemeldeten Gästen', 'Zeige diese Felder unangemeldeten Gästen'];
$GLOBALS['TL_LANG']['tl_content']['imgSize'] = ['Bildgrösse', 'Wählen Sie eine Bildgrösse aus.'];
$GLOBALS['TL_LANG']['tl_content']['jumpTo'] = ['Weiterleitungsseite', 'Wählen Sie eine Weiterleitungsseite aus.'];
$GLOBALS['TL_LANG']['tl_content']['swisstopoCenter'] = ['Karten-Mittelpunkt', 'Wählen Sie den Karten-Mittelpunkt aus bei Domready. z.B: 2600000,1200000.'];
$GLOBALS['TL_LANG']['tl_content']['swisstopoZoom'] = ['Karten-Zoom', 'Wählen Sie das Karten-Zoom aus bei Domready. z.B: 250'];
$GLOBALS['TL_LANG']['tl_content']['eventUnsubscribePage'] = ['Seite mit dem Event Stornierungsmodul', 'Wählen Sie die Seite aus, auf der das Event Stornierungsmodul angezeigt wird.'];

// References
$GLOBALS['TL_LANG']['tl_content']['selectUserRoles'] = 'Anhand Benutzer-Rollen';
$GLOBALS['TL_LANG']['tl_content']['selectUsers'] = 'Anhand Benutzer-Auswahl';

// Abo survey
$GLOBALS['TL_LANG']['tl_content']['abo_survey_legend'] = 'Abo-Umfrage';
$GLOBALS['TL_LANG']['tl_content']['aboSurveyYear'] = ['Jahr', 'Für welches Jahr gelten die Eingaben? z.B. 2027'];
$GLOBALS['TL_LANG']['tl_content']['aboSurveyStartDate'] = ['Formular aktiv ab', 'Ab diesem Datum (00:00 Uhr) ist das Formular aktiv.'];
$GLOBALS['TL_LANG']['tl_content']['aboSurveyEndDate'] = ['Formular aktiv bis', 'Bis zu diesem Datum (23:59 Uhr) ist das Formular aktiv.'];
$GLOBALS['TL_LANG']['tl_content']['aboSurveyEventPeriodStart'] = ['Zeitraum Start', 'Berücksichtigt werden Touren und Kurse, die ab diesem Datum (00:00 Uhr) beginnen, z.B. 01.12.2026, damit auch Touren des Jahresprogramms 2027 im Dezember 2026 zählen.'];
$GLOBALS['TL_LANG']['tl_content']['aboSurveyEventPeriodEnd'] = ['Zeitraum Ende', 'Berücksichtigt werden Touren und Kurse, die bis zu diesem Datum (23:59 Uhr) beginnen, z.B. 31.12.2027.'];
$GLOBALS['TL_LANG']['tl_content']['aboSurveyExplanation'] = '<strong>Abo-Umfrage (White Risk / SchweizMobil)</strong><br>'
	. 'Eingeloggte Mitglieder wählen ein Abo für das eingestellte Jahr. Das Formular ist vom Startdatum (00:00 Uhr) bis zum Enddatum (23:59 Uhr) aktiv.<br><br>'
	. '<strong>Wer darf das Formular absenden?</strong><br>'
	. 'Mitglieder mit SAC-Mitgliedernummer, zu der ein Backend-Benutzer existiert, und bei denen mindestens eines zutrifft:'
	. '<ul style="list-style:disc;margin:4px 0 4px 20px">'
	. '<li>Leiterin oder Leiter (Haupt- oder Co-Leitung) bei mindestens einer Tour oder einem Kurs, der im eingestellten Zeitraum (Zeitraum Start bis Zeitraum Ende) beginnt und Freigabestufe 3 oder höher hat</li>'
	. '<li>Rolle mit «gehört zum Vorstand»</li>'
	. '<li>Rolle unter «Zusätzlich berechtigte Rollen» ausgewählt</li>'
	. '<li>Benutzer unter «Zusätzlich berechtigte Benutzer» ausgewählt</li>'
	. '</ul>'
	. '<p><strong>Bergführerinnen und Bergführer</strong> (Leiterqualifikation «Bergführer») sind immer ausgeschlossen, auch wenn sie eine der obigen Bedingungen erfüllen.</p>';
$GLOBALS['TL_LANG']['tl_content']['abo_survey_access_legend'] = 'Abo-Umfrage: Zusätzliche Berechtigungen';
$GLOBALS['TL_LANG']['tl_content']['aboSurveyAllowedUserRoles'] = ['Zusätzlich berechtigte Rollen', 'Backend-Benutzer mit einer dieser Rollen dürfen das Formular ebenfalls absenden. Rollen mit «gehört zum Vorstand» sind immer berechtigt.'];
$GLOBALS['TL_LANG']['tl_content']['aboSurveyAllowedUsers'] = ['Zusätzlich berechtigte Benutzer', 'Diese Backend-Benutzer dürfen das Formular ebenfalls absenden.'];
