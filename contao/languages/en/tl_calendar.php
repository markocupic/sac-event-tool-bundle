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

use Markocupic\SacEventToolBundle\Config\EventType;

// Legends
$GLOBALS['TL_LANG']['tl_calendar']['event_type_legend'] = 'Event-Typen-Einstellungen';
$GLOBALS['TL_LANG']['tl_calendar']['event_reader_legend'] = 'Event-Detailseite-Einstellungen';
$GLOBALS['TL_LANG']['tl_calendar']['valid_time_period_legend'] = 'Einstellungen für Kalenderzeitspanne';
$GLOBALS['TL_LANG']['tl_calendar']['event_release_level_legend'] = 'Freigabestufe-Einstellungen';

// Fields
$GLOBALS['TL_LANG']['tl_calendar']['allowedEventTypes'] = ['Erlaubte Event Typen', 'Wählen Sie die Event Typen, die im Kalender ausgewählt werden dürfen, aus.'];
$GLOBALS['TL_LANG']['tl_calendar']['notifyOnEventReleaseLevelChange'] = ['Benachrichtigen bei Freigabestufen-Änderung', 'Geben Sie eine Kommaseparierte Liste mit E-Mail-Adressen an.'];
$GLOBALS['TL_LANG']['tl_calendar']['notifyOnEventPublish'] = ['Benachrichtigen bei Event-Veröffentlichung', 'Geben Sie eine Kommaseparierte Liste mit E-Mail-Adressen an.'];
$GLOBALS['TL_LANG']['tl_calendar']['userPortraitJumpTo'] = ['Seite mit User Portrait Inhaltselement.', 'Wählen Sie aus dem Seitenbaum eine Seite, welche das User-Portrait-Inhaltselement enthält.'];
$GLOBALS['TL_LANG']['tl_calendar']['enableEventStartDateValidation'] = ['Kalender-Zeitspanne festlegen', 'Mit dieser Einstellung kann verhindert werden, dass ein Event ausserhalb einer festgelegten Zeitspanne auf >=FS 2 hochgestuft werden kann.'];
$GLOBALS['TL_LANG']['tl_calendar']['validTimePeriodStart'] = ['Events ermöglichen ab', 'Events ab diesem Zeitpunkt erlauben.'];
$GLOBALS['TL_LANG']['tl_calendar']['validTimePeriodStop'] = ['Events ermöglichen bis', 'Events bis zu diesem Zeitpunkt erlauben.'];
$GLOBALS['TL_LANG']['tl_calendar']['enableMaxEventReleaseLevelProtection'] = ['Hochstufen auf höchste FS ab Datum ermöglichen', 'Mit dieser Einstellung kann verhindert werden, dass Nicht-Admins einen Event nicht vor einem festgelegten Datum auf die höchste Freigabestufe setzen können.'];
$GLOBALS['TL_LANG']['tl_calendar']['maxEventReleaseLevelTimeLimit'] = ['Hochstufen auf höchste FS ab diesem Datum erlauben', 'Legen Sie ein Datum fest, ab welchem die Hochstufung auf die höchste Freigabestufe erlaubt werden soll.'];

// References
$GLOBALS['TL_LANG']['tl_calendar'][EventType::COURSE] = 'SAC-Kurskalender';
$GLOBALS['TL_LANG']['tl_calendar'][EventType::TOUR] = 'SAC-Tourenkalender';

// Operations
$GLOBALS['TL_LANG']['tl_calendar']['cut'] = ['Kalender verschieben', 'Event ID %s verschieben'];
