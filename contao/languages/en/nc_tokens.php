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

use Markocupic\SacEventToolBundle\Feature\EventReminder\NotificationType\EventReminderNotificationType;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\NotificationType\EventCompletionReminderNotificationType;
use Markocupic\SacEventToolBundle\NotificationType\EventDeregistrationNotificationType;
use Markocupic\SacEventToolBundle\NotificationType\EventRegistrationNotificationType;
use Markocupic\SacEventToolBundle\NotificationType\SubscriptionStateChangeNotificationType;

/*
 * Event registration
 */
$type = EventRegistrationNotificationType::NAME;

// Event
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_id'] = 'ID des Events.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_title'] = 'Titel des Events.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_type'] = 'Event-Typ (Schlüssel, z.B. "tour" oder "course").';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_type_translated'] = 'Event-Typ (übersetzt, z.B. "Tour" oder "Kurs").';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_course_id'] = 'Kursnummer (nur bei Kursen).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_leistungen'] = 'Preis und Leistungen (Feld "Preis und Leistungen" im Event).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_add_iban'] = 'Gibt an, ob beim Event eine IBAN für die Einzahlung hinterlegt ist ("1" = ja, leer = nein).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_iban'] = 'IBAN für die Einzahlung der Event-Kosten. Leer, wenn keine IBAN hinterlegt ist.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_ibanBeneficiary'] = 'Zahlungsempfänger (Kontoinhaber) der IBAN. Leer, wenn keine IBAN hinterlegt ist.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_link_detail'] = 'Absoluter Link zur Event-Detailseite.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_*'] = 'Weitere Event-Felder.';

// Instructor
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructor_name'] = 'Name des Leiters: Anmeldekoordinator ("Anmeldungen gehen an"), falls hinterlegt, sonst Hauptleiter.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructor_email'] = 'E-Mail-Adresse des Leiters: Anmeldekoordinator ("Anmeldungen gehen an"), falls hinterlegt, sonst Hauptleiter.';

// Participant
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_name'] = 'Vor- und Nachname des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_email'] = 'E-Mail-Adresse des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_uuid'] = 'UUID der Event-Anmeldung.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_contao_member_id'] = 'Contao-Mitglieder-ID des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_sac_member_id'] = 'SAC-Mitgliedernummer des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_section_membership'] = 'Sektionszugehörigkeit(en) des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_state_of_subscription'] = 'Anmeldestatus des Teilnehmers (übersetzt, z.B. "Anmeldung bestätigt").';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_street'] = 'Strasse des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_postal'] = 'Postleitzahl des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_city'] = 'Wohnort des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_date_of_birth'] = 'Geburtsdatum des Teilnehmers (TT.MM.JJJJ).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_ahv_number'] = 'AHV-Nummer des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_phone'] = 'Telefonnummer des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_mobile'] = 'Mobilnummer des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_emergency_phone'] = 'Notfall-Telefonnummer des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_emergency_phone_name'] = 'Name der Notfall-Kontaktperson des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_food_habits'] = 'Essgewohnheiten des Teilnehmers (z.B. vegetarisch, Laktoseintoleranz).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_has_lead_climbing_education'] = 'Gibt an, ob der Teilnehmer eine Vorstiegsausbildung besitzt ("1" = ja, leer = nein).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_notes'] = 'Anmerkungen des Teilnehmers zur Anmeldung.';

/*
 * Event deregistration
 */
$type = EventDeregistrationNotificationType::NAME;

// Event
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_title'] = 'Titel des Events.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_type'] = 'Event-Typ (Schlüssel, z.B. "tour" oder "course").';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_course_id'] = 'Kursnummer (nur bei Kursen).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_link_detail'] = 'Absoluter Link zur Event-Detailseite.';

// Instructor
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructor_name'] = 'Name des Leiters: Anmeldekoordinator ("Anmeldungen gehen an"), falls hinterlegt, sonst Hauptleiter.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructor_email'] = 'E-Mail-Adresse des Leiters: Anmeldekoordinator ("Anmeldungen gehen an"), falls hinterlegt, sonst Hauptleiter.';

// Participant
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_name'] = 'Vor- und Nachname des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_email'] = 'E-Mail-Adresse des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_uuid'] = 'UUID der Event-Anmeldung.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_sac_member_id'] = 'SAC-Mitgliedernummer des Teilnehmers ("keine", wenn nicht vorhanden).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['state_of_subscription'] = 'Anmeldestatus des Teilnehmers (übersetzt).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['deregistration_cause'] = 'Vom Teilnehmer angegebener Grund für die Abmeldung.';

/*
 * Subscription state change
 */
$type = SubscriptionStateChangeNotificationType::NAME;

$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_title'] = 'Titel des Events.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_link_detail'] = 'Absoluter Link zur Event-Detailseite.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_name'] = 'Vor- und Nachname des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_email'] = 'E-Mail-Adresse des Teilnehmers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_uuid'] = 'UUID der Event-Anmeldung.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['participant_state_of_subscription'] = 'Neuer Anmeldestatus des Teilnehmers (übersetzt, z.B. "Anmeldung bestätigt").';

/*
 * Event reminder
 */
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

/*
 * Event completion reminder
 */
$type = EventCompletionReminderNotificationType::NAME;

// Recipient (instructor or registration coordinator)
$GLOBALS['TL_LANG']['nc_tokens'][$type]['recipient_email'] = 'E-Mail-Adresse des Empfängers (Leiter oder Anmelde-Koordinator). Für das Feld "Empfänger" verwenden.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructor_email'] = 'E-Mail-Adresse des Empfängers (identisch mit ##recipient_email##).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructor_firstname'] = 'Vorname des Empfängers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructor_lastname'] = 'Nachname des Empfängers.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['instructor_name'] = 'Vor- und Nachname des Empfängers.';

// Calendar
$GLOBALS['TL_LANG']['nc_tokens'][$type]['calendar_title'] = 'Titel des Kalenders.';

// To-do list
$GLOBALS['TL_LANG']['nc_tokens'][$type]['task_list_html'] = 'Liste der offenen Aufgaben als HTML (für den HTML-Text der E-Mail).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['task_list_text'] = 'Liste der offenen Aufgaben als Text (für den Text der E-Mail).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['open_task_count'] = 'Anzahl offener Aufgaben.';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['event_count'] = 'Anzahl Events mit offenen Aufgaben.';

// Calendar settings
$GLOBALS['TL_LANG']['nc_tokens'][$type]['first_offset_days'] = 'Bearbeitungsfrist in Tagen nach Event-Ende (Einstellung im Kalender).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['interval_days'] = 'Intervall in Tagen zwischen zwei Erinnerungen (Einstellung im Kalender).';

// Notification
$GLOBALS['TL_LANG']['nc_tokens'][$type]['reminder_count'] = 'Die wievielte Erinnerung für diesen Empfänger und Kalender (1 = erste Erinnerung).';
$GLOBALS['TL_LANG']['nc_tokens'][$type]['link_my_events_dashboard'] = 'Absoluter Link ins Backend (Startseite mit dem Dashboard "Meine Events").';
