<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

// Legends
$GLOBALS['TL_LANG']['tl_member']['section_legend'] = 'Sektions-Einstellungen';
$GLOBALS['TL_LANG']['tl_member']['section_info_legend'] = 'Sektionsinformationen';
$GLOBALS['TL_LANG']['tl_member']['avatar_legend'] = 'Avatar';
$GLOBALS['TL_LANG']['tl_member']['emergency_legend'] = 'Notfallangaben';
$GLOBALS['TL_LANG']['tl_member']['food_legend'] = 'Essgewohnheiten';
$GLOBALS['TL_LANG']['tl_member']['education_legend'] = 'Ausbildung';

// Fields
if (TL_MODE === 'FE')
{
    $GLOBALS['TL_LANG']['tl_member']['username'] = array('SAC Mitgliedernummer', 'Gib deine 6-stellige SAC-Mitgliedernummer ein.');
}
$GLOBALS['TL_LANG']['tl_member']['uuid'] = array('UUID (Zentralkommitee Bern)', '');
$GLOBALS['TL_LANG']['tl_member']['activation'] = array('Aktivierungscode', '');
$GLOBALS['TL_LANG']['tl_member']['activationLinkLifetime'] = array('Gültigkeitsdauer Aktivierungstoken', '');
$GLOBALS['TL_LANG']['tl_member']['isSacMember'] = array('Dieser User ist ein SAC-Mitglied', 'Dieses Mitglied wurde in der Datenbank (CSV-File) beim täglichen Sync gefunden.');
$GLOBALS['TL_LANG']['tl_member']['hasLeadClimbingEducation'] = array('Seilschaftsführer', 'Geben Sie an, ob das Mitglied die Seilschaftsführer-Ausbildung besitzt.');
$GLOBALS['TL_LANG']['tl_member']['dateOfLeadClimbingEducation'] = array('Datum der Seilschaftsführer-Ausbildung', 'Geben Sie an, wann das Mitglied die Seilschaftsführer-Ausbildung absolviert hat.');
$GLOBALS['TL_LANG']['tl_member']['sacMemberId'] = array('Mitgliedernummer', '');
$GLOBALS['TL_LANG']['tl_member']['ahvNumber'] = ["AHV-Nummer", 'Geben Sie hier die AHV-Nummer ein.'];
$GLOBALS['TL_LANG']['tl_member']['emergencyPhone'] = array('Notfall-Benachrichtigungs-Telefonnummer', 'Geben Sie die Telefonnummer einer Ihnen vertrauten Person an, welche in einem Notfall kontaktiert werden kann.');
$GLOBALS['TL_LANG']['tl_member']['emergencyPhoneName'] = array('Kontaktperson für Notfallbenachrichtigung', 'Geben Sie den Namen der Kontaktperson ein, welche im FAlle eines Notfalls benachrichtigt werden soll.');
$GLOBALS['TL_LANG']['tl_member']['sectionId'] = array('Sektion', '');
$GLOBALS['TL_LANG']['tl_member']['addressExtra'] = array('Adresszusatz', '');
$GLOBALS['TL_LANG']['tl_member']['phoneBusiness'] = array('Telefon Geschäft', '');
$GLOBALS['TL_LANG']['tl_member']['profession'] = array('Beruf', '');
$GLOBALS['TL_LANG']['tl_member']['entryYear'] = array('Eintrittsjahr', '');
$GLOBALS['TL_LANG']['tl_member']['membershipType'] = array('Mitglieder Typ', '');
$GLOBALS['TL_LANG']['tl_member']['sectionInfo1'] = array('Sektionsinfo 1', '');
$GLOBALS['TL_LANG']['tl_member']['sectionInfo2'] = array('Sektionsinfo 2', '');
$GLOBALS['TL_LANG']['tl_member']['sectionInfo3'] = array('Sektionsinfo 3', '');
$GLOBALS['TL_LANG']['tl_member']['sectionInfo4'] = array('Bemerkungen Sektion', '');
$GLOBALS['TL_LANG']['tl_member']['debit'] = array('Debit', '');
$GLOBALS['TL_LANG']['tl_member']['memberStatus'] = array('Mitgliederstatus', '');
$GLOBALS['TL_LANG']['tl_member']['disable'] = array('Inaktives SAC-Mitglied (ausgetreten)', '');
$GLOBALS['TL_LANG']['tl_member']['foodHabits'] = array('Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)', '');

// References
$GLOBALS['TL_LANG']['tl_member']['section']['4250'] = 'SAC PILATUS';
$GLOBALS['TL_LANG']['tl_member']['section']['4251'] = 'SAC PILATUS SURENTAL';
$GLOBALS['TL_LANG']['tl_member']['section']['4252'] = 'SAC PILATUS NAPF';
$GLOBALS['TL_LANG']['tl_member']['section']['4253'] = 'SAC PILATUS HOCHDORF';
$GLOBALS['TL_LANG']['tl_member']['section']['4254'] = 'SAC PILATUS RIGI';
