<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

// Overwrite defaults

$GLOBALS['TL_LANG']['MSC']['confirmation'] = 'Passwort erneut eingeben';
$GLOBALS['TL_LANG']['MSC']['yourUpcomingEvents'] = 'Deine nächsten Events';

$GLOBALS['TL_LANG']['CTE']['user_portrait'] = array('SAC-User-Portrait');
$GLOBALS['TL_LANG']['CTE']['user_portrait_list'] = array('SAC-User-Portrait-Liste');
$GLOBALS['TL_LANG']['CTE']['sac_calendar_newsletter'] = array('SAC-Events-Elemente');
$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_status_1'] = 'Freie Plätze!';
$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_status_2'] = 'Anmeldefrist für Event ist abgelaufen!';
$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_status_3'] = 'Event ausgebucht!';
$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_status_4'] = 'Event abgesagt!';
$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_status_5'] = 'Anmelden noch nicht möglich!';
$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_status_6'] = 'Event verschoben!';
$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_status_7'] = 'Keine Online-Anmeldung möglich!';

$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_fully_booked'] = 'Event ausgebucht!';
$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_canceled'] = 'Event abgesagt!';
$GLOBALS['TL_LANG']['CTE']['calendar_events']['event_deferred'] = 'Event verschoben!';

if (TL_MODE === 'FE')
{
    $GLOBALS['TL_LANG']['MSC']['username'] = 'SAC Mitgliedernummer';
}

// References
$GLOBALS['TL_LANG']['MSC']['courseLevel'][1] = 'Einführungskurs';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][2] = 'Grundstufe';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][3] = 'Fortbildungskurs';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][4] = 'Tourenleiter Fortbildungskurs';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][5] = 'Tourenleiter Fortbildungskurs';
$GLOBALS['TL_LANG']['MSC']['course'] = 'Kurs';
$GLOBALS['TL_LANG']['MSC']['tour'] = 'Tour';
$GLOBALS['TL_LANG']['MSC']['lastMinuteTour'] = 'Last Minute Tour';
$GLOBALS['TL_LANG']['MSC']['generalEvent'] = 'Veranstaltung (Fitnesstrainings, Skiturnen, Kultur, Vorträge + sektionsübergreifende Events)';

// Buttons
$GLOBALS['TL_LANG']['MSC']['sendEmail'] = 'E-Mail senden';
$GLOBALS['TL_LANG']['MSC']['plus1year'] = '+1 Jahr';
$GLOBALS['TL_LANG']['MSC']['minus1year'] = '-1 Jahr';
$GLOBALS['TL_LANG']['MSC']['plusOneReleaseLevel'] = 'Freigabestufe ++';
$GLOBALS['TL_LANG']['MSC']['minusOneReleaseLevel'] = 'Freigabestufe --';
$GLOBALS['TL_LANG']['MSC']['printInstructorInvoiceButton'] = 'Vergütungsformular und Rapport drucken';
$GLOBALS['TL_LANG']['MSC']['writeTourReportButton'] = 'Tourenrapport bearbeiten';
$GLOBALS['TL_LANG']['MSC']['backToEvent'] = 'Zurück zum Event';
$GLOBALS['TL_LANG']['MSC']['onloadCallbackExportCalendar'] = 'Events exportieren';

// Confirm messages
$GLOBALS['TL_LANG']['MSC']['plus1yearConfirm'] = 'Möchten Sie wirklich die Eventzeitpunkte aller Events in diesem Kalender um 1 Jahr nach vorne schieben?';
$GLOBALS['TL_LANG']['MSC']['minus1yearConfirm'] = 'Möchten Sie wirklich die Eventzeitpunkte aller Events in diesem Kalender um 1 Jahr nach vorne schieben?';
$GLOBALS['TL_LANG']['MSC']['plusOneReleaseLevelConfirm'] = 'Möchten Sie wirklich alle hier gelisteten Events um eine Freigabestufe erhöhen?';
$GLOBALS['TL_LANG']['MSC']['minusOneReleaseLevelConfirm'] = 'Möchten Sie wirklich alle hier gelisteten Events um eine Freigabestufe vermindern?';
$GLOBALS['TL_LANG']['MSC']['deleteEventMembersBeforeDeleteEvent'] = 'Für den Event mit ID %s sind Anmeldungen vorhanden. Bitte löschen Sie diese bevor Sie den Event selber löschen.';
$GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'] = 'Die Freigabestufe für Event mit ID %s wurde auf Level %s gesetzt.';
$GLOBALS['TL_LANG']['MSC']['publishedEvent'] = 'Der Event mit ID %s wurde veröffentlicht.';
$GLOBALS['TL_LANG']['MSC']['unpublishedEvent'] = 'Der Event mit ID %s ist nicht mehr veröffentlicht.';
$GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck'] = 'Das Datum für den Anfang des Anmeldezeitraums musste angepasst werden. Bitte kontrollieren Sie dieses nochmals.';
$GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck'] = 'Das Datum für das Ende des Anmeldezeitraums musste angepasst werden. Bitte kontrollieren Sie dieses nochmals.';
$GLOBALS['TL_LANG']['MSC']['missingPermissionsToEditEvent'] = 'Sie haben nicht die erforderlichen Berechtigungen den Datensatz mit ID %s zu bearbeiten.';
$GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'] = 'Sie haben nicht die erforderlichen Berechtigungen den Datensatz mit ID %s zu löschen.';
$GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'] = 'Sie haben nicht die erforderlichen Berechtigungen den Datensatz mit ID %s zu veröffentlichen.';
$GLOBALS['TL_LANG']['MSC']['generateInvoice'] = 'Möchten Sie das Vergütungsformular ausdrucken?';
$GLOBALS['TL_LANG']['MSC']['generateTourRapport'] = 'Möchten Sie den Tour-Rapport ausdrucken?';
$GLOBALS['TL_LANG']['MSC']['writeTourReport'] = 'Möchten Sie den Tourenrapport erstellen/bearbeiten?';
$GLOBALS['TL_LANG']['MSC']['goToPartcipantList'] = 'Möchten Sie zur Teilnehmerliste wechseln?';
$GLOBALS['TL_LANG']['MSC']['goToInvoiceList'] = 'Möchten Sie das Vergütungsformular bearbeiten/erstellen?';

// Event registration frontend module
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventNotFound'] = 'Event mit ID: %s nicht gefunden.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_onlineRegDisabled'] = 'Der Leiter hat die Online-Anmeldung zu diesem Event deaktiviert. Bitte beachte die Tourenauschreibung.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventFullyBooked'] = 'Dieser Anlass ist ausgebucht. Bitte erkundige dich beim Leiter, ob eine Nachmeldung möglich ist.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventCanceled'] = 'Dieser Anlass wurde abgesagt. Es ist keine Anmeldung möglich.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventDeferred'] = 'Dieser Anlass ist verschoben worden.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_registrationPossibleOn'] = 'Anmeldungen für <strong>"%s"</strong> sind erst ab dem %s möglich.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_registrationDeadlineExpired'] = 'Die Anmeldefrist für diesen Event ist am %s abgelaufen.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_registrationPossible24BeforeEventStart'] = 'Die Anmeldefrist für diesen Event ist abgelaufen. Du kannst dich bis 24 Stunden vor Event-Beginn anmelden. Nimm gegebenenfalls mit dem Leiter Kontakt auf.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventDateOverlapError'] = 'Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_mainInstructorNotFound'] = 'Der Hauptleiter mit ID "%s" wurde nicht in der Datenbank gefunden. Bitte nimm persönlich Kontakt mit dem Leiter auf.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_mainInstructorsEmailAddrNotFound'] = 'Dem Hauptleiter mit ID "%s" ist keine gültige E-Mail zugewiesen. Bitte nimm persönlich mit dem Leiter Kontakt auf.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_membersEmailAddrNotFound'] = 'Leider wurde für dieses Mitgliederkonto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlege auf auf der Internetseite des Zentralverbands deine E-Mail-Adresse.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_bookingLimitReaches'] = 'Die maximale Teilnehmerzahl für diesen Event ist bereits erreicht. Wenn du dich trotzdem anmeldest, gelangst du auf die Warteliste und kannst bei Absagen evtl. nachrücken. Du kannst selbstverständlich auch mit dem Leiter Kontakt aufnehmen, um Genaueres zu erfahren.';

// Event registration frontend module form labels
$GLOBALS['TL_LANG']['FORM']['evt_reg_ticketInfo'] = 'Ich besitze ein/eine';
$GLOBALS['TL_LANG']['FORM']['evt_reg_carInfo'] = 'Ich könnte ein Auto mit ... Plätzen (inkl. Fahrer) mitnehmen';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ahvNumber'] = 'AHV-Nummer';
$GLOBALS['TL_LANG']['FORM']['evt_reg_mobile'] = 'Mobilnummer';
$GLOBALS['TL_LANG']['FORM']['evt_reg_emergencyPhone'] = 'Notfalltelefonnummer/In Notfällen zu kontaktieren';
$GLOBALS['TL_LANG']['FORM']['evt_reg_emergencyPhoneName'] = 'Notfalltelefonnummer/In Notfällen zu kontaktieren';
$GLOBALS['TL_LANG']['FORM']['evt_reg_notes'] = 'Anmerkungen/Erfahrungen/Referenztouren';
$GLOBALS['TL_LANG']['FORM']['evt_reg_foodHabits'] = 'Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)';
//$GLOBALS['TL_LANG']['FORM']['evt_reg_agb'][0] = '';
$GLOBALS['TL_LANG']['FORM']['evt_reg_agb'][1] = 'Ich akzeptiere <a href="#" data-bs-toggle="modal" data-bs-target="#agbModal">das Kurs- und Tourenreglement.</a>';
$GLOBALS['TL_LANG']['FORM']['evt_reg_submit'] = 'Für Event anmelden';

// Event registration frontend module form explanations
$GLOBALS['TL_LANG']['FORM']['evt_reg_mobileExpl'] = 'Das Feld "Mobilnummer" ist kein Pflichtfeld und kann leergelassen werden. Damit der Leiter dich aber während der Tour bei Zwischenfällen erreichen kann, ist es für ihn sehr hilfreich, deine Mobilnummer zu kennen. Selbstverständlich werden diese Angaben vertraulich behandelt und nicht an Dritte weitergegeben.';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ahvExpl'] = 'Sämtliche Daten werden lediglich für interne Zwecke verwendet. Die AHV-Nummer wird ausschliesslich für die Abrechnung oder Rückforderung von Geldern von J+S verwendet. Die persönlichen Daten werden vertraulich behandelt. Eine Weitergabe an Drittorganisationen ist ausgeschlossen.';
$GLOBALS['TL_LANG']['FORM']['evt_reg_notesExpl'] = 'Bitte beschreibe in wenigen Worten dein Leistungsniveau und/oder beantworte, die in den Anmeldebestimmungen verlangten Angaben (z.B. bereits absolvierte Referenztouren oder Essgewohnheiten bei Events mit Übernachtung, etc.).';

// Miscelaneous
$GLOBALS['TL_LANG']['MSC']['published'] = 'veröffentlicht';
$GLOBALS['TL_LANG']['MSC']['unpublished'] = 'unveröffentlicht';

// Meta wizard
$GLOBALS['TL_LANG']['MSC']['aw_photographer'] = 'Photograph';

