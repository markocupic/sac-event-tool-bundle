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
$GLOBALS['TL_LANG']['CTE']['userPortraitList'] = array('SAC-User-Portrait-Liste');
$GLOBALS['TL_LANG']['CTE']['sac_calendar_newsletter'] = array('SAC-Events-Elemente');
$GLOBALS['TL_LANG']['CTE']['calendar_newsletter'] = array('SAC-Events als Cleverreach Template-Vorlage herunterladen');

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
$GLOBALS['TL_LANG']['MSC']['courseLevel'][1] = 'Einf&uuml;hrungskurs';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][2] = 'Grundstufe';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][3] = 'Fortbildungskurs';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][4] = 'Tourenleiter Fortbildungskurs';
$GLOBALS['TL_LANG']['MSC']['courseLevel'][5] = 'Tourenleiter Fortbildungskurs';
$GLOBALS['TL_LANG']['MSC']['course'] = 'Kurs';
$GLOBALS['TL_LANG']['MSC']['tour'] = 'Tour';
$GLOBALS['TL_LANG']['MSC']['lastMinuteTour'] = 'Last Minute Tour';
$GLOBALS['TL_LANG']['MSC']['generalEvent'] = 'Veranstaltung (Fitnesstrainings, Skiturnen, Kultur, Vorträge + sektionsübergreifende Events)';

// Buttons
$GLOBALS['TL_LANG']['MSC']['downloadEventMemberList'] = 'Teilnehmerliste downloaden';
$GLOBALS['TL_LANG']['MSC']['sendEmail'] = 'E-Mail senden';
$GLOBALS['TL_LANG']['MSC']['plus1year'] = '+1 Jahr';
$GLOBALS['TL_LANG']['MSC']['minus1year'] = '-1 Jahr';
$GLOBALS['TL_LANG']['MSC']['plusOneReleaseLevel'] = 'Freigabestufe ++';
$GLOBALS['TL_LANG']['MSC']['minusOneReleaseLevel'] = 'Freigabestufe --';
$GLOBALS['TL_LANG']['MSC']['printInstructorInvoiceButton'] = 'Verg&uuml;tungsformular';
$GLOBALS['TL_LANG']['MSC']['writeTourReportButton'] = 'Tourenrapport';
$GLOBALS['TL_LANG']['MSC']['backToEvent'] = 'Zur&uuml;ck zum Event';
$GLOBALS['TL_LANG']['MSC']['onloadCallbackExportCalendar'] = 'Events exportieren';

// Confirm messages
$GLOBALS['TL_LANG']['MSC']['plus1yearConfirm'] = 'M&ouml;chten Sie wirklich die Eventzeitpunkte aller Events in diesem Kalender um 1 Jahr nach vorne schieben?';
$GLOBALS['TL_LANG']['MSC']['minus1yearConfirm'] = 'M&ouml;chten Sie wirklich die Eventzeitpunkte aller Events in diesem Kalender um 1 Jahr nach vorne schieben?';
$GLOBALS['TL_LANG']['MSC']['plusOneReleaseLevelConfirm'] = 'M&ouml;chten Sie wirklich alle hier gelisteten Events um eine Freigabestufe erh&ouml;hen?';
$GLOBALS['TL_LANG']['MSC']['minusOneReleaseLevelConfirm'] = 'M&ouml;chten Sie wirklich alle hier gelisteten Events um eine Freigabestufe vermindern?';
$GLOBALS['TL_LANG']['MSC']['deleteEventMembersBeforeDeleteEvent'] = 'F&uuml;r den Event mit ID %s sind Anmeldungen vorhanden. Bitte l&ouml;schen Sie diese bevor Sie den Event selber l&ouml;schen.';
$GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'] = 'Die Freigabestufe f&uuml;r Event mit ID %s wurde auf Level %s gesetzt.';
$GLOBALS['TL_LANG']['MSC']['publishedEvent'] = 'Der Event mit ID %s wurde ver&ouml;ffentlicht.';
$GLOBALS['TL_LANG']['MSC']['unpublishedEvent'] = 'Der Event mit ID %s ist nicht mehr ver&ouml;ffentlicht.';
$GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck'] = 'Das Datum f&uuml;r den Anfang des Anmeldezeitraums musste angepasst werden. Bitte kontrollieren Sie dieses nochmals.';
$GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck'] = 'Das Datum f&uuml;r das Ende des Anmeldezeitraums musste angepasst werden. Bitte kontrollieren Sie dieses nochmals.';
$GLOBALS['TL_LANG']['MSC']['missingPermissionsToEditEvent'] = 'Sie haben nicht die erforderlichen Berechtigungen den Datensatz mit ID %s zu bearbeiten.';
$GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'] = 'Sie haben nicht die erforderlichen Berechtigungen den Datensatz mit ID %s zu l&ouml;schen.';
$GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'] = 'Sie haben nicht die erforderlichen Berechtigungen den Datensatz mit ID %s zu ver&ouml;ffentlichen.';
$GLOBALS['TL_LANG']['MSC']['generateInvoice'] = 'M&ouml;chten Sie das Verg&uuml;tungsformular ausdrucken?';
$GLOBALS['TL_LANG']['MSC']['writeTourReport'] = 'M&ouml;chten Sie den Tourenrapport erstellen/bearbeiten?';
$GLOBALS['TL_LANG']['MSC']['goToPartcipantList'] = 'M&ouml;chten Sie zur Teilnehmerliste wechseln?';
$GLOBALS['TL_LANG']['MSC']['goToInvoiceList'] = 'M&ouml;chten Sie das Verg&uuml;tungsformular bearbeiten/erstellen?';

// Miscelaneous
$GLOBALS['TL_LANG']['MSC']['published'] = 'ver&ouml;ffentlicht';
$GLOBALS['TL_LANG']['MSC']['unpublished'] = 'unver&ouml;ffentlicht';

// Meta wizard
$GLOBALS['TL_LANG']['MSC']['aw_photographer'] = 'Photograph';

// mod_login
$GLOBALS['TL_LANG']['ERR']['memberAccountNotActivated'] = 'Anmeldeversuch gescheitert! Das Benutzerkonto wurde noch nicht aktiviert. Bitte aktiviere einmalig dein Konto und lege dein Passwort fest.';
$GLOBALS['TL_LANG']['ERR']['memberAccountNotFound'] = 'Anmeldeversuch gescheitert! Zur Mitgliedernummer "%s" wurde kein Mitgliederkonto gefunden. Bitte probiere es nochmals.';

// eventToolActivateMemberAccount
$GLOBALS['TL_LANG']['MSC']['activateMemberAccount_sacMemberId'] = 'SAC Mitgliedernummer';
$GLOBALS['TL_LANG']['MSC']['activateMemberAccount_email'] = 'Deine beim <span class="text-danger">SAC in Bern</span> registrierte E-Mail-Adresse';
$GLOBALS['TL_LANG']['MSC']['activateMemberAccount_dateOfBirth'] = 'Dein Geburtsdatum (%s)';
$GLOBALS['TL_LANG']['MSC']['activateMemberAccount_startActivationProcess'] = 'Aktivierung starten';
$GLOBALS['TL_LANG']['MSC']['activateMemberAccount_agb'] = 'Ich akzeptiere die %sallg. Datenschutzrichtlinien.%s';
$GLOBALS['TL_LANG']['MSC']['activateMemberAccount_pleaseEnterTheActivationCode'] = 'Aktivierungscode eingeben';
$GLOBALS['TL_LANG']['MSC']['activateMemberAccount_proceedActivationProcess'] = 'Mit dem Aktivierungsprozess fortfahren';
$GLOBALS['TL_LANG']['MSC']['activateMemberAccount_pleaseEnterPassword'] = 'Passwort für dein Mitgliederkonto festlegen';
$GLOBALS['TL_LANG']['MSC']['activateMemberAccount_activateMemberAccount'] = 'Mitgliederkonto aktivieren';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_couldNotAssignUserToSacMemberId'] = 'Für die eingegebene Mitgliedernummer %s konnte kein Benutzer gefunden werden.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sacMemberIdAndDateOfBirthDoNotMatch'] = 'Mitgliedernummer und Geburtsdatum stimmen nicht überein.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sacMemberEmailNotRegistered'] = 'Du hast beim SAC noch keine E-Mail-Adresse hinterlegt. Die Kontoaktivierung ist so nicht möglich. Bitte hinterlege auf der Webseite des SAC Zentralverbands deine E-Mail-Adresse <a href=\"https://sac-cas.ch\">LINK</a>. Bitte beachte, dass im Minimum 1-2 Tage vergehen, bis diese Änderung auf unserer Webseite wirksam wird, und du einen erneuten Aktivierungsversuch starten kannst.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sacMemberIdAndEmailDoNotMatch'] = 'Mitgliedernummer und E-Mail-Adresse stimmen nicht &uuml;berein.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountWithThisSacMemberIdIsAllreadyRegistered'] = 'Das Konto mit der eingegebenen Mitgliedernummer %s wurde bereits aktiviert.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountWithThisSacMemberIdHasBeendDeactivatedAndIsNoMoreValid'] = 'Das Konto mit der eingegebenen Mitgliedernummer %s ist deaktiviert und nicht mehr g&uuml;ltig.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_couldNotTerminateActivationProcess'] = 'Der Aktivierungsprozess konnte nicht abgeschlossen werden. Bitte probiere es nochmals oder nimm mit der Geschäftsstelle Kontakt auf.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountActivationStoppedAccountIsDeactivated'] = 'Es ist ein Fehler aufgetreten. Dein Mitgliederkonto ist deaktiviert. Bitte nimm gegebenenfalls mit der Geschäftsstelle Kontakt auf.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_accountActivationStoppedInvalidActivationCodeAndTooMuchTries'] = 'Ungültiger Aktivierungscode und zu viele Anzahl ungültiger Versuche. Bitte starte den Aktivierungsprozess von vorne. %sAktivierungsprozess neu starten%s';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_invalidActivationCode'] = 'Ungültiger Aktivierungscode. Bitte erneut versuchen.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_activationCodeExpired'] = 'Der Aktivierungscode ist abgelaufen. Bitte starte den Aktivierungsprozess neu.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sessionExpired'] = 'Es ist ein Fehler aufgetreten. Die Session ist abgelaufen.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_noValidNotificationSelected'] = 'Vom Administrator wurde keine gültige Benachrichtigung ausgewählt. Kontaktiere bitte die Geschäftsstelle.';
$GLOBALS['TL_LANG']['ERR']['activateMemberAccount_sessionExpiredPleaseTestartProcess'] = 'Leider ist die Session abgelaufen. Starte den Aktivierungsprozess von vorne.<br><a href="%s">Aktivierungsprozess neu starten</a>';





