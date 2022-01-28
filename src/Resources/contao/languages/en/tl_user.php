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

// Legends
$GLOBALS['TL_LANG']['tl_user']['frontend_legend'] = 'Frontend Einstellungen';
$GLOBALS['TL_LANG']['tl_user']['emergency_phone_legend'] = 'Notfall Einstellungen';
$GLOBALS['TL_LANG']['tl_user']['bank_account_legend'] = 'Kontoangaben';
$GLOBALS['TL_LANG']['tl_user']['role_legend'] = 'Aufgaben/Rollen im Verein';
$GLOBALS['TL_LANG']['tl_user']['event_tool_legend'] = 'Event-Tool Einstellungen';
$GLOBALS['TL_LANG']['tl_user']['recission_legend'] = 'Benutzer-Deaktivierungs-Einstellungen';

// Fields
$GLOBALS['TL_LANG']['tl_user']['uuid'] = ['UUID (Zentralkommitee Bern)', ''];
$GLOBALS['TL_LANG']['tl_user']['userRole'] = ['Rolle/Funktion im Verein', 'Geben Sie hier bitte eine oder mehrere Rollen/Funktionen an.'];
$GLOBALS['TL_LANG']['tl_user']['sacMemberId'] = ['SAC-Mitgliedernummer', 'Schreibe eine "0", falls der User kein Sektionsmitglied ist.'];
$GLOBALS['TL_LANG']['tl_user']['firstname'] = ['Vorname', ''];
$GLOBALS['TL_LANG']['tl_user']['lastname'] = ['Nachname', ''];
$GLOBALS['TL_LANG']['tl_user']['dateOfBirth'] = ['Geburtsdatum', ''];
$GLOBALS['TL_LANG']['tl_user']['gender'] = ['Geschlecht', ''];
$GLOBALS['TL_LANG']['tl_user']['street'] = ['Strasse', ''];
$GLOBALS['TL_LANG']['tl_user']['postal'] = ['Postleitzahl', ''];
$GLOBALS['TL_LANG']['tl_user']['city'] = ['Ort', ''];
$GLOBALS['TL_LANG']['tl_user']['state'] = ['Kanton', ''];
$GLOBALS['TL_LANG']['tl_user']['country'] = ['Land', ''];
$GLOBALS['TL_LANG']['tl_user']['phone'] = ['Telefon (Festnetz)', ''];
$GLOBALS['TL_LANG']['tl_user']['mobile'] = ['Telefon (Mobile)', ''];
$GLOBALS['TL_LANG']['tl_user']['website'] = ['Webseite', ''];
$GLOBALS['TL_LANG']['tl_user']['instructor_legend'] = 'Leiter-Einstellungen';
$GLOBALS['TL_LANG']['tl_user']['leiterQualifikation'] = ['Leiter Qualifikation', ''];
$GLOBALS['TL_LANG']['tl_user']['addAvatar'] = ['Portrait-Bild hinzufügen', ''];
$GLOBALS['TL_LANG']['tl_user']['avatarSRC'] = ['Portrait-Bild', ''];
$GLOBALS['TL_LANG']['tl_user']['iban'] = ['IBAN Nr.', 'Geben Sie Ihre IBAN-Nummer ein. Diese wird für die Vergütung benötigt.'];
$GLOBALS['TL_LANG']['tl_user']['emergencyPhone'] = ['Notfall-Benachrichtigungs-Telefonnummer', 'Geben Sie die Telefonnummer einer Ihnen vertrauten Person an, welche in einem Notfall kontaktiert werden kann.'];
$GLOBALS['TL_LANG']['tl_user']['emergencyPhoneName'] = ['Name und Bezug der Ihnen anvertrauten Kontaktperson für Notfälle', 'Geben Sie die den Namen und den Bezug einer Ihnen vertrauten Person an, welche in einem Notfall kontaktiert werden kann.'];
$GLOBALS['TL_LANG']['tl_user']['hobbies'] = ['Hobbies', 'Hier kannst du deine Hobbys auflisten.'];
$GLOBALS['TL_LANG']['tl_user']['introducing'] = ['Steckbrief/Kurzpräsentation', 'Stelle dich in einigen Sätzen kurz vor. Motivation, Beweggründe, History, etc.'];
$GLOBALS['TL_LANG']['tl_user']['hideInFrontendListings'] = ['Benutzer in Listing Modulen Im Frontend nicht anzeigen', 'Geben Sie an, ob der Benutzer in Listing Modulen im Frontend nicht angezeigt werden soll.'];
$GLOBALS['TL_LANG']['tl_user']['calendar_containers'] = ['Erlaubte SAC-Event-Jahrescontainer', ''];
$GLOBALS['TL_LANG']['tl_user']['calendar_containerp'] = ['SAC-Event-Jahrescontainer Rechte', ''];
$GLOBALS['TL_LANG']['tl_user']['sectionId'] = ['Sektions-Mitgliedschaft', 'Geben Sie die Sektionmitgliedschaft an.'];
$GLOBALS['TL_LANG']['tl_user']['disableOnlineRegistration'] = ['Online Anmeldungen standardmässig ausschalten', 'Geben Sie an, ob bei diesem User die Online Anmeldung bei Events, bei denen dieser Autor ist, standarmässig deaktiviert sein soll.'];
$GLOBALS['TL_LANG']['tl_user']['generateMainInstructorContactDataFromDb'] = ['Kontaktdaten standardmässig aus der Datenbank generieren', 'Die Kontaktdaten werden im Frontend im Feld "Anmeldungen" (auch für nicht eingeloggte Mitglieder) ausgegeben.'];
$GLOBALS['TL_LANG']['tl_user']['rescissionCause'] = ['Deaktivierungs-Grund', 'Geben Sie einen Grund an, weshalb der Benutzer deaktiviert wurde.'];

// References
$GLOBALS['TL_LANG']['tl_user']['section']['4250'] = 'SAC PILATUS';
$GLOBALS['TL_LANG']['tl_user']['section']['4251'] = 'SAC PILATUS SURENTAL';
$GLOBALS['TL_LANG']['tl_user']['section']['4252'] = 'SAC PILATUS NAPF';
$GLOBALS['TL_LANG']['tl_user']['section']['4253'] = 'SAC PILATUS HOCHDORF';
$GLOBALS['TL_LANG']['tl_user']['section']['4254'] = 'SAC PILATUS RIGI';

$GLOBALS['TL_LANG']['tl_user']['rescissionCauseOptions']['deceased'] = 'Verstorben';
$GLOBALS['TL_LANG']['tl_user']['rescissionCauseOptions']['recission'] = 'Rücktritt';
$GLOBALS['TL_LANG']['tl_user']['rescissionCauseOptions']['leaving'] = 'Vereins-Austritt';
