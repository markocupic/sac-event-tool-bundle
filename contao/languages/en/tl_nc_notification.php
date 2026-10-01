<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

// Notification groups
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['sac_event_tool'] = 'SAC Event Tool';

// Notification types
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['onchange_state_of_subscription'] = ['Benachrichtigung bei Änderung des Event-Anmeldestatus', 'Dieser Benachrichtigungstyp wird versandt, nachdem sich der Anmeldestatus geändert hat.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['event_registration'] = ['Benachrichtigung nach dem Absenden des Event-Buchungsformulars', 'Dieser Benachrichtigungstyp wird versandt, nachdem eine Online Anmeldung eingegangen ist.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['event_deregistration'] = ['Benachrichtigung bei Event-Stornierung', 'Dieser Benachrichtigungstyp wird bei der Stornierung einer Event-Teilnahme durch den Teilnehmer versandt.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['event_reminder'] = ['Event-Erinnerung vor Event-Start', 'Dieser Benachrichtigungstyp wird x Tage vor Event-Start (einstellbar im Kalender) versandt. Pro Event wird genau eine E-Mail erstellt: An/Antwort an ##main_instructor_email##, CC ##co_instructors_email##, BCC ##participants_email##.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['instructor_post_event_task_reminder'] = ['Leiter-Erinnerung an offene Aufgaben nach dem Event', 'Dieser Benachrichtigungstyp erinnert Leiter und Anmelde-Koordinatoren an offene Aufgaben nach einem Event (Tourenbericht, Teilnahmebestätigung). Pro Empfänger und Kalender wird eine Benachrichtigung mit der Liste aller offenen Aufgaben versendet. Bearbeitungsfrist, Intervall und Rückwirkung werden im Kalender eingestellt.'];
