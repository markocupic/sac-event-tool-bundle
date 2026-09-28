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

use Markocupic\SacEventToolBundle\NotificationType\EventReminderNotificationType;

$type = EventReminderNotificationType::NAME;

// Event
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_id'] = 'ID des Events.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_title'] = 'Titel des Events.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_event_type'] = 'Event-Typ (Schlüssel, z.B. "tour" oder "course").';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_event_type_translated'] = 'Event-Typ (übersetzt, z.B. "Tour" oder "Kurs").';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_course_id'] = 'Kursnummer (nur bei Kursen).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_start_date'] = 'Startdatum des Events (TT.MM.JJJJ).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_end_date'] = 'Enddatum des Events (TT.MM.JJJJ).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_period'] = 'Event-Zeitraum als formatierter Text, inkl. Dauer (z.B. "05.12.2026 - 06.12.2026 (2 Tage)").';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_duration'] = 'Event-Dauer (z.B. "2 Tage" oder der im Event hinterlegte Dauer-Text).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_meeting_point'] = 'Zeit und Treffpunkt (Feld "Zeit und Treffpunkt" im Event).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_leistungen'] = 'Preis und Leistungen (Feld "Preis und Leistungen" im Event).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_deregistration_limit'] = 'Abmeldefrist in Tagen vor Event-Start. Leer, wenn die Online-Abmeldung für den Event nicht erlaubt ist.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_link_detail'] = 'Absoluter Link zur Event-Detailseite.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_*'] = 'Weitere Event-Felder.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_raw_*'] = 'Weitere Event-Felder (Rohdaten).';

// Main instructor
$GLOBALS['TL_LANG']['nc_tokens'][$type]['main_instructor_name'] = 'Name des Hauptleiters.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['main_instructor_email'] = 'E-Mail-Adresse des Hauptleiters (für die Empfängerfelder ##recipient_to## verwenden).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['main_instructor_phone'] = 'Telefonnummer des Hauptleiters.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['main_instructor_mobile'] = 'Mobilnummer des Hauptleiters.';

// Instructors
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructors_names'] = 'Namen aller Leiter (inkl. Hauptleiter), kommasepariert.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructors_email'] = 'E-Mail-Adressen aller Leiter (inkl. Hauptleiter), kommasepariert.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['co_instructors_names'] = 'Namen der weiteren Leiter (ohne Hauptleiter), kommasepariert.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['co_instructors_email'] = 'E-Mail-Adressen der weiteren Leiter (ohne Hauptleiter), kommasepariert (für das CC-Feld ##recipient_cc## verwenden).';

// Participants
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participants_names'] = 'Namen aller bestätigten Teilnehmer, kommasepariert.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participants_email'] = 'E-Mail-Adressen aller bestätigten Teilnehmer, kommasepariert (für das BCC-Feld ##recipient_bcc## verwenden).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participants_count'] = 'Anzahl bestätigter Teilnehmer.';

// Registration coordinator (tl_calendar_events.registrationGoesTo)
$GLOBALS['TL_LANG']['nc_tokens'][$type]['registration_coordinator_name'] = 'Name des Anmeldekoordinators ("Anmeldungen gehen an"), leer wenn keiner hinterlegt ist.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['registration_coordinator_email'] = 'E-Mail-Adresse des Anmeldekoordinators, leer wenn keiner hinterlegt ist.';

// Recipients
$GLOBALS['TL_LANG']['nc_tokens'][$type]['recipient_to'] = 'Empfänger für die Felder "Empfänger" und "Antwort an": Anmeldekoordinator, falls hinterlegt, sonst Hauptleiter.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['recipient_cc'] = 'Empfänger für das Feld "CC": alle aktiven Leiter, ohne die Adresse aus ##recipient_to##, kommasepariert.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['recipient_bcc'] = 'Empfänger für das Feld "BCC": alle bestätigten Teilnehmer, kommasepariert.';

// Reminder
$GLOBALS['TL_LANG']['nc_tokens'][$type]['reminder_offset_days'] = 'Anzahl Tage vor Event-Start, an denen diese Erinnerung versendet wird (Einstellung im Kalender).';
