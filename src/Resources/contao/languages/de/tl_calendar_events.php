<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

// Operations
$GLOBALS['TL_LANG']['tl_calendar_events']['registrations'] = array("Event-Anmeldungen", "Bearbeiten Sie die Anmeldungen des Events mit ID %s.");
$GLOBALS['TL_LANG']['tl_calendar_events']['typo3export'] = array("HTML-Export f&uuml;r Typo 3", "Generieren Sie den HTML-Code des Events mit ID %s f&uuml;r den Import nach Typo 3 (alte Webseite).");
$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelNext'] = array("Freigabestufe um +1 erh&ouml;hen", "Freigabestufe von Datensatz mit ID %s um +1 erh&ouml;hen. Der Datensatz ist dann vielleicht nicht mehr bearbeitbar.");
$GLOBALS['TL_LANG']['tl_calendar_events']['releaseLevelPrev'] = array("Freigabestufe um -1 verringern", "Freigabestufe  von Datensatz mit ID %s um -1 verringern.");


// Legends
$GLOBALS['TL_LANG']['tl_calendar_events']['title_legend'] = "Basis-Einstellungen"; 
$GLOBALS['TL_LANG']['tl_calendar_events']['broschuere_legend'] = "Einstellungen f&uuml;r die PDF-Brosch&uuml;re";
$GLOBALS['TL_LANG']['tl_calendar_events']['deregistration_legend'] = "Einstellungen f&uuml;r Event-Abmeldungen";
$GLOBALS['TL_LANG']['tl_calendar_events']['gallery_legend'] = "Einstellungen f&uuml;r die Bildergalerie";
$GLOBALS['TL_LANG']['tl_calendar_events']['registration_legend'] = "Einstellungen f&uuml;r die Online-Anmeldung";
$GLOBALS['TL_LANG']['tl_calendar_events']['tour_report_legend'] = "Tourenrapport";
$GLOBALS['TL_LANG']['tl_calendar_events']['min_max_member_legend'] = "Teilnehmerzahl Einstellungen";
$GLOBALS['TL_LANG']['tl_calendar_events']['event_type_legend'] = "Event-Art Einstellungen";
$GLOBALS['TL_LANG']['tl_calendar_events']['journey_legend'] = "Event-Art Einstellungen";



// Fields
$GLOBALS['TL_LANG']['tl_calendar_events']['eventType'] = array("Event-Art", "Geben Sie bitte an, um welche Art von Event es sich handelt.");
$GLOBALS['TL_LANG']['tl_calendar_events']['teaser'] = array("Event-Kurzbeschreibung", "Geben Sie bitte eine Kurzbeschreibung f&uuml;r den Event ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['title'] = array("Event-/Touren-/Kursname", "Geben Sie bitte einen Namen f&uuml;r den Event an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['inTypo3'] = array("In Typo 3 abgelegt", "");
$GLOBALS['TL_LANG']['tl_calendar_events']['mountainguide'] = array("Event mit Bergf&uuml;hrer", "Geben Sie bitte an, ob der Event durch Bergf&uuml;hrer geleitet wird");
$GLOBALS['TL_LANG']['tl_calendar_events']['alias'][0] = "Event-Alias (wird automatisch gesetzt)";
$GLOBALS['TL_LANG']['tl_calendar_events']['mainInstructor'] = array("Hauptleiter", "W&auml;hlen Sie bitte einen Hauptleiter.");
$GLOBALS['TL_LANG']['tl_calendar_events']['instructor'] = array("Leiter", "W&auml;hlen Sie bitte einen oder mehrere Leiter aus. Der Erstaufgef&uuml;hrte ist der Hauptverantwortliche und erhält die Onlineanmeldungen.");
$GLOBALS['TL_LANG']['tl_calendar_events']['orderInstructor'] = "Sortierung Leiter";
$GLOBALS['TL_LANG']['tl_calendar_events']['teaser'] = array("Einf&uuml;hrungstext", "Geben Sie bitte einen kurzen Einf&uuml;hrungstext ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['terms'] = array("Kursziele", "Geben Sie bitte die Kursziele ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['issues'] = array("Kursinhalte", "Geben Sie bitte die Kursinhalte ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['requirements'] = array("Voraussetzungen", "Geben Sie bitte die Voraussetzungen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['leistungen'] = array("Preis und Leistungen", "Geben Sie bitte die Leistungen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['courseLevel'] = array("Kursstufe", "Geben Sie bitte die Kursstufe ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel0'] = array("Kursart (Hauptkategorien)", "Geben Sie bitte die Kursart ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['courseTypeLevel1'] = array("Kursart (Unterkategorien)", "Geben Sie bitte die Kursart ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['organizers'] = array("Organisierende Gruppe", "Geben Sie bitte die organisierende Gruppe ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['meetingPoint'] = array("Zeit und Treffpunkt", "Geben Sie bitte eine Zeit und einen Treffpunkt an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['addMinAndMaxMembers'] = array("Minimale und maximale Teilnehmerzahl festlegen", "M&ouml;chten Sie die Teilnehmerzahl festlegen?");
$GLOBALS['TL_LANG']['tl_calendar_events']['minMembers'] = array("Minimale Teilnehmerzahl", "Geben Sie bitte eine Teilnehmerzahl an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['maxMembers'] = array("Maximale Teilnehmerzahl", "Geben Sie bitte eine Teilnehmerzahl an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['equipment'] = array("Ben&ouml;tigtes Material", "Geben Sie bitte eine Liste mit dem ben&ouml;tigten Material an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['bookingEvent'] = array("Anmeldung", "Geben Sie bitte Details zur Anmeldung an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['miscellaneous'] = array("Sonstiges", "Geben Sie bitte weitere/sonstige Informationen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['repeatFixedDates'] = array("Eventdaten", "Geben Sie bitte die Eventdaten ein. F&uuml;r jeden Tag eine Zeile.");
$GLOBALS['TL_LANG']['tl_calendar_events']['durationInfo'] = array("Dauer", "Hier kann die Kursdauer angegeben werden.");
$GLOBALS['TL_LANG']['tl_calendar_events']['eventCanceled'] = array("Event abgesagt", "Diesen Event als 'abgesagt' markieren.");
$GLOBALS['TL_LANG']['tl_calendar_events']['singleSRCBroschuere'] = array("Hintergrundbild fuer Broschuere", "W&auml;hlen Sie bitte ein Bild aus.");
$GLOBALS['TL_LANG']['tl_calendar_events']['allowDeregistration'] = array("Angemeldeter Teilnehmer darf sich online vom Event abmelden", "Geben Sie dem angemeldeten Teilnehmer die M&ouml;glichkeit unter Einhaltung einer definierten Abmeldefrist sich online vom Event abzumelden.");
$GLOBALS['TL_LANG']['tl_calendar_events']['deregistrationLimit'] = array("Abmeldefrist in Tagen", "Definieren Sie den Zeitraum in Tagen, bis zu dem sich ein bereits angemeldeter Teilnehmer wieder online vom Event abmelden kann.");
$GLOBALS['TL_LANG']['tl_calendar_events']['addGallery'] = array("Dem Event eine Bildergalerie hinzuf&uuml;gen");
$GLOBALS['TL_LANG']['tl_calendar_events']['multiSRC'] = array("Bilder ausw&auml;hlen", "Treffen Sie eine Auswahl von Bildern. Evtl. m&auml;ssen Sie Ihre Bilder zuerst &uuml;ber die Dateiverwaltung auf den Webserver laden.");
$GLOBALS['TL_LANG']['tl_calendar_events']['journey'] = array("Anreise mit", "Geben Sie an, wie zum Event angereist wird.");
$GLOBALS['TL_LANG']['tl_calendar_events']['setRegistrationPeriod'] = array("Anmeldezeitraum definieren", "Definieren Sie hier den Zeitraum, indem sich Teilnehmer f&uuml;r den Event mit dem Anmeldeformular anmelden k&ouml;nnen.");
$GLOBALS['TL_LANG']['tl_calendar_events']['disableOnlineRegistration'] = array("Online-Anmeldung deaktivieren", "Wenn Sie diese Einstellung w&auml;hlen, wird das Online-Anmeldeformular deaktiviert.");
$GLOBALS['TL_LANG']['tl_calendar_events']['disableOnlineRegistration'] = array("Online-Anmeldung deaktivieren", "Wenn Sie diese Einstellung w&auml;hlen, wird das Online-Anmeldeformular deaktiviert.");
$GLOBALS['TL_LANG']['tl_calendar_events']['registrationGoesTo'] = array("Online-Anmeldungen gehen an diese Person", "Alle Online-Anmeldungen laufen gew&ouml;hnlich &uuml;ber den Hauptleiter. Geben Sie hier weitere Personen an, welche bei Online-Anmeldungen benachrichtigt werden und die Teilnehmerliste administrieren k&ouml;nnen.");
$GLOBALS['TL_LANG']['tl_calendar_events']['registrationStartDate'] = array("Anmeldung m&ouml;glich ab:", "Definieren Sie hier, ab wann eine Anmeldung mit dem Anmeldformular m&ouml;glich sein soll.");
$GLOBALS['TL_LANG']['tl_calendar_events']['registrationEndDate'] = array("Anmeldung m&ouml;glich bis:", "Definieren Sie hier, bis wann eine Anmeldung mit dem Anmeldformular m&ouml;glich sein soll.");
$GLOBALS['TL_LANG']['tl_calendar_events']['eventReleaseLevel'] = array("Freigabestufe", "Definieren Sie hier die Freigabestufe.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourType'] = array("Touren-Typ:", "Definieren Sie hier den Tourentyp.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourTechDifficulty'] = array("Technische Schwierigkeiten:", "Definieren Sie hier die technischen Schwierigkeiten.");
$GLOBALS['TL_LANG']['tl_calendar_events']['altitudeDifference'] = array("H&ouml;henmeter im Aufstieg pro Tag", "Definieren Sie die maximalen H&ouml;henmeter im Aufstieg pro Tag.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourDetailText'] = array("Details zur Tour/Route", "Geben Sie hier weitere Details zur Tour an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourHasExecutedLikePredicted'] = array("Tour wie ausgeschrieben durchgef&uuml;hrt", "Geben Sie an, ob der Event, wie im Jahresprogramm angek&uuml;ndigt durchgef&uuml;hrt werden konnte.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourSubstitutionText'] = array("Ersatz-/Ausweichtour, falls die Tour nicht wie ausgeschrieben durchgef&uuml;hrt wurde", "Geben Sie, falls n&ouml;tig Informationen zur Ersatz-/Ausweichtour an.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourWeatherConditions'] = array("Angaben zum Wetter", "Geben Sie hier Angaben zum Wetter ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourAvalancheConditions'] = array("Lawinensituation", "Geben Sie hier Angaben zur Lawinensituation ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['tourSpecialIncidents'] = array("Besondere Vorkommnisse", "Geben Sie hier Informationen zu besonderen Vorkommnissen ein.");
$GLOBALS['TL_LANG']['tl_calendar_events']['filledInEventReportForm'] = array("Event Rapport ausgef&uuml;llt", "Event Rapport wurde ausgef&uuml;llt.");



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



