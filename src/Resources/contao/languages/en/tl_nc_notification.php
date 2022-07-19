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

// Notification groups
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['sac_event_tool'] = 'SAC Event Tool';

// Notification types
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['accept_event_participation'] = ['Event-Teilnahme bestätigen', 'Dieser Benachrichtigungstyp wird versandt, nachdem der Autor seinen Bericht zur Korrektur an die Redaktion freigegeben hat.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['onchange_state_of_subscription'] = ['Benachrichtigung bei Änderung des Event-Anmeldestatus', 'Dieser Benachrichtigungstyp wird versandt, nachdem sich der Anmeldestatus geändert hat.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['receipt_event_registration'] = ['Benachrichtigung nach dem Absenden des Event-Buchungsformulars', 'Dieser Benachrichtigungstyp wird versandt, nachdem eine Online Anmeldung eingegangen ist.'];
$GLOBALS['TL_LANG']['tl_nc_notification']['type']['sign_out_from_event'] = ['Benachrichtigung bei Event-Stornierung', 'Dieser Benachrichtigungstyp wird bei der Stornierung einer Event-Teilnahme durch den Teilnehmer versandt.'];
