<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

// Global operations
$GLOBALS['TL_LANG']['tl_calendar_events_member']['onloadCallbackExportMemberlist'] = 'Teilnehmerliste Excel';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['downloadEventMemberList'] = 'Teilnehmerliste Word';

// Operations
$GLOBALS['TL_LANG']['tl_calendar_events_member']['toggleStateOfParticipation'] = ["Status der Teilnahme aendern", "Ändern Sie hier den Teilnahmestatus des Teilnehmers mit ID %s."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['new'] = ["Neuen Teilnehmer hinzufügen", "Fügen Sie hier manuell einen neuen Teilnehmer hinzu."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['edit'] = ["Bearbeiten", "Bearbeiten Sie die Teilnahme-Optionen des Teilnehmers mit ID %s."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['delete'] = ["Löschen", "Löschen Sie die Anmeldung des Teilnehmers mit ID %s."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['show'] = ["Details", "Details des Teilnehmers mit ID %s anzeigen."];


// Legends
$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfSubscription_legend'] = "Anmeldestatus-Einstellungen";
$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfParticipation_legend'] = "Abschliessende Einstellungen zum Teilnahmestatus";
$GLOBALS['TL_LANG']['tl_calendar_events_member']['notes_legend'] = "Anmerkungen";
$GLOBALS['TL_LANG']['tl_calendar_events_member']['personal_legend'] = "Personalien";
$GLOBALS['TL_LANG']['tl_calendar_events_member']['address_legend'] = "Adresse";
$GLOBALS['TL_LANG']['tl_calendar_events_member']['contact_legend'] = "Kontaktangaben";
$GLOBALS['TL_LANG']['tl_calendar_events_member']['sendEmail_legend'] = "E-Mail an Teilnehmer versenden";
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergency_phone_legend'] = "Notfall Kontaktangaben";
$GLOBALS['TL_LANG']['tl_calendar_events_member']['sac_member_id_legend'] = "SAC-Mitgliedernummer";


// Fields
$GLOBALS['TL_LANG']['tl_calendar_events_member']['hasParticipated'] = ["Hat am Event teilgenommen", "Geben Sie hier abschliessend an, ob der Teilnehmer auch tatsächlich am Event teilgenommen hat. (Erst nach Eventende einstellen!)"];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfSubscription'] = ["Anmeldestatus", "Geben Sie hier den Anmeldestatus ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['addedOn'] = ["Anmeldedatum", "Geben Sie hier das Anmeldedatum ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['firstname'] = ["Vorname", "Geben Sie hier den Vornamen ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['lastname'] = ["Nachname", "Geben Sie hier den Nachnamen ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['sacMemberId'] = ["SAC Mitgliedernummer", '<span style="font-weight:bold;color:red">Lasse das Feld leer</span>, falls die Person kein Sektionsmitglied ist.'];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['contaoMemberId'] = ["Contao Mitglieder Id (tl_member)", "Geben Sie hier die Contao Mitglieder-ID ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['street'] = ["Strasse", "Geben Sie hier die Strasse ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['postal'] = ["Postleitzahl", "Geben Sie hier die Postleitzahl ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['city'] = ["Ort", "Geben Sie hier den Ort ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['mobile'] = ["Mobilnummer", "Geben Sie die Mobilnummer ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhoneName'] = ["Namen einer vertrauten Person für Notfälle", "Geben Sie den Namen einer vertrauten Person an, welche in Notfällen kontaktiert wird."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhone'] = ["Notfall Telefonnummer", "Geben Sie eine Notfallnummer ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['foodHabits'] = ['Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)', ''];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['carInfo'] = ["Der Teilnehmer stellt ein Auto mit ... Anzahl Plätzen (inkl. Fahrer)", "Geben Sie bitte eine PW-Option ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['ticketInfo'] = ["Angaben zum Billet (Der Teilnehmer besitzt ein/eine)", "Geben Sie bitte eine Billet-Option ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['email'] = ["E-Mail", "Geben Sie bitte die E-Mail-Adresse ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['notes'] = ["Anmerkungen", "Geben Sie hier die Anmerkungen ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['instructorNotes'] = ["Anmerkungen des Event-Leiters", "Geben Sie hier allfällige Anmerkungen ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['gender'] = ["Geschlecht", "Geben Sie hier das Geschlecht ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['dateOfBirth'] = ["Geburtsdatum", "Geben Sie bitte das Geburtsdatum ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailRecipients'] = ["E-Mail Empfänger", "Wählen Sie bitte die E-Mail Empfaänger aus."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSubject'] = ["E-Mail Betreff", "Schreiben Sie hier bitte den E-Mail Betreff."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailText'] = ["E-Mail Text", "Schreiben Sie hier bitte den E-Mail Text."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSendCopy'] = ["Kopie der Nachricht an mich senden", "Wählen Sie diese Option aus, wenn Sie eine Kopie der Nachricht erhalten möchten."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['addEmailAttachment'] = ["Dateianlage hinzufügen", ""];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailAttachment'] = ["Dateien auswählen", "Wählen Sie die Dateien aus dem Dateibaum aus."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['eventName'] = ["Eventname", "Geben Sie bitte den Eventnamen ein."];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['anonymized'] = ["Eingaben anonymisiert", ""];
$GLOBALS['TL_LANG']['tl_calendar_events_member']['bookingType'] = ["Buchungsart", "Geben Sie an über welchen Zugang der Teilnehmer gebucht hat."];

// References
$GLOBALS['TL_LANG']['tl_calendar_events_member']['subscription-not-confirmed'] = 'Anmeldung nicht bestätigt';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['subscription-accepted'] = 'Anmeldung bestätigt';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['subscription-refused'] = 'Anmeldung abgelehnt';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['subscription-waitlisted'] = 'Auf Warteliste';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['user-has-unsubscribed'] = 'Vom Event abgemeldet';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['manually'] = 'Manuelle Erfassung der Personalien des Event-Teilnehmers';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['onlineForm'] = 'Buchung über Online-Buchungsformular';






