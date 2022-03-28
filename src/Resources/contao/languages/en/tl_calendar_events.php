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

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

// Global operations
$GLOBALS['TL_LANG']['tl_calendar_events']['plus1year'] = ['+ 1 Jahr', 'Ändere bei allen Events die Datumsangaben um + 1 Jahr.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['minus1year'] = ['- 1 Jahr', 'Ändere bei allen Events die Datumsangaben um - 1 Jahr.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['onloadCallbackExportCalendar'] = ['Excel Export', 'Exportiere die Events im Excel-/CSV-Format.'];

// Operations
$GLOBALS['TL_LANG']['tl_calendar_events']['registrations'] = ['Event-Anmeldungen', 'Bearbeiten Sie die Anmeldungen des Events mit ID %s.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelNext'] = ['Freigabestufe um +1 erhöhen', 'Freigabestufe von Datensatz mit ID %s um +1 erhöhen. Der Datensatz ist dann vielleicht nicht mehr bearbeitbar.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelPrev'] = ['Freigabestufe um -1 verringern', 'Freigabestufe  von Datensatz mit ID %s um -1 verringern.'];

// Legends
$GLOBALS['TL_LANG']['tl_calendar_events']['title_legend'] = 'Basis-Einstellungen';
$GLOBALS['TL_LANG']['tl_calendar_events']['broschuere_legend'] = 'Einstellungen SAC Kursprogramm PDF Broschüre';
$GLOBALS['TL_LANG']['tl_calendar_events']['deregistration_legend'] = 'Einstellungen für Event-Abmeldungen';
$GLOBALS['TL_LANG']['tl_calendar_events']['sign_up_form_legend'] = 'Einstellungen für Anmeldeformular';
$GLOBALS['TL_LANG']['tl_calendar_events']['gallery_legend'] = 'Einstellungen für die Bildergalerie';
$GLOBALS['TL_LANG']['tl_calendar_events']['registration_legend'] = 'Anmelde-Einstellungen';
$GLOBALS['TL_LANG']['tl_calendar_events']['tour_report_legend'] = 'Tourenrapport';
$GLOBALS['TL_LANG']['tl_calendar_events']['min_max_member_legend'] = 'Teilnehmerzahl Einstellungen';
$GLOBALS['TL_LANG']['tl_calendar_events']['event_type_legend'] = 'Event-Art Einstellungen';
$GLOBALS['TL_LANG']['tl_calendar_events']['journey_legend'] = 'Event-Art Einstellungen';
$GLOBALS['TL_LANG']['tl_calendar_events']['event_registration_confirmation_legend'] = 'E-Mail-Anmeldebestätigung individualisieren';

// Fields
$GLOBALS['TL_LANG']['tl_calendar_events']['courseId'] = ['Kurs.Nr.', 'Geben Sie bitte die Kursnummer ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['eventType'] = ['Event-Art', 'Geben Sie bitte an, um welche Art von Event es sich handelt.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['teaser'] = ['Event-Kurzbeschreibung', 'Geben Sie bitte eine Kurzbeschreibung für den Event ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['title'] = ['Event-/Touren-/Kursname', 'Geben Sie bitte einen Namen für den Event an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['suitableForBeginners'] = ['Für Anfänger geeignet', 'Der Event eignet sich für die Teilnahme von Anfängern in der entsprechenden Bergsport-Disziplin.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['mountainguide'] = ['Event mit Bergführer', 'Geben Sie bitte an, ob der Event durch Bergführer geleitet wird'];
$GLOBALS['TL_LANG']['tl_calendar_events']['alias'][0] = 'Event-Alias (wird automatisch gesetzt)';
$GLOBALS['TL_LANG']['tl_calendar_events']['mainInstructor'] = ['Hauptleiter', 'Wählen Sie bitte einen Hauptleiter.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['instructor'] = ['Leiter', 'Wählen Sie bitte einen oder mehrere Leiter aus. Der Erstaufgeführte ist der Hauptverantwortliche und erhält die Onlineanmeldungen.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['instructorId'] = ['Leiter auswählen. (Der Erstaufgeführte ist der Hauptleiter)', 'Wählen Sie bitte einen oder mehrere Leiter aus. Der Erstaufgeführte ist der Hauptverantwortliche und erhält die Onlineanmeldungen.'];
//$GLOBALS['TL_LANG']['tl_calendar_events']['orderInstructor'] = "Sortierung Leiter";
$GLOBALS['TL_LANG']['tl_calendar_events']['askForAhvNumber'] = ['Im Anmeldeformular zur Eingabe der AHV-Nummer auffordern (JUGEND)', 'Soll beim Anmeldeformular zur Eingabe der AHV-Nummer aufgefordert werden (Touren/Kurse Jugenden)?'];
$GLOBALS['TL_LANG']['tl_calendar_events']['teaser'] = ['Einführungstext', 'Geben Sie bitte einen kurzen Einführungstext ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['terms'] = ['Kursziele', 'Geben Sie bitte die Kursziele ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['issues'] = ['Kursinhalte', 'Geben Sie bitte die Kursinhalte ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['requirements'] = ['Voraussetzungen', 'Geben Sie bitte die Voraussetzungen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['leistungen'] = ['Preis und Leistungen', 'Geben Sie bitte die Leistungen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['courseLevel'] = ['Kursstufe', 'Geben Sie bitte die Kursstufe ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel0'] = ['Kursart (Hauptkategorien)', 'Geben Sie bitte die Kursart ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel1'] = ['Kursart (Unterkategorien)', 'Geben Sie bitte die Kursart ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['organizers'] = ['Organisierende Gruppe', 'Geben Sie bitte die organisierende Gruppe ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['meetingPoint'] = ['Zeit und Treffpunkt', 'Geben Sie bitte eine Zeit und einen Treffpunkt an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['addIban'] = ['IBAN Nummer anzeigen', 'Geben Sie die IBAN-Nummer an, diese wird beim Anmeldevorgang angezeigt'];
$GLOBALS['TL_LANG']['tl_calendar_events']['iban'] = ['IBAN', 'Geben Sie die IBAN Nummer ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['ibanBeneficiary'] = ['Begünstigter (IBAN)', 'Geben Sie den zur IBAN Nummer gehörenden Begünstigten an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['addMinAndMaxMembers'] = ['Minimale und maximale Teilnehmerzahl festlegen', 'Möchten Sie die Teilnehmerzahl festlegen?'];
$GLOBALS['TL_LANG']['tl_calendar_events']['minMembers'] = ['Minimale Teilnehmerzahl', 'Geben Sie bitte eine Teilnehmerzahl an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['maxMembers'] = ['Maximale Teilnehmerzahl', 'Geben Sie bitte eine Teilnehmerzahl an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['equipment'] = ['Benötigtes Material/Ausrüstung', 'Geben Sie bitte eine Liste mit der benötigten Ausrüstung an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['bookingEvent'] = ['Anmeldung', 'Geben Sie bitte Details zur Anmeldung an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['miscellaneous'] = ['Sonstiges', 'Geben Sie bitte weitere/sonstige Informationen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['isRecurringEvent'] = ['Sich wiederholender Event', 'Bitte geben Sie an, ob es sich bei diesem Event um einen sich wiederholdenden Event handelt.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['eventDates'] = ['Eventdaten', 'Geben Sie bitte die Eventdaten ein. Für jeden Tag eine Zeile.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['durationInfo'] = ['Event-Dauer', 'Geben SIe die Event-Dauer an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['eventState'] = ['Event Status', 'Geben Sie optional einen Event-Status an. !!!Teilnehmer müssen informiert werden.'];
/** @todo Falls verschoben, kann hier das Verschiebedatum angegeben werden. */
// eventDeferDate
//$GLOBALS['TL_LANG']['tl_calendar_events']['eventDeferDate'] = array("Verschiebedatum", "Geben Sie das Verschiebedatum an, falls der Anlass verschoben wurde.");
$GLOBALS['TL_LANG']['tl_calendar_events']['singleSRCBroschuere'] = ['Hintergrundbild für PDF Kursbroschüre', 'Wählen Sie bitte ein Bild aus.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['allowDeregistration'] = ['Angemeldeter Teilnehmer darf sich online vom Event abmelden', 'Geben Sie dem angemeldeten Teilnehmer die Möglichkeit unter Einhaltung einer definierten Abmeldefrist sich online vom Event abzumelden.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['deregistrationLimit'] = ['Abmeldefrist in Tagen', 'Definieren Sie den Zeitraum in Tagen, bis zu dem sich ein bereits angemeldeter Teilnehmer wieder online vom Event abmelden kann.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['addGallery'] = ['Dem Event eine Bildergalerie hinzufügen'];
$GLOBALS['TL_LANG']['tl_calendar_events']['multiSRC'] = ['Bilder auswählen', 'Treffen Sie eine Auswahl von Bildern. Evtl. mässen Sie Ihre Bilder zuerst über die Dateiverwaltung auf den Webserver laden.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['journey'] = ['Anreise mit', 'Geben Sie an, wie zum Event angereist wird.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['setRegistrationPeriod'] = ['Anmeldezeitraum definieren', 'Definieren Sie hier den Zeitraum, indem sich Teilnehmer für den Event mit dem Anmeldeformular anmelden können.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['disableOnlineRegistration'] = ['Online-Anmeldung deaktivieren', 'Wenn Sie diese Einstellung wählen, wird das Online-Anmeldeformular deaktiviert.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['registrationGoesTo'] = ['Online-Anmeldungen gehen nicht an den Hauptleiter, sondern an diese Person', 'Alle Online-Anmeldungen laufen gewöhnlich über den Hauptleiter (erster Leiter in der Liste). Geben Sie hier weitere Personen an, welche bei Online-Anmeldungen benachrichtigt werden und die Teilnehmerliste administrieren können.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['registrationStartDate'] = ['Anmeldung möglich ab', 'Definieren Sie hier, ab wann eine Anmeldung mit dem Anmeldformular möglich sein soll.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['registrationEndDate'] = ['Anmeldung möglich bis', 'Definieren Sie hier, bis wann eine Anmeldung mit dem Anmeldformular möglich sein soll.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['eventReleaseLevel'] = ['Freigabestufe', 'Definieren Sie hier die Freigabestufe.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourType'] = ['Touren-Typ', 'Definieren Sie hier den Tourentyp.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficulty'] = ['Technische Schwierigkeiten', 'Definieren Sie hier die technischen Schwierigkeiten. Max. Schwierigkeit nur bei von ... bis ... Angabe eintragen.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMin'] = ['Schwierigkeiten Minimum', 'Definieren Sie hier den minimalen technischen Schwierigkeitsgrad.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficultyMax'] = ['Schwierigkeiten Maximum (optional)', 'Definieren Sie hier den maximalen technischen Schwierigkeitsgrad.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourDetailText'] = ['Details zur Tour/Route/Wegpunkte', 'Geben Sie hier weitere Details zur Tour an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['generalEventDetailText'] = ['Details zum Anlass', 'Geben Sie hier weitere Details zum Anlass an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['executionState'] = ['Durchführungsstatus', 'Geben Sie an, ob der Event durchgeführt werden konnte.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['eventSubstitutionText'] = ['Ersatz-/Ausweichtour, falls die Tour nicht wie ausgeschrieben durchgeführt wurde', 'Geben Sie, falls nötig Informationen zur Ersatz-/Ausweichtour an.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourWeatherConditions'] = ['Angaben zum Wetter', 'Geben Sie hier Angaben zum Wetter ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourAvalancheConditions'] = ['Lawinensituation', 'Geben Sie hier Angaben zur Lawinensituation ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourSpecialIncidents'] = ['Besondere Vorkommnisse', 'Geben Sie hier Informationen zu besonderen Vorkommnissen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['eventReportAdditionalNotices'] = ['Weitere Bemerkungen', 'Geben Sie hier weitere Informationen ein.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['filledInEventReportForm'] = ['Event Rapport ausgefüllt', 'Event Rapport wurde ausgefüllt.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfile'] = ['Höhenunterschied und Zeitbedarf pro Tag', 'Machen Sie hier zu jedem Tag Angaben zum Profil der Tour.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentMeters'] = ['Höhenunterschied im Aufstieg', 'Machen Sie hier Angaben zum Höhenunterschied im Aufstieg.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileAscentTime'] = ['Zeitbedarf im Aufstieg in h', 'Machen Sie hier Angaben zum Zeitbedarf im Aufstieg.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentMeters'] = ['Höhenunterschied im Abstieg', 'Machen Sie hier Angaben zum Höhenunterschied im Abstieg.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['tourProfileDescentTime'] = ['Zeitbedarf im Abstieg in h', 'Machen Sie hier Angaben zum Zeitbedarf im Abstieg.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['generateMainInstructorContactDataFromDb'] = ['<span style="color:red">Kontaktdaten standardmässig aus der Datenbank generieren (Wichtig, falls Online-Anmeldung deaktiviert ist!!!)</span>', 'Die Kontaktdaten werden im Frontend im Feld "Anmeldungen" (auch für nicht eingeloggte Mitglieder) ausgegeben.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['customizeEventRegistrationConfirmationEmailText'] = ['E-Mail-Text für Anmeldebestätigung individualisieren.', ''];
$GLOBALS['TL_LANG']['tl_calendar_events']['customEventRegistrationConfirmationEmailText'] = ['E-Mail-Text für Anmeldebestätigung', 'Nutzen Sie dieses Feld, um eine individualisierte E-Mail-Bestätigungs für den Event zu erstellen. Fahren Sie mit der Maus über diesen Text, um mehr zu erfahren. Die Tags dienen als Platzhalter für eventspezifische Informationen.'];

// References
// Tech difficulties main categories
$GLOBALS['TL_LANG']['tl_calendar_events']['skiTour'] = ['Skitour'];
$GLOBALS['TL_LANG']['tl_calendar_events']['hiking'] = ['Wandern'];
$GLOBALS['TL_LANG']['tl_calendar_events']['highAlpineClimb'] = ['Hochtour'];
$GLOBALS['TL_LANG']['tl_calendar_events']['climbingFrench'] = ['Klettern (franz.)'];
$GLOBALS['TL_LANG']['tl_calendar_events']['climbingUiaa'] = ['Klettern UIAA'];
$GLOBALS['TL_LANG']['tl_calendar_events']['mountainbike'] = ['Mountainbike'];
$GLOBALS['TL_LANG']['tl_calendar_events']['viaFerrata'] = ['Klettersteig'];
$GLOBALS['TL_LANG']['tl_calendar_events']['snowShoeTour'] = ['Schneeschuhtour'];
$GLOBALS['TL_LANG']['tl_calendar_events']['endurance'] = ['Konditionelle Anforderungen'];
$GLOBALS['TL_LANG']['tl_calendar_events']['iceClimbing'] = ['Eisklettern'];
$GLOBALS['TL_LANG']['tl_calendar_events']['event_fully_booked'] = ['Event ausgebucht'];
$GLOBALS['TL_LANG']['tl_calendar_events']['event_canceled'] = ['Event abgesagt'];
$GLOBALS['TL_LANG']['tl_calendar_events']['event_deferred'] = ['Event verschoben'];
$GLOBALS['TL_LANG']['tl_calendar_events']['event_executed_like_predicted'] = ['Event wie ausgeschrieben durchgeführt.'];
$GLOBALS['TL_LANG']['tl_calendar_events']['event_adapted'] = ['Ausweichtour-/event'];
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_0'] = ['Keine Lawinengefahr'];
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_1'] = ['Geringe Lawinengefahr'];
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_2'] = ['Mässige Lawinengefahr'];
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_3'] = ['Erhebliche Lawinengefahr'];
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_4'] = ['Grosse Lawinengefahr'];
$GLOBALS['TL_LANG']['tl_calendar_events']['avalanche_level_5'] = ['Sehr grosse Lawinengefahr'];
