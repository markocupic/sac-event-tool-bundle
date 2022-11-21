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

// Global operations
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['new'] = ['Neue Freigabestufe anlegen', 'Legen Sie eine neue Freigabestufe an.'];

// Buttons
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['edit'] = ['Bearbeiten', 'Freigabestufe mit ID %s bearbeiten.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['delete'] = ['Löschen', 'Freigabestufe mit ID %s löschen.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['copy'] = ['Kopieren', 'Freigabestufe mit ID %s kopieren.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['show'] = ['Ansehen', 'Freigabestufe mit ID %s ansehen.'];

// Legends
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title_legend'] = 'Titeleinstellungen';

// Fields
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['level'] = ['Veröffentlichungsstufe', 'Geben Sie eine Veröffentlichungsstufe ein. Keine doppelten Einträge!!!'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title'] = ['Titel', 'Geben Sie für die Freigabestufe einen Namen an.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['description'] = ['Beschreibung', 'Geben Sie für die Freigabestufe einen Namen ein.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['groupReleaseLevelRights'] = ['Weitere berechtigten Gruppen', 'Geben Sie die berechtigten Gruppen an. Der Autor und die Event-Leiter müssen nicht angegeben werden.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['releaseLevelRights'] = ['Freigabestufe-Rechte für Backend-Gruppen', 'Geben Sie die Freigabestufen-Rechte für Backend-Gruppen an.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['group'] = ['Backend-Gruppen', 'Wählen Sie die Gruppen mit erweiterten Rechten aus.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['canWrite'] = ['Kann schreiben', 'Schreibzugriff ermöglichen.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['canDelete'] = ['Kann löschen', 'Löschen ermöglichen?.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToAuthor'] = ['Dem Event-Autor Scheibzugriff auf diesem Level gewähren.', 'Mit dieser Einstellung gewähren Sie auf diesem Level dem Autor Schreibugriff.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToInstructors'] = ['Den Event-Leitern Scheibzugriff auf diesem Level gewähren.', 'Mit dieser Einstellung gewähren Sie auf diesem Level den Event-Leitern Schreibugriff.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowDeleteAccessToAuthor'] = ['Dem Event-Autor Löschzugriff auf diesem Level gewähren.', 'Mit dieser Einstellung gewähren Sie auf diesem Level dem Autor Löschzugriff.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowDeleteAccessToInstructors'] = ['Den Event-Leitern Löschzugriff auf diesem Level gewähren.', 'Mit dieser Einstellung gewähren Sie auf diesem Level den Event-Leitern Löschzugriff.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToPrevLevel'] = ['Dem Event-Autor und dem Event-Leiter erlauben die Veröffentlichungsstufe herunterzustufen', ''];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToNextLevel'] = ['Dem Event-Autor und dem Event-Leiter erlauben die Veröffentlichungsstufe hochzustufen', ''];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowRegistration'] = ['Online Anmeldung zu Event ermöglichen.', ''];

// References
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['up'] = 'erlaube hochstufen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['down'] = 'erlaube herabstufen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['upAndDown'] = 'erlaube auf- und herabstufen';
