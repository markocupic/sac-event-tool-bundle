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

use Contao\System;
use Markocupic\SacEventToolBundle\Config\EventMountainGuide;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Controller\ContentElement\UserPortraitController;
use Markocupic\SacEventToolBundle\Controller\ContentElement\UserPortraitListController;

// Content elements
$GLOBALS['TL_LANG']['CTE'][UserPortraitController::TYPE] = ['SAC-User-Portrait'];
$GLOBALS['TL_LANG']['CTE'][UserPortraitListController::TYPE] = ['SAC-User-Portrait-Liste'];

// Override defaults
$request = System::getContainer()->get('request_stack')->getCurrentRequest();

if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isFrontendRequest($request)) {
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

// tl_calendar_events_instructor_invoice
$GLOBALS['TL_LANG']['ERR']['actionNotSupported'] = 'Diese Aktion wird nicht unterstützt.';

// Events
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_1'] = 'Freie Plätze!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_2'] = 'Anmeldefrist für Event ist abgelaufen!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_3'] = 'Event ausgebucht!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_4'] = 'Event abgesagt!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_5'] = 'Anmelden noch nicht möglich!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_6'] = 'Event verschoben! Neues Startdatum: %s. Anmeldung nur via Leitende.';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_7'] = 'Keine Online-Anmeldung möglich!';
$GLOBALS['TL_LANG']['MSC']['calendar_events']['event_status_8'] = 'Max. Teilnehmerzahl erreicht. Anmeldung auf Warteliste möglich.';

$GLOBALS['TL_LANG']['MSC']['calendar_events'][EventState::STATE_FULLY_BOOKED] = 'Event ausgebucht!';
$GLOBALS['TL_LANG']['MSC']['calendar_events'][EventState::STATE_CANCELED] = 'Event abgesagt!';
$GLOBALS['TL_LANG']['MSC']['calendar_events'][EventState::STATE_RESCHEDULED] = 'Event verschoben! Neuer Termin: %s';

// References
$GLOBALS['TL_LANG']['MSC']['courseLevel'][1] = 'Einführungskurs';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][2] = 'Grundstufe';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][3] = 'Fortbildungskurs';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][4] = 'Tourenleiter Fortbildungskurs';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][5] = 'Tourenleiter Fortbildungskurs';
$GLOBALS['TL_LANG']['MSC'][EventType::COURSE] = 'Kurs';
$GLOBALS['TL_LANG']['MSC'][EventType::COURSE.'_short'] = 'Kurs';
$GLOBALS['TL_LANG']['MSC'][EventType::TOUR] = 'Tour';
$GLOBALS['TL_LANG']['MSC'][EventType::TOUR.'_short'] = 'Tour';
$GLOBALS['TL_LANG']['MSC'][EventType::LAST_MINUTE_TOUR] = 'Last Minute Tour';
$GLOBALS['TL_LANG']['MSC'][EventType::LAST_MINUTE_TOUR.'_short'] = 'Last Minute Tour';
$GLOBALS['TL_LANG']['MSC'][EventType::GENERAL_EVENT] = 'Veranstaltung (Fitnesstrainings, Skiturnen, Kultur, Vorträge + sektionsübergreifende Events)';
$GLOBALS['TL_LANG']['MSC'][EventType::GENERAL_EVENT.'_short'] = 'Veranstaltung';
$GLOBALS['TL_LANG']['MSC']['event_mountainguide'][EventMountainGuide::NO_MOUNTAIN_GUIDE] = '«ohne Bergführer/in» und «ohne Bergführerangebot»';
$GLOBALS['TL_LANG']['MSC']['event_mountainguide'][EventMountainGuide::WITH_MOUNTAIN_GUIDE] = '«mit Bergführer/in» und «ohne Bergführerangebot»';
$GLOBALS['TL_LANG']['MSC']['event_mountainguide'][EventMountainGuide::WITH_MOUNTAIN_GUIDE_OFFER] = '«mit Bergführer/in» und «mit Bergführerangebot»';

// Buttons
$GLOBALS['TL_LANG']['MSC']['sendEmail'] = 'E-Mail senden';
$GLOBALS['TL_LANG']['MSC']['plus1year'] = '+1 Jahr';
$GLOBALS['TL_LANG']['MSC']['minus1year'] = '-1 Jahr';
$GLOBALS['TL_LANG']['MSC']['plusOneReleaseLevel'] = 'Freigabestufe ++';
$GLOBALS['TL_LANG']['MSC']['minusOneReleaseLevel'] = 'Freigabestufe --';
$GLOBALS['TL_LANG']['MSC']['printInstructorInvoiceButton'] = 'Tourrapport und Vergütungsformular drucken und einreichen';
$GLOBALS['TL_LANG']['MSC']['writeTourReportButton'] = 'Tourrapport bearbeiten';
$GLOBALS['TL_LANG']['MSC']['backToEvent'] = 'Zurück zum Event';
$GLOBALS['TL_LANG']['MSC']['onloadCallbackExportCalendar'] = 'Events exportieren';

// Confirm messages
$GLOBALS['TL_LANG']['MSC']['plus1yearConfirm'] = 'Möchten Sie wirklich die Eventzeitpunkte aller Events in diesem Kalender um 1 Jahr nach vorne schieben?';
$GLOBALS['TL_LANG']['MSC']['minus1yearConfirm'] = 'Möchten Sie wirklich die Eventzeitpunkte aller Events in diesem Kalender um 1 Jahr nach vorne schieben?';
$GLOBALS['TL_LANG']['MSC']['plusOneReleaseLevelConfirm'] = 'Möchten Sie wirklich alle hier gelisteten Events um eine Freigabestufe erhöhen?';
$GLOBALS['TL_LANG']['MSC']['minusOneReleaseLevelConfirm'] = 'Möchten Sie wirklich alle hier gelisteten Events um eine Freigabestufe vermindern?';
$GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'] = 'Die Freigabestufe für Event mit ID %s wurde auf Level %s gesetzt.';
$GLOBALS['TL_LANG']['MSC']['publishedEvent'] = 'Der Event mit ID %s wurde veröffentlicht.';
$GLOBALS['TL_LANG']['MSC']['unpublishedEvent'] = 'Der Event mit ID %s ist nicht mehr veröffentlicht.';
$GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck'] = 'Das Datum für den Anfang des Anmeldezeitraums musste angepasst werden. Bitte kontrollieren Sie dieses nochmals.';
$GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck'] = 'Das Datum für das Ende des Anmeldezeitraums musste angepasst werden. Bitte kontrollieren Sie dieses nochmals.';
$GLOBALS['TL_LANG']['MSC']['writeTourReport'] = 'Möchten Sie den Tourrapport erstellen/bearbeiten?';
$GLOBALS['TL_LANG']['MSC']['goToPartcipantList'] = 'Möchten Sie zur Teilnehmerliste wechseln?';
$GLOBALS['TL_LANG']['MSC']['goToInvoiceList'] = 'Möchten Sie das Vergütungsformular bearbeiten/erstellen?';

// tl_calendar_events error messages
$GLOBALS['TL_LANG']['ERR']['invalidEventDurationInfo'] = 'Die Event-Dauer stimmt nicht mit der Anzahl Event-Daten überein. Setzen Sie für jeden Tag des Events eine Datumszeile oder korrigieren Sie die Event-Dauer.';
$GLOBALS['TL_LANG']['ERR']['deleteEventMembersBeforeDeleteEvent'] = 'Für den Event mit ID %s sind Anmeldungen vorhanden. Bitte löschen Sie diese, bevor Sie den Event selber löschen.';
$GLOBALS['TL_LANG']['ERR']['setEvtRelLevelForSelectAll'] = 'Mehrere bearbeiten" nur möglich, wenn ein Freigabestufen-Filter gesetzt wurde.';
$GLOBALS['TL_LANG']['ERR']['missingPermissionsToEditEvent'] = 'Sie haben nicht die erforderliche Berechtigung den Event mit ID %s zu bearbeiten.';
$GLOBALS['TL_LANG']['ERR']['missingPermissionsToDeleteEvent'] = 'Sie haben nicht die erforderliche Berechtigung den Event mit ID %s zu löschen.';
$GLOBALS['TL_LANG']['ERR']['missingPermissionsToPublishOrUnpublishEvent'] = 'Sie haben nicht die erforderliche Berechtigung den Event mit ID %s zu veröffentlichen.';
$GLOBALS['TL_LANG']['ERR']['missingPermissionsToCutEvent'] = 'Sie haben nicht die erforderliche Berechtigung den Event mit ID %d zu verschieben.';

// Backend home screen dashboard
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_sacEvents'] = 'SAC Events';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_yourUpcomingEvents'] = 'Ihre nächsten Events';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_yourPastEvents'] = 'Ihre vergangenen Events';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_howToEditReadonlyProfileData'] = 'Änderungen an Name, Adresse, Telefon und E-Mail müssen auf der Webseite des SAC Zentralverbandes (https://sac-cas.ch) gemacht werden.';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_editEvent'] = 'Event bearbeiten';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_editReleaseLevel'] = 'Freigabestufe ändern';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_eventListing'] = 'Event-Liste';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_livePreview'] = 'Event Vorschau';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_printReport'] = 'Rapporte drucken und einreichen';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_printReportDisabled'] = 'Rapporte drucken nicht verfügbar';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_registrationList'] = 'Event Registrierungen';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_sendEmail'] = 'E-Mail versenden';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_sendEmailDisabled'] = 'E-Mail versenden nicht verfügbar';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_writeReport'] = 'Tourrapport erfassen';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_editReport'] = 'Tourrapport bearbeiten';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_writeReportDisabled'] = 'Tourrapport erfassen nicht verfügbar';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_guidesAndTutorials'] = 'Anleitungen und Tutorials';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_guidesAndTutorialsText'] = 'Die Seite "Anleitungen und Tutorials" beim Menüpunkt "Service" im Frontend/Website unterstützt Sie bei der Verwendung des SAC Event-Tools (Backend/Contao).';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_noUpcomingEventsDetected'] = 'In nächster Zeit stehen bei Ihnen keine Events an, wo Sie eine Leitungsfunktion ausüben.';
$GLOBALS['TL_LANG']['MSC']['bhs_dashb_noPastEventsDetected'] = 'Es wurden keine Events gefunden, bei denen Sie in letzter Zeit eine Leitungsfunktion ausgeübt haben.';

// Event registration frontend module
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventNotFound'] = 'Event mit ID: %s nicht gefunden.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventNotPublishedYet'] = 'Der Event "%s" ist nicht veröffentlicht und eine Online-Anmeldung ist (noch) nicht möglich.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventReleaseLevelPolicyDoesNotAllowRegistrations'] = 'Die Veröffentlichungsstufe für Event "%s" erlaubt Online-Anmeldungen nicht.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_onlineRegDisabled'] = 'Der/Die Leiter/in hat die Online-Anmeldung zu diesem Event deaktiviert. Bitte beachte die Tourenausschreibung.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventFullyBooked'] = 'Dieser Anlass ist ausgebucht. Bitte erkundige dich beim Leiter/in, ob eine Nachmeldung möglich ist.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventCanceled'] = 'Dieser Anlass wurde <strong>abgesagt</strong>. Es ist keine Anmeldung möglich.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventDeferred'] = 'Dieser Anlass ist verschoben worden.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_registrationPossibleOn'] = 'Anmeldungen für <strong>"%s"</strong> sind erst ab dem %s möglich.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_registrationDeadlineExpired'] = 'Die Anmeldefrist für diesen Event ist am %s um %s abgelaufen.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_registrationPossible24HoursBeforeEventStart'] = 'Die Anmeldefrist für diesen Event ist abgelaufen. Du kannst dich bis 24 Stunden vor Event-Beginn anmelden. Nimm gegebenenfalls mit dem/der Leiter/in Kontakt auf.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_eventDateOverlapError'] = 'Die Anmeldung zu diesem Event ist nicht möglich, da die Event-Daten sich mit den Daten eines anderen Events überschneiden, wo deine Teilnahme bereits bestätigt ist. Bitte nimm persönlich Kontakt mit dem/der Touren-/Kursleiter/in auf, falls du der Ansicht bist, dass keine zeitliche Überschneidung vorliegt und deine Teilnahme an beiden Events möglich ist.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_mainInstructorNotFound'] = 'Der Hauptleiter mit ID "%s" wurde nicht in der Datenbank gefunden. Bitte nimm persönlich Kontakt mit dem/der Leiter/in auf.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_mainInstructorsEmailAddrNotFound'] = 'Dem Hauptleiter mit ID "%s" ist keine gültige E-Mail zugewiesen. Bitte nimm persönlich mit dem/der Leiter/in Kontakt auf.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_membersEmailAddrNotFound'] = 'Leider wurde für dieses Mitgliederkonto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlege auf auf der Internetseite des Zentralverbands deine E-Mail-Adresse.';
$GLOBALS['TL_LANG']['ERR']['evt_reg_bookingLimitReaches'] = 'Die maximale Teilnehmerzahl für diesen Event ist bereits erreicht. Wenn du dich trotzdem anmeldest, gelangst du auf die Warteliste und kannst bei Absagen evtl. nachrücken. Du kannst selbstverständlich auch mit dem/der Leiter/in Kontakt aufnehmen, um Genaueres zu erfahren.';

// Event registration frontend module form labels
$GLOBALS['TL_LANG']['FORM']['evt_reg_ibanText'] = 'Bitte beachte, dass es sich bei diesem Anlass um einen Event mit Bezahlung durch Vorauskasse handelt. Deine Anmeldung wird erst bestätigt, nachdem der Teilnahmebeitrag bei uns eingegangen ist. Details dazu erhältst du nach der Anmeldung per E-Mail.';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ibanBeneficiary'] = 'Begünstigte/r';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ticketInfo'] = 'Ich besitze ein/eine';
$GLOBALS['TL_LANG']['FORM']['evt_reg_blankLabelTicketInfo'] = 'bitte auswählen';
$GLOBALS['TL_LANG']['FORM']['evt_reg_carInfo'] = 'Ich könnte ein Auto mit ... Plätzen (inkl. Fahrer/in) stellen';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ahvNumber'] = 'AHV-Nummer';
$GLOBALS['TL_LANG']['FORM']['evt_reg_mobile'] = 'Mobilnummer';
$GLOBALS['TL_LANG']['FORM']['evt_reg_emergencyPhone'] = 'Notfalltelefonnummer / In Notfällen zu kontaktieren';
$GLOBALS['TL_LANG']['FORM']['evt_reg_emergencyPhoneName'] = 'Name und Bezug der dir anvertrauten Kontaktperson für Notfälle';
$GLOBALS['TL_LANG']['FORM']['evt_reg_notes'] = 'Anmerkungen / Erfahrungen / Referenztouren';
$GLOBALS['TL_LANG']['FORM']['evt_reg_foodHabits'] = 'Essgewohnheiten (Vegetarier/in, Laktoseintoleranz, etc.)';
$GLOBALS['TL_LANG']['FORM']['evt_reg_agb'] = 'Ich akzeptiere <a href="#" data-bs-toggle="modal" data-bs-target="#courseAndTourRegulationsModal">das Kurs- und Tourenreglement.</a>*';
$GLOBALS['TL_LANG']['FORM']['evt_reg_hasAcceptedPrivacyRules'] = 'Ich habe die <a href="#" data-bs-toggle="modal" data-bs-target="#hasAcceptedPrivacyRulesModal">Hinweise zum Datenschutz und zu meinen Persönlichkeitsrechten</a> zur Kenntnis genommen und bin damit einverstanden.*';
$GLOBALS['TL_LANG']['FORM']['evt_reg_submit'] = 'Für Event anmelden';

// Send tour rapport notification module
$GLOBALS['TL_LANG']['MSC']['evt_strn_successfullySendNotification'] = 'Tourrapport und Vergütungsformular erfolgreich versandt an: "%s" CC: "%s".';
$GLOBALS['TL_LANG']['MSC']['evt_strn_title'] = 'Versand Tourrapport & Vergütungsformular';
$GLOBALS['TL_LANG']['MSC']['evt_strn_emailRecipients'] = 'E-Mail-Empfänger (kommasepariert)';
$GLOBALS['TL_LANG']['MSC']['evt_strn_emailRecipientsPlaceholder'] = 'E-Mail-Adressen der Empfänger (Kasse und Tourenchef) kommasepariert eingeben.';
$GLOBALS['TL_LANG']['MSC']['evt_strn_emailSubject'] = 'Betreff';
$GLOBALS['TL_LANG']['MSC']['evt_strn_emailText'] = 'Text';
$GLOBALS['TL_LANG']['MSC']['evt_strn_sendEmail'] = 'E-Mail absenden';
$GLOBALS['TL_LANG']['ERR']['evt_strn_linkExpired'] = 'Der Link ist abgelaufen. Laden Sie die Seite neu und probieren Sie erneut.';
$GLOBALS['TL_LANG']['ERR']['evt_strn_sendNotificationFailed'] = 'Die E-Mail konnte nicht versandt werden, weil es zu einem unerwarteten Fehler gekommen ist. Bitte probieren Sie den selben Vorgang nochmals auszuführen. Kontaktieren Sie bitte den Administrator dieser Webseite, sollte der Fehler erneut auftreten.';
$GLOBALS['TL_LANG']['ERR']['evt_strn_cloudconvConversionCreditUsedUp'] = 'Die E-Mail konnte nicht versandt werden, weil bei der DOCX zu PDF Konvertierung von "%s" ein Fehler aufgetreten ist. Die Anzahl der täglich erlaubten Konvertierungen wurde überschritten.';
$GLOBALS['TL_LANG']['ERR']['evt_strn_cloudconvUnexpectedError'] = 'Die E-Mail konnte nicht versandt werden, weil bei der DOCX zu PDF Konvertierung von "%s" ein unerwarteter Fehler aufgetreten ist. Probieren Sie den selben Vorgang noch einmal durchzuführen. Kontaktieren Sie bitte den Administrator dieser Webseite, sollte der Fehler erneut auftreten.';
$GLOBALS['TL_LANG']['MSC']['evt_strn_multiFormSubmitWarning'] = 'Sie haben diese Benachrichtigung bereits %dx versendet. Das letzte Mal am %s. Möchten Sie die Benachrichtigung wirklich ein weiteres Mal versenden?';

// Booking states/Subscription states
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED] = 'Anmeldeanfrage unbeantwortet';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionState::SUBSCRIPTION_ACCEPTED] = 'Anmeldeanfrage bestätigt';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionState::SUBSCRIPTION_REFUSED] = 'Anmeldeanfrage abgelehnt';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST] = 'Anmeldeanfrage auf Warteliste';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionState::USER_HAS_UNSUBSCRIBED] = 'Anmeldeanfrage storniert';
$GLOBALS['TL_LANG']['MSC'][EventSubscriptionState::SUBSCRIPTION_STATE_UNDEFINED] = 'Status der Anmeldeanfrage unbekannt';

// Event registration frontend module form explanations
$GLOBALS['TL_LANG']['FORM']['evt_reg_ffield_expl_mobile'] = 'Das Feld "Mobilnummer" ist kein Pflichtfeld und kann leer gelassen werden. Damit der/die Leiter/in dich aber während der Tour bei Zwischenfällen erreichen kann, ist es für ihn sehr hilfreich, deine Mobilnummer zu kennen. Selbstverständlich werden diese Angaben vertraulich behandelt und nicht an Dritte weitergegeben.';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ffield_expl_ahvNumber'] = 'Sämtliche Daten werden lediglich für interne Zwecke verwendet. Die AHV-Nummer wird ausschliesslich für die Abrechnung oder Rückforderung von J+S-Geldern verwendet. Deine persönlichen Daten werden vertraulich behandelt. Eine Weitergabe an Drittorganisationen ist ausgeschlossen.';
$GLOBALS['TL_LANG']['FORM']['evt_reg_ffield_expl_notes'] = 'Bitte beschreibe und beantworte in wenigen Worten die erforderlichen Angaben für den Event wie: <ul class="list-bullet ps-3"><li>dein <strong>Leistungsniveau und Erfahrungen</strong></li><li>bereits <strong>absolvierte Referenztouren</strong> in den letzten paar Jahren (inkl. Angabe mit/ohne Bergführer/in)</li><li><strong>zusätzlich verlangte Angaben</strong> in den Anmeldebestimmungen</li><li>und <strong>weitere Anmerkungen, Wichtiges etc.</strong> nach Bedarf</li></ul>';

// Event Instructor Invoice
$GLOBALS['TL_LANG']['ERR']['invalidNumberOfPrivateArrivals'] = 'Die Zahl der privat angereisten Teilnehmer (%s) ist nicht zulässig und übersteigt die Gesamtanzahl der Teilnehmer und Leiter (%s). Bitte korrigieren Sie diese Zahl im Vergütungsformular.';

// Miscellaneous
$GLOBALS['TL_LANG']['MSC']['published'] = 'veröffentlicht';
$GLOBALS['TL_LANG']['MSC']['unpublished'] = 'unveröffentlicht';

// Meta wizard
$GLOBALS['TL_LANG']['MSC']['aw_photographer'] = 'Photograph';

// Backend route contao/event_participant_email
$GLOBALS['TL_LANG']['MSC']['evt_epe_accessDenied'] = 'Zugriff zur App verweigert. Bitte aktualisieren Sie die Seite und rufen Sie die App erneut auf.';
$GLOBALS['TL_LANG']['MSC']['evt_epe_emailRecipients'] = 'E-Mail Empfänger';
$GLOBALS['TL_LANG']['MSC']['evt_epe_emailRecipientsCc'] = 'Weitere E-Mail Empfänger (kommasepariert)';
$GLOBALS['TL_LANG']['MSC']['evt_epe_emailSubject'] = 'E-Mail Betreff';
$GLOBALS['TL_LANG']['MSC']['evt_epe_emailText'] = 'E-Mail Text';
$GLOBALS['TL_LANG']['MSC']['evt_epe_sendEmail'] = 'E-Mail versenden';
$GLOBALS['TL_LANG']['MSC']['evt_epe_emailAttachments'] = 'Dateianhänge';
$GLOBALS['TL_LANG']['MSC']['evt_epe_deleteAttachment'] = 'Anhang entfernen';
$GLOBALS['TL_LANG']['MSC']['evt_epe_maxFilesizeExceeded'] = 'Die maximale Dateigrösse von für Dateianhänge beträgt %s MB.';
$GLOBALS['TL_LANG']['MSC']['evt_epe_notAllowedFileExtension'] = 'Die Datei besitzt eine unerlaubte Dateiendung. Erlaubt sind Dateien mit der Endung: %s.';
$GLOBALS['TL_LANG']['MSC']['evt_epe_fileNotSubmitted'] = 'Es wurde keine Datei hochgeladen.';
$GLOBALS['TL_LANG']['MSC']['evt_epe_appTitle'] = 'E-Mails an TN/TL von "%s" versenden';
$GLOBALS['TL_LANG']['MSC']['evt_epe_sendingEmailFailed'] = 'Die Email konnte nicht versendet werden. Bitte überprüfen Sie Ihre Angaben.';
$GLOBALS['TL_LANG']['MSC']['evt_epe_generalAjaxError'] = 'Der Befehl konnte nicht ausgeführt werden. Bitte kontrollieren Sie die Verbindung oder laden Sie die Seite neu.';
$GLOBALS['TL_LANG']['MSC']['evt_epe_eventNotFound'] = 'Event mit ID %s konnte nicht gefunden werden.';
$GLOBALS['TL_LANG']['MSC']['evt_epe_emailSentToEventMembers'] = 'Ihre E-Mail wurde erfolgreich versendet.';
$GLOBALS['TL_LANG']['MSC']['evt_epe_fileUploadedSuccessful'] = 'Datei erfolgreich hochgeladen.';
$GLOBALS['TL_LANG']['MSC']['evt_epe_selectAll'] = 'alle anwählen';
$GLOBALS['TL_LANG']['MSC']['evt_epe_unselectAll'] = 'alle abwählen';
$GLOBALS['TL_LANG']['MSC']['evt_epe_youWillReceiveACopyOfThisMessage'] = 'Sie erhalten automatisch eine Kopie dieser Nachricht.';
