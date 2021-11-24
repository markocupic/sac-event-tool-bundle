<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

// Operations
$GLOBALS['TL_LANG']['tl_calendar_events']['registrations'] = array("Event-Anmeldungen", "Bearbeiten Sie die Anmeldungen des Events mit ID %s.");
$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelNext'] = array("Freigabestufe um +1 erhöhen", "Freigabestufe von Datensatz mit ID %s um +1 erhöhen. Der Datensatz ist dann vielleicht nicht mehr bearbeitbar.");
$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelPrev'] = array("Freigabestufe um -1 verringern", "Freigabestufe  von Datensatz mit ID %s um -1 verringern.");


// Legends
$GLOBALS['TL_LANG']['tl_calendar_events']['title_legend'] = "Basis-Einstellungen";
$GLOBALS['TL_LANG']['tl_calendar_events']['broschuere_legend'] = "Einstellungen SAC Kursprogramm PDF Broschüre";
$GLOBALS['TL_LANG']['tl_calendar_events']['deregistration_legend'] = "Einstellungen für Event-Abmeldungen";
$GLOBALS['TL_LANG']['tl_calendar_events']['sign_up_form_legend'] = "Einstellungen für Anmeldeformular";
$GLOBALS['TL_LANG']['tl_calendar_events']['gallery_legend'] = "Einstellungen für die Bildergalerie";
$GLOBALS['TL_LANG']['tl_calendar_events']['registration_legend'] = "Anmelde-Einstellungen";
$GLOBALS['TL_LANG']['tl_calendar_events']['tour_report_legend'] = "Tourenrapport";
$GLOBALS['TL_LANG']['tl_calendar_events']['min_max_member_legend'] = "Teilnehmerzahl Einstellungen";
$GLOBALS['TL_LANG']['tl_calendar_events']['event_type_legend'] = "Event-Art Einstellungen";
$GLOBALS['TL_LANG']['tl_calendar_events']['journey_legend'] = "Event-Art Einstellungen";
$GLOBALS['TL_LANG']['tl_calendar_events']['event_registration_confirmation_legend'] = "E-Mail-Anmeldebestätigung individualisieren";


// Fields
$GLOBALS['TL_LANG']['tl_calendar_events']['courseId'] = array("Kurs.Nr.", "Geben Sie bitte die Kursnummer ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['eventType'] = array("Event-Art", "Geben Sie bitte an, um welche Art von Event es sich handelt.");
$GLOBALS['TL_LANG']['tl_calendar_events']['teaser'] = array("Event-Kurzbeschreibung", "Geben Sie bitte eine Kurzbeschreibung für den Event ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['title'] = array("Event-/Touren-/Kursname", "Geben Sie bitte einen Namen für den Event an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['suitableForBeginners'] = array("Für Anfänger geeignet", "Der Event eignet sich für die Teilnahme von Anfängern in der entsprechenden Bergsport-Disziplin.");
$GLOBALS['TL_LANG']['tl_calendar_events']['mountainguide'] = array("Event mit Bergführer", "Geben Sie bitte an, ob der Event durch Bergführer geleitet wird");
$GLOBALS['TL_LANG']['tl_calendar_events']['alias'][0] = "Event-Alias (wird automatisch gesetzt)";
$GLOBALS['TL_LANG']['tl_calendar_events']['mainInstructor'] = array("Hauptleiter", "Wählen Sie bitte einen Hauptleiter.");
$GLOBALS['TL_LANG']['tl_calendar_events']['instructor'] = array("Leiter", "Wählen Sie bitte einen oder mehrere Leiter aus. Der Erstaufgeführte ist der Hauptverantwortliche und erhält die Onlineanmeldungen.");
$GLOBALS['TL_LANG']['tl_calendar_events']['instructorId'] = array("Leiter auswählen. (Der Erstaufgeführte ist der Hauptleiter)", "Wählen Sie bitte einen oder mehrere Leiter aus. Der Erstaufgeführte ist der Hauptverantwortliche und erhält die Onlineanmeldungen.");
//$GLOBALS['TL_LANG']['tl_calendar_events']['orderInstructor'] = "Sortierung Leiter";
$GLOBALS['TL_LANG']['tl_calendar_events']['askForAhvNumber'] = array("Im Anmeldeformular zur Eingabe der AHV-Nummer auffordern (JUGEND)", "Soll beim Anmeldeformular zur Eingabe der AHV-Nummer aufgefordert werden (Touren/Kurse Jugenden)?");
$GLOBALS['TL_LANG']['tl_calendar_events']['teaser'] = array("Einführungstext", "Geben Sie bitte einen kurzen Einführungstext ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['terms'] = array("Kursziele", "Geben Sie bitte die Kursziele ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['issues'] = array("Kursinhalte", "Geben Sie bitte die Kursinhalte ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['requirements'] = array("Voraussetzungen", "Geben Sie bitte die Voraussetzungen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['leistungen'] = array("Preis und Leistungen", "Geben Sie bitte die Leistungen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['courseLevel'] = array("Kursstufe", "Geben Sie bitte die Kursstufe ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel0'] = array("Kursart (Hauptkategorien)", "Geben Sie bitte die Kursart ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel1'] = array("Kursart (Unterkategorien)", "Geben Sie bitte die Kursart ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['organizers'] = array("Organisierende Gruppe", "Geben Sie bitte die organisierende Gruppe ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['meetingPoint'] = array("Zeit und Treffpunkt", "Geben Sie bitte eine Zeit und einen Treffpunkt an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['addIban'] = array("IBAN Nummer anzeigen", "Geben Sie die IBAN-Nummer an, diese wird beim Anmeldevorgang angezeigt");
$GLOBALS['TL_LANG']['tl_calendar_events']['iban'] = array("IBAN", "Geben Sie die IBAN Nummer ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['addMinAndMaxMembers'] = array("Minimale und maximale Teilnehmerzahl festlegen", "Möchten Sie die Teilnehmerzahl festlegen?");
$GLOBALS['TL_LANG']['tl_calendar_events']['minMembers'] = array("Minimale Teilnehmerzahl", "Geben Sie bitte eine Teilnehmerzahl an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['maxMembers'] = array("Maximale Teilnehmerzahl", "Geben Sie bitte eine Teilnehmerzahl an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['equipment'] = array("Benötigtes Material/Ausrüstung", "Geben Sie bitte eine Liste mit der benötigten Ausrüstung an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['bookingEvent'] = array("Anmeldung", "Geben Sie bitte Details zur Anmeldung an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['miscellaneous'] = array("Sonstiges", "Geben Sie bitte weitere/sonstige Informationen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['isRecurringEvent'] = array("Sich wiederholender Event", "Bitte geben Sie an, ob es sich bei diesem Event um einen sich wiederholdenden Event handelt.");
$GLOBALS['TL_LANG']['tl_calendar_events']['eventDates'] = array("Eventdaten", "Geben Sie bitte die Eventdaten ein. Für jeden Tag eine Zeile.");
$GLOBALS['TL_LANG']['tl_calendar_events']['durationInfo'] = array("Event-Dauer", "Geben SIe die Event-Dauer an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['eventState'] = array("Event Status", "Geben Sie optional einen Event-Status an. !!!Teilnehmer müssen informiert werden.");
/** @todo Falls verschoben, kann hier das Verschiebedatum angegeben werden. */
// eventDeferDate
//$GLOBALS['TL_LANG']['tl_calendar_events']['eventDeferDate'] = array("Verschiebedatum", "Geben Sie das Verschiebedatum an, falls der Anlass verschoben wurde.");
$GLOBALS['TL_LANG']['tl_calendar_events']['singleSRCBroschuere'] = array("Hintergrundbild für PDF Kursbroschüre", "Wählen Sie bitte ein Bild aus.");
$GLOBALS['TL_LANG']['tl_calendar_events']['allowDeregistration'] = array("Angemeldeter Teilnehmer darf sich online vom Event abmelden", "Geben Sie dem angemeldeten Teilnehmer die Möglichkeit unter Einhaltung einer definierten Abmeldefrist sich online vom Event abzumelden.");
$GLOBALS['TL_LANG']['tl_calendar_events']['deregistrationLimit'] = array("Abmeldefrist in Tagen", "Definieren Sie den Zeitraum in Tagen, bis zu dem sich ein bereits angemeldeter Teilnehmer wieder online vom Event abmelden kann.");
$GLOBALS['TL_LANG']['tl_calendar_events']['addGallery'] = array("Dem Event eine Bildergalerie hinzufügen");
$GLOBALS['TL_LANG']['tl_calendar_events']['multiSRC'] = array("Bilder auswählen", "Treffen Sie eine Auswahl von Bildern. Evtl. mässen Sie Ihre Bilder zuerst über die Dateiverwaltung auf den Webserver laden.");
$GLOBALS['TL_LANG']['tl_calendar_events']['journey'] = array("Anreise mit", "Geben Sie an, wie zum Event angereist wird.");
$GLOBALS['TL_LANG']['tl_calendar_events']['setRegistrationPeriod'] = array("Anmeldezeitraum definieren", "Definieren Sie hier den Zeitraum, indem sich Teilnehmer für den Event mit dem Anmeldeformular anmelden können.");
$GLOBALS['TL_LANG']['tl_calendar_events']['disableOnlineRegistration'] = array("Online-Anmeldung deaktivieren", "Wenn Sie diese Einstellung wählen, wird das Online-Anmeldeformular deaktiviert.");
$GLOBALS['TL_LANG']['tl_calendar_events']['registrationGoesTo'] = array("Online-Anmeldungen gehen nicht an den Hauptleiter, sondern an diese Person", "Alle Online-Anmeldungen laufen gewöhnlich über den Hauptleiter (erster Leiter in der Liste). Geben Sie hier weitere Personen an, welche bei Online-Anmeldungen benachrichtigt werden und die Teilnehmerliste administrieren können.");
$GLOBALS['TL_LANG']['tl_calendar_events']['registrationStartDate'] = array("Anmeldung möglich ab", "Definieren Sie hier, ab wann eine Anmeldung mit dem Anmeldformular möglich sein soll.");
$GLOBALS['TL_LANG']['tl_calendar_events']['registrationEndDate'] = array("Anmeldung möglich bis", "Definieren Sie hier, bis wann eine Anmeldung mit dem Anmeldformular möglich sein soll.");
$GLOBALS['TL_LANG']['tl_calendar_events']['eventReleaseLevel'] = array("Freigabestufe", "Definieren Sie hier die Freigabestufe.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourType'] = array("Touren-Typ", "Definieren Sie hier den Tourentyp.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficulty'] = array("Technische Schwierigkeiten", "Definieren Sie hier die technischen Schwierigkeiten. Max. Schwierigkeit nur bei von ... bis ... Angabe eintragen.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMin'] = array("Schwierigkeiten Minimum", "Definieren Sie hier den minimalen technischen Schwierigkeitsgrad.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMax'] = array("Schwierigkeiten Maximum (optional)", "Definieren Sie hier den maximalen technischen Schwierigkeitsgrad.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourDetailText'] = array("Details zur Tour/Route/Wegpunkte", "Geben Sie hier weitere Details zur Tour an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['generalEventDetailText'] = array("Details zum Anlass", "Geben Sie hier weitere Details zum Anlass an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['executionState'] = array("Durchführungsstatus", "Geben Sie an, ob der Event durchgeführt werden konnte.");
$GLOBALS['TL_LANG']['tl_calendar_events']['eventSubstitutionText'] = array("Ersatz-/Ausweichtour, falls die Tour nicht wie ausgeschrieben durchgeführt wurde", "Geben Sie, falls nötig Informationen zur Ersatz-/Ausweichtour an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourWeatherConditions'] = array("Angaben zum Wetter", "Geben Sie hier Angaben zum Wetter ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourAvalancheConditions'] = array("Lawinensituation", "Geben Sie hier Angaben zur Lawinensituation ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourSpecialIncidents'] = array("Besondere Vorkommnisse", "Geben Sie hier Informationen zu besonderen Vorkommnissen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['eventReportAdditionalNotices'] = array("Weitere Bemerkungen", "Geben Sie hier weitere Informationen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['filledInEventReportForm'] = array("Event Rapport ausgefüllt", "Event Rapport wurde ausgefüllt.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfile'] = array("Höhenunterschied und Zeitbedarf pro Tag", "Machen Sie hier zu jedem Tag Angaben zum Profil der Tour.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentMeters'] = array("Höhenunterschied im Aufstieg", "Machen Sie hier Angaben zum Höhenunterschied im Aufstieg.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentTime'] = array("Zeitbedarf im Aufstieg in h", "Machen Sie hier Angaben zum Zeitbedarf im Aufstieg.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentMeters'] = array("Höhenunterschied im Abstieg", "Machen Sie hier Angaben zum Höhenunterschied im Abstieg.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentTime'] = array("Zeitbedarf im Abstieg in h", "Machen Sie hier Angaben zum Zeitbedarf im Abstieg.");
$GLOBALS['TL_LANG']['tl_calendar_events']['generateMainInstructorContactDataFromDb'] = array('<span style="color:red">Kontaktdaten standardmässig aus der Datenbank generieren (Wichtig, falls Online-Anmeldung deaktiviert ist!!!)</span>', 'Die Kontaktdaten werden im Frontend im Feld "Anmeldungen" (auch für nicht eingeloggte Mitglieder) ausgegeben.');
$GLOBALS['TL_LANG']['tl_calendar_events']['customizeEventRegistrationConfirmationEmailText'] = array("E-Mail-Text für Anmeldebestätigung individualisieren.", "");
$GLOBALS['TL_LANG']['tl_calendar_events']['customEventRegistrationConfirmationEmailText'] = array("E-Mail-Text für Anmeldebestätigung", sprintf("Nutzen Sie dieses Feld, um eine individualisierte E-Mail-Bestätigungs für den Event zu erstellen. Fahren Sie mit der Maus über diesen Text, um mehr zu erfahren. Die Tags dienen als Platzhalter für eventspezifische Informationen. %s", "<br><br>" . str_replace('{{br}}', '<br>', Config::get('SAC_EVT_ACCEPT_REGISTRATION_EMAIL_TEXT'))));


// References
// Tech difficulties main categories
$GLOBALS['TL_LANG']['tl_calendar_events']['skiTour'] = array('Skitour');
$GLOBALS['TL_LANG']['tl_calendar_events']['hiking'] = array('Wandern');
$GLOBALS['TL_LANG']['tl_calendar_events']['highAlpineClimb'] = array('Hochtour');
$GLOBALS['TL_LANG']['tl_calendar_events']['climbingFrench'] = array('Klettern (franz.)');
$GLOBALS['TL_LANG']['tl_calendar_events']['climbingUiaa'] = array('Klettern UIAA');
$GLOBALS['TL_LANG']['tl_calendar_events']['mountainbike'] = array('Mountainbike');
$GLOBALS['TL_LANG']['tl_calendar_events']['viaFerrata'] = array('Klettersteig');
$GLOBALS['TL_LANG']['tl_calendar_events']['snowShoeTour'] = array('Schneeschuhtour');
$GLOBALS['TL_LANG']['tl_calendar_events']['endurance'] = array('Konditionelle Anforderungen');
$GLOBALS['TL_LANG']['tl_calendar_events']['iceClimbing'] = array('Eisklettern');
$GLOBALS['TL_LANG']['tl_calendar_events']['event_fully_booked'] = array('Event ausgebucht');
$GLOBALS['TL_LANG']['tl_calendar_events']['event_canceled'] = array('Event abgesagt');
$GLOBALS['TL_LANG']['tl_calendar_events']['event_deferred'] = array('Event verschoben');
$GLOBALS['TL_LANG']['tl_calendar_events']['event_executed_like_predicted'] = array('Event wie ausgeschrieben durchgeführt.');
$GLOBALS['TL_LANG']['tl_calendar_events']['event_adapted'] = array('Ausweichtour-/event');
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_0'] = array('Keine Lawinengefahr');
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_1'] = array('Geringe Lawinengefahr');
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_2'] = array('Mässige Lawinengefahr');
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_3'] = array('Erhebliche Lawinengefahr');
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_4'] = array('Grosse Lawinengefahr');
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_5'] = array('Sehr grosse Lawinengefahr');





