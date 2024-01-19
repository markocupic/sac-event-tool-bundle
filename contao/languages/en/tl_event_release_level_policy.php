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

// Global operations
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['new'] = ['Neue Freigabestufe anlegen', 'Legen Sie eine neue Freigabestufe an.'];

// Buttons
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['edit'] = ['Bearbeiten', 'Freigabestufe mit ID %s bearbeiten.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['delete'] = ['Löschen', 'Freigabestufe mit ID %s löschen.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['copy'] = ['Kopieren', 'Freigabestufe mit ID %s kopieren.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['show'] = ['Ansehen', 'Freigabestufe mit ID %s ansehen.'];

// Legends
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title_legend'] = 'Titel-Einstellungen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['event_grants_legend'] = 'Rechte-Vergabe für Event';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['event_release_level_grants_legend'] = 'Rechte-Vergabe für Änderung Freigabestufe';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['event_registrations_grants_legend'] = 'Rechte-Vergabe für Event-Registrierungen';

// Fields
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['level'] = ['Veröffentlichungsstufe', 'Geben Sie eine Veröffentlichungsstufe ein. Keine doppelten Einträge!!!'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title'] = ['Titel', 'Geben Sie für die Freigabestufe einen Namen an.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['description'] = ['Beschreibung', 'Geben Sie für die Freigabestufe einen Namen ein.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['groupReleaseLevelPerm'] = ['Weitere berechtigten Gruppen', 'Geben Sie die berechtigten Gruppen an. Der Event-Besitzer (Autor) und der/die Event-Leiter müssen nicht angegeben werden.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['groupEventPerm'] = ['Weitere berechtigten Gruppen', 'Geben Sie die berechtigten Gruppen an. Der Event-Besitzer (Autor) und der/die Event-Leiter müssen nicht angegeben werden.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['group'] = ['Backend-Gruppen', 'Wählen Sie die Gruppen mit erweiterten Rechten aus.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissions'] = ['Rechte', 'Wählen Sie die Rechte aus, die Mitgliedern der Gruppe zugewiesen werden.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToAuthor'] = ['Dem Event-Autor Schreibzugriff auf diesem Level gewähren.', 'Mit dieser Einstellung gewähren Sie auf diesem Level dem Autor Schreibzugriff.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToInstructors'] = ['Den Event-Leitern Schreibzugriff auf diesem Level gewähren.', 'Mit dieser Einstellung gewähren Sie auf diesem Level den Event-Leitern Schreibzugriff.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowDeleteAccessToAuthor'] = ['Dem Event-Autor Löschzugriff auf diesem Level gewähren.', 'Mit dieser Einstellung gewähren Sie auf diesem Level dem Autor Löschzugriff.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowDeleteAccessToInstructors'] = ['Den Event-Leitern Löschzugriff auf diesem Level gewähren.', 'Mit dieser Einstellung gewähren Sie auf diesem Level den Event-Leitern Löschzugriff.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToPrevLevel'] = ['Dem Event-Besitzer und dem Event-Leiter das Herabstufen erlauben', 'Dem Event-Besitzer (Autor) und dem Event-Leiter das Herabstufen erlauben.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToNextLevel'] = ['Dem Event-Besitzer und dem Event-Leiter das Hochstufen erlauben', 'Dem Event-Besitzer (Autor) und dem Event-Leiter das Hochstufen erlauben.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowRegistration'] = ['Online Anmeldung zu Event ermöglichen.', ''];

// References
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['canWriteEvent'] = 'Event bearbeiten';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['canDeleteEvent'] = 'Event löschen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['canRelLevelUp'] = 'Freigabestufe hochstufen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['canRelLevelDown'] = 'Freigabestufe herabstufen';
