<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */


// Operations

$GLOBALS['TL_LANG']['tl_calendar_events_member']['toggleStateOfParticipation'] = array("Status der Teilnahme aendern", "Ändern Sie hier den Teilnahmestatus des Teilnehmers mit ID %s.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['new'] = array("Neuen Teilnehmer hinzuf&uuml;gen", "F&uuml;gen Sie hier manuell einen neuen Teilnehmer hinzu.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['edit'] = array("Bearbeiten", "Bearbeiten Sie die Teilnahme-Optionen des Teilnehmers mit ID %s.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['delete'] = array("L&ouml;schen", "L&ouml;schen Sie die Anmeldung des Teilnehmers mit ID %s.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['show'] = array("Details", "Details des Teilnehmers mit ID %s anzeigen.");


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
$GLOBALS['TL_LANG']['tl_calendar_events_member']['hasParticipated'] = array("Hat am Event teilgenommen", "Geben Sie hier abschliessend an, ob der Teilnehmer auch tats&auml;chlich am Event teilgenommen hat. (Erst nach Eventende einstellen!)");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfSubscription'] = array("Anmeldestatus", "Geben Sie hier den Anmeldestatus ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['addedOn'] = array("Anmeldedatum", "Geben Sie hier das Anmeldedatum ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['firstname'] = array("Vorname", "Geben Sie hier den Vornamen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['lastname'] = array("Nachname", "Geben Sie hier den Nachnamen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['sacMemberId'] = array("SAC Mitgliedernummer", "Geben Sie hier die Mitgliedernummer ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['contaoMemberId'] = array("Contao Mitglieder Id (tl_member)", "Geben Sie hier die Contao Mitglieder-ID ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['street'] = array("Strasse", "Geben Sie hier die Strasse ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['postal'] = array("Postleitzahl", "Geben Sie hier die Postleitzahl ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['city'] = array("Ort", "Geben Sie hier den Ort ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['phone'] = array("Telefonnummer", "Geben Sie die Telefonnummer ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhoneName'] = array("Namen einer vertrauten Person f&uuml;r Notf&auml;lle", "Geben Sie den Namen einer vertrauten Person an, welche in Notf&auml;llen kontaktiert wird.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhone'] = array("Notfall Telefonnummer", "Geben Sie eine Notfallnummer ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['vegetarian'] = array("Vegetarier", "Geben Sie an, ob der Teilnehmer Vegetarier ist.");

$GLOBALS['TL_LANG']['tl_calendar_events_member']['email'] = array("E-Mail", "Geben Sie bitte die E-Mail-Adresse ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['notes'] = array("Anmerkungen", "Geben Sie hier die Anmerkungen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['gender'] = array("Geschlecht", "Geben Sie hier das Geschlecht ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['dateOfBirth'] = array("Geburtsdatum", "Geben Sie bitte das Geburtsdatum ein.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailRecipients'] = array("E-Mail Empf&auml;nger", "W&auml;hlen Sie bitte die E-Mail Empfa&auml;nger aus.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSubject'] = array("E-Mail Betreff", "Schreiben Sie hier bitte den E-Mail Betreff.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailText'] = array("E-Mail Text", "Schreiben Sie hier bitte den E-Mail Text.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSendCopy'] = array("Kopie der Nachricht an mich senden", "W&auml;hlen Sie diese Option aus, wenn Sie eine Kopie der Nachricht erhalten m&ouml;chten.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['addEmailAttachment'] = array("Dateianlage hinzuf&uuml;gen", "");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailAttachment'] = array("Dateien ausw&auml;hlen", "W&auml;hlen Sie die Dateien aus dem Dateibaum aus.");
$GLOBALS['TL_LANG']['tl_calendar_events_member']['eventName'] = array("Eventname", "Geben Sie bitte den Eventnamen ein.");


// References
$GLOBALS['TL_LANG']['tl_calendar_events_member']['subscription-not-confirmed'] = 'Anmeldung nicht best&auml;tigt';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['subscription-accepted'] = 'Anmeldung best&auml;tigt';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['subscription-refused'] = 'Anmeldung abgelehnt';
$GLOBALS['TL_LANG']['tl_calendar_events_member']['subscription-waitlisted'] = 'Auf Warteliste';



