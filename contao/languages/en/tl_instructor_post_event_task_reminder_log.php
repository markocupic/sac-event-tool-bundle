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

// Legends
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['log_legend'] = 'Log-Eintrag';

// Fields
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['id'] = ['ID'];
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['tstamp'] = ['Änderungsdatum'];
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['sentAt'] = ['Versendet am', 'Zeitpunkt, an dem die Erinnerung versendet wurde.'];
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['userId'] = ['Empfänger', 'Leiter oder Anmelde-Koordinator, der die Erinnerung erhalten hat.'];
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['calendarId'] = ['Kalender', 'Kalender, dessen Events geprüft wurden.'];
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['notificationId'] = ['Benachrichtigung', 'Verwendete Benachrichtigung aus dem Notification Center.'];
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['reminderCount'] = ['Zähler', 'Die wievielte Erinnerung der Empfänger für diesen Kalender erhalten hat (1 = erste Erinnerung).'];
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['openTaskCount'] = ['Offene Aufgaben', 'Anzahl offener Aufgaben zum Zeitpunkt des Versands.'];
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['eventIds'] = ['Events', 'IDs der Events mit offenen Aufgaben zum Zeitpunkt des Versands, kommagetrennt.'];
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['delivered'] = ['Zugestellt', 'Ob das Notification Center den Versand als erfolgreich gemeldet hat.'];

// Operations
$GLOBALS['TL_LANG']['tl_instructor_post_event_task_reminder_log']['show'] = ['Details anzeigen', 'Details des Eintrags ID %s anzeigen'];
