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

// Global operations
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['new'] = ['Neue Freigabestufe anlegen', 'Legen Sie eine neue Freigabestufe an.'];

// Buttons
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['edit'] = ['Bearbeiten', 'Freigabestufe mit ID %s bearbeiten.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['delete'] = ['Löschen', 'Freigabestufe mit ID %s löschen. Es kann nur die höchste Stufe gelöscht werden.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['copy'] = ['Kopieren', 'Freigabestufe mit ID %s kopieren.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['show'] = ['Ansehen', 'Freigabestufe mit ID %s ansehen.'];

// Legends
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title_legend'] = 'Titel-Einstellungen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['event_registrations_grants_legend'] = 'Online-Anmeldung-Frontend';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permission_rules_legend'] = 'Rechte-Regeln';

// Fields
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['level'] = ['Veröffentlichungsstufe', 'Wählen Sie die Veröffentlichungsstufe aus. Jede Stufe darf pro Freigabestufen-System nur einmal vorkommen, und die Stufen müssen lückenlos bei 1 beginnen (1, 2, 3, …).'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title'] = ['Titel', 'Geben Sie für die Freigabestufe einen Namen an. Der Name darf pro Freigabestufen-System nur einmal vorkommen.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['description'] = ['Beschreibung', 'Geben Sie für die Freigabestufe einen Namen ein.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowRegistration'] = ['Online Anmeldung zu Event im Frontend ermöglichen.', 'Wenn diese Option aktiviert ist, können sich Teilnehmer für den Event im Frontend über das Buchungsformular anmelden.'];

// Errors
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['errLevelMin'] = 'Die Stufe %s ist nicht zulässig: Die tiefste Stufe ist immer 1.';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['errLevelExists'] = 'Die Stufe %s ist in diesem Freigabestufen-System bereits vergeben.';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['errLevelGap'] = 'Die Stufe %s ist nicht zulässig: Die Stufen müssen lückenlos bei 1 beginnen. Mit dieser Stufe wären es: %s.';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['errTitleExists'] = 'Der Titel "%s" ist in diesem Freigabestufen-System bereits vergeben.';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules'] = ['Rechte-Regeln', 'Eine Regel gewährt die ausgewählten Rechte auf dieser Freigabestufe den ausgewählten Parteien des Events oder den Mitgliedern einer Benutzergruppe.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_parties'] = ['Parteien', 'Auf welche Parteien des Events soll die Regel angewendet werden?'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_group'] = ['Benutzergruppe', 'Soll die Regel auf die Mitglieder einer Benutzergruppe angewendet werden?'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flags'] = ['Rechte', 'Wählen Sie die Rechte aus, die gewährt werden sollen.'];
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_partyOptions']['event_author'] = 'Event-Autor';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_partyOptions']['main_instructor'] = 'Nur Hauptleiter';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_partyOptions']['event_instructors'] = 'Alle Event-Leiter';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_partyOptions']['registration_coordinator'] = 'Anmeldungs-Koordinator';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flagOptions']['can_write_event'] = 'Event bearbeiten';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flagOptions']['can_delete_event'] = 'Event löschen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flagOptions']['can_cut_event'] = 'Event verschieben';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flagOptions']['can_administer_event_registrations'] = 'Event-Anmeldungen administrieren';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flagOptions']['can_upgrade_release_level'] = 'Freigabestufe hochstufen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flagOptions']['can_downgrade_release_level'] = 'Freigabestufe herabstufen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flagOptions']['can_view_participant_event_history'] = 'Teilnahme-Historie der Angemeldeten ansehen';
