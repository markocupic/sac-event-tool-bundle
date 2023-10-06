<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

// Legends
$GLOBALS['TL_LANG']['tl_newsletter']['sac_evt_legend'] = 'SAC Event Tool Einstellungen';

// Fields
$GLOBALS['TL_LANG']['tl_newsletter']['enableSendAndDeleteCron'] = ['Aktiviere Send & Delete Cron Job', 'Der Newsletter wird automatisch via Cron Job versandt und die Empfänger danach aus der Liste entfernt. Tritt beim Versand ein Fehler auf, wird der Empfänger deaktiviert und nicht gelöscht.'];
$GLOBALS['TL_LANG']['tl_newsletter']['sendPerMinute'] = ['Anzahl Nachrichten pro Minute (Stundenlimit beachten)', 'Cyon erlaubt den Versand von 1000 E-Mails pro Stunde.'];
$GLOBALS['TL_LANG']['tl_newsletter']['cronJobStart'] = ['Cron Job Aktivierungszeitpunkt (leer = ab sofort)', 'Wählen Sie den Zeitpunkt aus, ab wann der Cron Job aktiv werden soll (leer = ab sofort).'];
