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

/*
 * Legends
 */
$GLOBALS['TL_LANG']['tl_event_feedback']['survey_legend'] = 'Auswertungsergebnisse';

/*
 * Operations
 */
$GLOBALS['TL_LANG']['tl_event_feedback']['edit'] = ['Datensatz mit ID: %s bearbeiten', 'Datensatz mit ID: %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_event_feedback']['copy'] = ['Datensatz mit ID: %s kopieren', 'Datensatz mit ID: %s kopieren'];
$GLOBALS['TL_LANG']['tl_event_feedback']['delete'] = ['Datensatz mit ID: %s löschen', 'Datensatz mit ID: %s löschen'];
$GLOBALS['TL_LANG']['tl_event_feedback']['show'] = ['Datensatz mit ID: %s ansehen', 'Datensatz mit ID: %s ansehen'];

/*
 * Fields
 */
$GLOBALS['TL_LANG']['tl_event_feedback']['form'] = ['Formular', 'Wählen Sie das Formular aus.'];
$GLOBALS['TL_LANG']['tl_event_feedback']['uuid'] = ['UUID'];
$GLOBALS['TL_LANG']['tl_event_feedback']['dateAdded'] = ['Erstellt am'];
$GLOBALS['TL_LANG']['tl_event_feedback']['learningEffectIndex'] = ['Ich habe etwas gelernt.'];
$GLOBALS['TL_LANG']['tl_event_feedback']['learningGoalsAchievedIndex'] = ['Die Lernziele wurden erreicht.'];
$GLOBALS['TL_LANG']['tl_event_feedback']['theoryAndPracticeBalanceIndex'] = ['Theorie und Praxis waren richtig bemessen.'];
$GLOBALS['TL_LANG']['tl_event_feedback']['recommendationIndex'] = ['Würdest du den Kurs weiterempfehlen!'];
$GLOBALS['TL_LANG']['tl_event_feedback']['safetyFeelingIndex'] = ['Während des Kurses fühlte ich mich sicher.'];
$GLOBALS['TL_LANG']['tl_event_feedback']['durationIndex'] = ['Der Kurs hatte die richtige Dauer.'];
$GLOBALS['TL_LANG']['tl_event_feedback']['improvementOpportunity'] = ['Was kann verbessert werden?'];
$GLOBALS['TL_LANG']['tl_event_feedback']['highlights'] = ['Was sollte unbedingt beibehalten werden?'];
$GLOBALS['TL_LANG']['tl_event_feedback']['wildcard'] = ['Platzhalter'];
$GLOBALS['TL_LANG']['tl_event_feedback']['comments'] = ['Kommentare/Anregungen'];

/*
 * References
 */
$GLOBALS['TL_LANG']['tl_event_feedback']['learningEffectIndexReference'] = [
    '1' => 'Ja, sehr!',
    '2' => 'Ja!',
    '3' => 'Nein!',
    '4' => 'Nein, gar nicht!',
];

$GLOBALS['TL_LANG']['tl_event_feedback']['durationIndexReference'] = [
    '1' => 'Zu kurz!',
    '2' => 'Etwas zu kurz!',
    '3' => 'Genau richtig!',
    '4' => 'Etwas zu lange!',
    '5' => 'Zu lange!',
];
