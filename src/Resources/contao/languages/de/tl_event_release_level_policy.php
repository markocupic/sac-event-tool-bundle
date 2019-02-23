<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */


// Global operations
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['new'] = array("Neue Freigabestufe anlegen", "Legen Sie eine neue Freigabestufe an.");

// Buttons
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['edit'] = array("Bearbeiten", "Freigabestufe mit ID %s bearbeiten.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['delete'] = array("L&ouml;schen", "Freigabestufe mit ID %s l&ouml;schen.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['copy'] = array("Kopieren", "Freigabestufe mit ID %s kopieren.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['show'] = array("Ansehen", "Freigabestufe mit ID %s ansehen.");

// Legends
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title_legend'] = "Titeleinstellungen";

// Fields
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['level'] = array("Ver&ouml;ffentlichungsstufe", "Geben Sie eine Ver&ouml;ffentlichungsstufe ein. Keine doppelten Eintr&auml;ge!!!");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title'] = array("Titel", "Geben Sie f&uuml;r die Freigabestufe einen Namen an.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['description'] = array("Beschreibung", "Geben Sie f&uuml;r die Freigabestufe einen Namen ein.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['groupReleaseLevelRights'] = array("Weitere berechtigten Gruppen", "Geben Sie die berechtigten Gruppen an. Der Autor und die Event-Leiter müssen nicht angegeben werden.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['releaseLevelRights'] = array("Freigabestufe-Rechte für Backend-Gruppen", "Geben Sie die Freigabestufen-Rechte für Backend-Gruppen an.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['group'] = array("Backend-Gruppen", "Wählen Sie die Gruppen mit erweiterten Rechten aus.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['writeAccess'] = array("Schreibberechtigung", "Soll die Gruppe Schreibzugriff erhalten?.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToAuthor'] = array("Dem Autor Scheibzugriff auf diesem Level gew&auml;hren.", "Mit dieser Einstellung gew&auml;hren Sie auf diesem Level dem Autor Schreibugriff.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToInstructors'] = array("Den Event-Leitern Scheibzugriff auf diesem Level gew&auml;hren.", "Mit dieser Einstellung gew&auml;hren Sie auf diesem Level den Event-Leitern Schreibugriff.");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToPrevLevel'] = array("Dem Autor und dem Leiter erlauben die Veröffentlichungsstufe herunterzustufen", "");
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToNextLevel'] = array("Dem Autor und dem Leiter erlauben die Veröffentlichungsstufe hochzustufen", "");

// References
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['up'] = 'erlaube hochstufen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['down'] = 'erlaube herabstufen';
$GLOBALS['TL_LANG']['tl_event_release_level_policy']['upAndDown'] = 'erlaube auf- und herabstufen';
