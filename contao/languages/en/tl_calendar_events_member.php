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

use Markocupic\SacEventToolBundle\Config\BookingType;

// Main headline label
$GLOBALS['TL_LANG']['tl_calendar_events_member']['notify_event_registration_state'] = ['Teilnehmer benachrichtigen', 'Teilnehmer benachrichtigen'];

// Global operations
$GLOBALS['TL_LANG']['tl_calendar_events_member']['writeTourReport'] = 'Tourrapport bearbeiten';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['printInstructorInvoice'] = 'Tourrapport und Vergütungsformular drucken und einreichen';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['downloadEventRegistrationListCsv'] = 'Teilnehmerliste Excel';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['downloadEventRegistrationListDocx'] = 'Teilnehmerliste Word';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['sendEmail'] = 'E-Mail';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['new'] = ['Teilnehmer hinzufügen', 'Fügen Sie hier manuell einen neuen Teilnehmer hinzu.'];

// Operations
$GLOBALS['TL_LANG']['tl_calendar_events_member']['toggleParticipationState'] = ['Status der Registrierung ändern', 'Ändern Sie hier den Teilnahmestatus des Teilnehmers mit ID %s.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['edit'] = ['Registrierung bearbeiten', 'Registrierung mit ID %s bearbeiten.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['delete'] = ['Registrierung löschen', 'Löschen der Registrierung mit ID %s.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['show'] = ['Details der Registrierung ansehen', 'Details der Registrierung mit ID %s anzeigen.'];

// Legends
$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfSubscription_legend'] = 'Anmeldestatus-Einstellungen';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfParticipation_legend'] = 'Abschliessende Einstellungen zum Teilnahmestatus';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['notes_legend'] = 'Anmerkungen';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['personal_legend'] = 'Personalien';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['address_legend'] = 'Adresse';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['contact_legend'] = 'Kontaktangaben';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergency_phone_legend'] = 'Notfall Kontaktangaben';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['sac_member_id_legend'] = 'SAC-Mitgliedernummer';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['education_legend'] = 'Ausbildung';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['agb_legend'] = 'AGB u.Ä.';

// Fields
$GLOBALS['TL_LANG']['tl_calendar_events_member']['uuid'] = ['Registrierungs-UUID', 'Geben Sie hier die Registrierungs-UUID ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['eventId'] = ['Event-ID', 'Geben Sie hier die Event-ID ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['hasParticipated'] = ['Hat am Event teilgenommen', 'Geben Sie hier abschliessend an, ob der Teilnehmer auch tatsächlich am Event teilgenommen hat. (Erst nach Eventende einstellen!)'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfSubscription'] = ['Anmeldestatus', 'Geben Sie hier den Anmeldestatus ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['dateAdded'] = ['Anmeldedatum', 'Geben Sie hier das Anmeldedatum ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['allowMultiSignUp'] = ['Mehrfachbuchung zulassen', 'Mehrfachbuchung zulassen, obwohl der Teilnehmer im selben Zeitraum bereits für einen Event angemeldet ist.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['firstname'] = ['Vorname', 'Geben Sie hier den Vornamen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['lastname'] = ['Nachname', 'Geben Sie hier den Nachnamen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['sacMemberId'] = ['SAC Mitgliedernummer', '<span class="notice">Lasse das Feld leer</span>, falls die Person kein Sektionsmitglied ist.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['ahvNumber'] = ['AHV-Nummer', 'Geben Sie hier die AHV-Nummer ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['contaoMemberId'] = ['Contao Mitglieder Id (tl_member)', 'Geben Sie hier die Contao Mitglieder-ID ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['street'] = ['Strasse', 'Geben Sie hier die Strasse ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['postal'] = ['Postleitzahl', 'Geben Sie hier die Postleitzahl ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['city'] = ['Ort', 'Geben Sie hier den Ort ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['mobile'] = ['Mobilnummer', 'Geben Sie die Mobilnummer ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['hasLeadClimbingEducation'] = ['Seilschaftsführer', 'Geben Sie an, ob das Mitglied die Seilschaftsführer-Ausbildung besitzt.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['dateOfLeadClimbingEducation'] = ['Datum der Seilschaftsführer-Ausbildung', 'Geben Sie an, wann das Mitglied die Seilschaftsführer-Ausbildung absolviert hat.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhoneName'] = ['Name und Bezug der Ihnen anvertrauten Kontaktperson für Notfälle', 'Geben Sie die den Namen und den Bezug einer Ihnen vertrauten Person an, welche in einem Notfall kontaktiert werden kann.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhone'] = ['Notfalltelefonnummer/In Notfällen zu kontaktieren', 'Geben Sie eine Notfallnummer ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['foodHabits'] = ['Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)', ''];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['carInfo'] = ['Der Teilnehmer stellt ein Auto mit ... Anzahl Plätzen (inkl. Fahrer)', 'Geben Sie bitte eine PW-Option ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['ticketInfo'] = ['Angaben zum Billet (Der Teilnehmer besitzt ein/eine)', 'Geben Sie bitte eine Billet-Option ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['email'] = ['E-Mail', 'Geben Sie bitte die E-Mail-Adresse ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['notes'] = ['Anmerkungen', 'Geben Sie hier die Anmerkungen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['instructorNotes'] = ['Anmerkungen des Event-Leiters', 'Geben Sie hier allfällige Anmerkungen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['gender'] = ['Geschlecht', 'Geben Sie hier das Geschlecht ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['dateOfBirth'] = ['Geburtsdatum', 'Geben Sie bitte das Geburtsdatum ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['eventName'] = ['Eventname', 'Geben Sie bitte den Eventnamen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['anonymized'] = ['Eingaben anonymisiert', ''];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['bookingType'] = ['Buchungsart', 'Geben Sie an über welchen Zugang der Teilnehmer gebucht hat.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['sectionId'] = ['Sektionszugehörigkeit', 'Geben Sie hier die SAC Sektionszugehörigkeit an (readonly-Feld).'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['hasPaid'] = ['Teilnahmekosten beglichen', 'Geben Sie an, ob dieser Teilnehmer die Teilnahmekosten beglichen hat.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['paymentMethod'] = ['Zahlungsart', 'Geben Sie die Zahlungsart an.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['agb'] = ['AGB akzeptiert', 'Der Teilnehmer hat die AGB bei Event Anmeldung akzeptiert.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['hasAcceptedPrivacyRules'] = ['Datenschutzrichtline zum Umgang mit Foto und Videomaterial akzeptiert', 'Der Teilnehmer hat die Datenschutzrichtline zu Bildmedien bei der Event Anmeldung akzeptiert.'];

// References
$GLOBALS['TL_LANG']['tl_calendar_events_member'][BookingType::MANUALLY] = 'Manuelle Erfassung der Personalien des Event-Teilnehmers';
$GLOBALS['TL_LANG']['tl_calendar_events_member'][BookingType::ONLINE_FORM] = 'Buchung über Online-Buchungsformular';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['cashPayment'] = 'Barzahlung';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['bankTransfer'] = 'Bank-/Postüberweisung';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['twint'] = 'TWINT';

// Messages
$GLOBALS['TL_LANG']['tl_calendar_events_member']['notificationDueToMissingEmailDisabled'] = 'Benachrichtigungen wegen fehlender E-Mail-Adresse nicht möglich.';
