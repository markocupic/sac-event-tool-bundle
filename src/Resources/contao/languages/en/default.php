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

use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;

// Override defaults
if (TL_MODE === 'FE') {
    $GLOBALS['TL_LANG']['MSC']['username'] = 'SAC Mitgliedernummer';
    $GLOBALS['TL_LANG']['MSC']['confirmation'] = 'Passwort erneut eingeben';
}

// DCA
// tl_user_role
$GLOBALS['TL_LANG']['MSC']['roleCurrentlyVacant'] = 'Benutzer-Rolle im Moment vakant';
// tl_member
$GLOBALS['TL_LANG']['ERR']['clearMemberProfile'] = 'Das Mitglied mit ID %d kann nicht gelöscht werden, weil es bei einem oder mehreren Events noch auf der Buchungsliste steht.';
// tl_event_release_level_policy
$GLOBALS['TL_LANG']['MSC']['level'] = 'Stufe';
// tl_calendar_events_member
$GLOBALS['TL_LANG']['ERR']['accessDenied'] = 'Zutritt verweigert.';
$GLOBALS['TL_LANG']['MSC']['messageSuccessfullySent'] = 'Die Nachricht wurde erfolgreich versandt.';

// Content elements
$GLOBALS['TL_LANG']['CTE']['user_portrait'] = ['SAC-User-Portrait'];
$GLOBALS['TL_LANG']['CTE']['user_portrait_list'] = ['SAC-User-Portrait-Liste'];

// Events
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_1'] = 'Freie Plätze!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_2'] = 'Anmeldefrist für Event ist abgelaufen!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_3'] = 'Event ausgebucht!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_4'] = 'Event abgesagt!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_5'] = 'Anmelden noch nicht möglich!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_6'] = 'Event verschoben!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_7'] = 'Keine Online-Anmeldung möglich!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_8'] = 'Max. Teilnehmerzahl erreicht. Anmeldung auf Warteliste möglich.';

$GLOBALS['TL_LANG']['MSC']['calendar_events'][EventState::STATE_FULLY_BOOKED] = 'Event ausgebucht!';
$GLOBALS['TL_LANG']['MSC']['calendar_events'][EventState::STATE_CANCELED] = 'Event abgesagt!';
$GLOBALS['TL_LANG']['MSC']['calendar_events'][EventState::STATE_DEFERRED] = 'Event verschoben!';

$GLOBALS['TL_LANG']['MSC']['calendar_events']['withoutMountainGuide'] = 'Ohne Bergführer';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['withMountainGuide'] = 'Mit Bergführer';

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
$GLOBALS['TL_LANG']['MSC']['deleteEventMembersBeforeDeleteEvent'] = 'Für den Event mit ID %s sind Anmeldungen vorhanden. Bitte löschen Sie diese, bevor Sie den Event selber löschen.';
$GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'] = 'Die Freigabestufe für Event mit ID %s wurde auf Level %s gesetzt.';
$GLOBALS['TL_LANG']['MSC']['publishedEvent'] = 'Der Event mit ID %s wurde veröffentlicht.';
$GLOBALS['TL_LANG']['MSC']['unpublishedEvent'] = 'Der Event mit ID %s ist nicht mehr veröffentlicht.';
$GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck'] = 'Das Datum für den Anfang des Anmeldezeitraums musste angepasst werden. Bitte kontrollieren Sie dieses nochmals.';
$GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck'] = 'Das Datum für das Ende des Anmeldezeitraums musste angepasst werden. Bitte kontrollieren Sie dieses nochmals.';
$GLOBALS['TL_LANG']['MSC']['missingPermissionsToEditEvent'] = 'Sie haben nicht die erforderliche Berechtigung den Datensatz mit ID %s zu bearbeiten.';
$GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'] = 'Sie haben nicht die erforderliche Berechtigung den Datensatz mit ID %s zu löschen.';
$GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'] = 'Sie haben nicht die erforderliche Berechtigung den Datensatz mit ID %s zu veröffentlichen.';
$GLOBALS['TL_LANG']['MSC']['generateInvoice'] = 'Möchten Sie das Vergütungsformular ausdrucken?';
$GLOBALS['TL_LANG']['MSC']['generateTourRapport'] = 'Möchten Sie den Tour-Rapport ausdrucken?';
$GLOBALS['TL_LANG']['MSC']['writeTourReport'] = 'Möchten Sie den Tourenrapport erstellen/bearbeiten?';
$GLOBALS['TL_LANG']['MSC']['goToPartcipantList'] = 'Möchten Sie zur Teilnehmerliste wechseln?';
$GLOBALS['TL_LANG']['MSC']['goToInvoiceList'] = 'Möchten Sie das Vergütungsformular bearbeiten/erstellen?';

// Backend member dashboard
$GLOBALS['TL_LANG']['MSC']['bmd_yourUpcomingEvents'] = 'Deine nächsten Events';
$GLOBALS['TL_LANG']['MSC']['bmd_howToEditReadonlyProfileData'] = 'Änderungen an Name, Adresse und E-Mail müssen auf der Webseite des SAC Zentralverbandes (https://sac-cas.ch) gemacht werden.';

// Frontend member dashboard write event article frontend module
$GLOBALS['TL_LANG']['ERR']['md_write_event_article_mailAddressNotFound'] = 'Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterle auf der Webseite des Zentralverbands deine E-Mail-Adresse.';
$GLOBALS['TL_LANG']['ERR']['md_write_event_article_eventNotFound'] = 'Event mit ID %s nicht gefunden.';
$GLOBALS['TL_LANG']['ERR']['md_write_event_article_createArticleDeadlineExpired'] = 'Für diesen Event kann kein Bericht mehr erstellt werden. Das Eventdatum liegt bereits zu lange zurück.';
$GLOBALS['TL_LANG']['ERR']['md_write_event_article_writingPermissionDenied'] = 'Du hast keine Berechtigung für diesen Event einen Bericht zu verfassen.';
$GLOBALS['TL_LANG']['ERR']['md_write_event_article_missingImageLegend'] = 'Es fehlen noch eine oder mehrere Bildlegenden oder der Fotografen-Name. Bitte ergänze diese Pflichtangaben, damit der Bericht veröffentlicht werden kann.';
$GLOBALS['TL_LANG']['ERR']['md_write_event_article_uploadDirNotFound'] = 'Bild-Upload-Verzeichnis nicht gefunden.';
$GLOBALS['TL_LANG']['ERR']['md_write_event_article_notSpecified'] = 'keine Angabe';
$GLOBALS['TL_LANG']['ERR']['md_write_event_article_writeSomethingAboutTheEvent'] = 'Bitte schreibe in einigen Sätzen etwas zum Event.';

// Event registration frontend module
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventNotFound'] = 'Event mit ID: %s nicht gefunden.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_onlineRegDisabled'] = 'Der Leiter hat die Online-Anmeldung zu diesem Event deaktiviert. Bitte beachte die Tourenausschreibung.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventFullyBooked'] = 'Dieser Anlass ist ausgebucht. Bitte erkundige dich beim Leiter, ob eine Nachmeldung möglich ist.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventCanceled'] = 'Dieser Anlass wurde abgesagt. Es ist keine Anmeldung möglich.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventDeferred'] = 'Dieser Anlass ist verschoben worden.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_registrationPossibleOn'] = 'Anmeldungen für <strong>"%s"</strong> sind erst ab dem %s möglich.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_registrationDeadlineExpired'] = 'Die Anmeldefrist für diesen Event ist am %s um %s abgelaufen.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_registrationPossible24HoursBeforeEventStart'] = 'Die Anmeldefrist für diesen Event ist abgelaufen. Du kannst dich bis 24 Stunden vor Event-Beginn anmelden. Nimm gegebenenfalls mit dem Leiter Kontakt auf.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventDateOverlapError'] = 'Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem Touren-/Kursleiter auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_mainInstructorNotFound'] = 'Der Hauptleiter mit ID "%s" wurde nicht in der Datenbank gefunden. Bitte nimm persönlich Kontakt mit dem Leiter auf.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_mainInstructorsEmailAddrNotFound'] = 'Dem Hauptleiter mit ID "%s" ist keine gültige E-Mail zugewiesen. Bitte nimm persönlich mit dem Leiter Kontakt auf.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_membersEmailAddrNotFound'] = 'Leider wurde für dieses Mitgliederkonto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlege auf auf der Internetseite des Zentralverbands deine E-Mail-Adresse.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_bookingLimitReaches'] = 'Die maximale Teilnehmerzahl für diesen Event ist bereits erreicht. Wenn du dich trotzdem anmeldest, gelangst du auf die Warteliste und kannst bei Absagen evtl. nachrücken. Du kannst selbstverständlich auch mit dem Leiter Kontakt aufnehmen, um Genaueres zu erfahren.';

// Event registration frontend module form labels
$GLOBALS['TL_LANG']['FORM']['evt_reg_ibanText'] = 'Bitte beachte, dass es sich bei diesem Anlass um einen Event mit Bezahlung durch Vorauskasse handelt. Deine Anmeldung wird erst bestätigt, nachdem der Teilnahmebeitrag bei uns eingegangen ist. Details dazu erhältst du nach der Anmeldung per E-Mail.';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ibanBeneficiary'] = 'Begünstigter';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ticketInfo'] = 'Ich besitze ein/eine';
$GLOBALS['TL_LANG']['FORM']['evt_reg_carInfo'] = 'Ich könnte ein Auto mit ... Plätzen (inkl. Fahrer) stellen';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ahvNumber'] = 'AHV-Nummer';
$GLOBALS['TL_LANG']['FORM']['evt_reg_mobile'] = 'Mobilnummer';
$GLOBALS['TL_LANG']['FORM']['evt_reg_emergencyPhone'] = 'Notfalltelefonnummer / In Notfällen zu kontaktieren';
$GLOBALS['TL_LANG']['FORM']['evt_reg_emergencyPhoneName'] = 'Name und Bezug der dir anvertrauten Kontaktperson für Notfälle';
$GLOBALS['TL_LANG']['FORM']['evt_reg_notes'] = 'Anmerkungen / Erfahrungen / Referenztouren';
$GLOBALS['TL_LANG']['FORM']['evt_reg_foodHabits'] = 'Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)';
$GLOBALS['TL_LANG']['FORM']['evt_reg_agb'] = 'Ich akzeptiere <a href="#" data-bs-toggle="modal" data-bs-target="#agbModal">das Kurs- und Tourenreglement.</a>';
$GLOBALS['TL_LANG']['FORM']['evt_reg_submit'] = 'Für Event anmelden';

// Booking states/Subscription states
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionLevel::SUBSCRIPTION_NOT_CONFIRMED] = 'Anmeldeanfrage unbeantwortet';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED] = 'Anmeldung bestätigt';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionLevel::SUBSCRIPTION_REJECTED] = 'Anmeldeanfrage abgelehnt';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED] = 'Auf Warteliste';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionLevel::USER_HAS_UNSUBSCRIBED] = 'Anmeldung storniert';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionLevel::SUBSCRIPTION_STATE_UNDEFINED] = 'Anmelde-Status unbekannt';

// Event registration frontend module form explanations
$GLOBALS['TL_LANG']['FORM']['evt_reg_mobileExpl'] = 'Das Feld "Mobilnummer" ist kein Pflichtfeld und kann leer gelassen werden. Damit der Leiter dich aber während der Tour bei Zwischenfällen erreichen kann, ist es für ihn sehr hilfreich, deine Mobilnummer zu kennen. Selbstverständlich werden diese Angaben vertraulich behandelt und nicht an Dritte weitergegeben.';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ahvExpl'] = 'Sämtliche Daten werden lediglich für interne Zwecke verwendet. Die AHV-Nummer wird ausschliesslich für die Abrechnung oder Rückforderung von J+S-Geldern verwendet. Deine persönlichen Daten werden vertraulich behandelt. Eine Weitergabe an Drittorganisationen ist ausgeschlossen.';
$GLOBALS['TL_LANG']['FORM']['evt_reg_notesExpl'] = 'Bitte beschreibe und beantworte in wenigen Worten die erforderlichen Angaben für den Event wie: <ul><li>dein <strong>Leistungsniveau und Erfahrungen</strong></li><li>bereits <strong>absolvierte Referenztouren</strong> in den letzten paar Jahren (inkl. Angabe mit/ohne Bergführer/in)</li><li><strong>zusätzlich verlangte Angaben</strong> in den Anmeldebestimmungen</li><li>und <strong>weitere Anmerkungen, Wichtiges etc.</strong> nach Bedarf</li></ul>';

// Miscellaneous
$GLOBALS['TL_LANG']['MSC']['published'] = 'veröffentlicht';
$GLOBALS['TL_LANG']['MSC']['unpublished'] = 'unveröffentlicht';
$GLOBALS['TL_LANG']['MSC']['notSpecified'] = 'keine Angabe';

// Meta wizard
$GLOBALS['TL_LANG']['MSC']['aw_photographer'] = 'Photograph';
