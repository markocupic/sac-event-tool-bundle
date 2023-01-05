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

use Contao\BackendUser;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;

// Manipulate palette default
PaletteManipulator::create()
	->addLegend('food_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addLegend('section_info_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addLegend('section_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addLegend('emergency_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addLegend('avatar_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addLegend('education_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addField(['avatar'], 'avatar_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['foodHabits'], 'food_legend', PaletteManipulator::POSITION_AFTER)
	->addField(['isSacMember', 'sacMemberId', 'ahvNumber', 'uuid', 'sectionId', 'profession', 'addressExtra', 'streetExtra', 'phoneBusiness', 'entryYear', 'membershipType', 'sectionInfo1', 'sectionInfo2', 'sectionInfo3', 'sectionInfo4', 'debit', 'memberStatus'], 'section_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['emergencyPhone', 'emergencyPhoneName'], 'emergency_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['hasLeadClimbingEducation'], 'education_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('default', 'tl_member');

// Add palettes
$GLOBALS['TL_DCA']['tl_member']['palettes']['__selector__'][] = 'hasLeadClimbingEducation';
$GLOBALS['TL_DCA']['tl_member']['subpalettes']['hasLeadClimbingEducation'] = 'dateOfLeadClimbingEducation';

// Customize tl_member
$GLOBALS['TL_DCA']['tl_member']['list']['sorting']['fields'] = ['lastname ASC'];

// Add tl_member.sacMemberId to index
$GLOBALS['TL_DCA']['tl_member']['config']['sql']['keys']['sacMemberId'] = 'index';

// More fields...
$GLOBALS['TL_DCA']['tl_member']['fields']['avatar'] = [
	'exclude'   => true,
	'inputType' => 'fileTree',
	'eval'      => ['filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
	'sql'       => 'binary(16) NULL',
];

$GLOBALS['TL_DCA']['tl_member']['fields']['uuid'] = [
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => false, 'tl_class' => 'w50'],
	'sql'       => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['activationLinkLifetime'] = [
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'datim', 'mandatory' => false, 'datepicker' => true, 'tl_class' => 'clr wizard'],
	'sql'       => 'int(10) unsigned NULL',
];

$GLOBALS['TL_DCA']['tl_member']['fields']['activation'] = [
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => false, 'tl_class' => 'w50'],
	'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['activationFalseTokenCounter'] = [
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'natural'],
	'sql'       => 'int(10) unsigned NULL',
];

$GLOBALS['TL_DCA']['tl_member']['fields']['isSacMember'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => false],
	'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['sacMemberId'] = [
	'exclude'   => true,
	'search'    => true,
	'sorting'   => true,
	'flag'      => 1,
	'inputType' => 'text',
	'eval'      => ['doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'rgxp' => 'natural'],
	'sql'       => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['hasLeadClimbingEducation'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true],
	'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['dateOfLeadClimbingEducation'] = [
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => true, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => "varchar(11) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['ahvNumber'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['mandatory' => false, 'maxlength' => 16, 'decodeEntities' => true, 'feEditable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['sectionId'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'filter'    => true,
	'eval'      => ['multiple' => true, 'chosen' => true, 'doNotCopy' => true, 'tl_class' => 'clr'],
	'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_member']['fields']['profession'] = [
	'exclude'   => true,
	'search'    => true,
	'sorting'   => true,
	'flag'      => 1,
	'inputType' => 'text',
	'eval'      => ['maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['addressExtra'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['streetExtra'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['phoneBusiness'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
	'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['entryYear'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(5) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['membershipType'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(256) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo1'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(256) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo2'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(256) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo3'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(256) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo4'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(256) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['debit'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(256) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['memberStatus'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(256) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['emergencyPhone'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
	'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['emergencyPhoneName'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['maxlength' => 255, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
	'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['foodHabits'] = [
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'clr', 'maxlength' => 5000],
	'sql'       => 'text NULL',
];

/** @var Request $request */
$request = System::getContainer()->get('request_stack')->getCurrentRequest();

$user = System::getContainer()->get('security.helper');

if ($user instanceof BackendUser && !$user->admin) {
	if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
		// Fields (readonly fields)
		$GLOBALS['TL_DCA']['tl_member']['fields']['uuid']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['sacMemberId']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['gender']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['firstname']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['lastname']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['street']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['streetExtra']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['addressExtra']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['postal']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['city']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['memberStatus']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['debit']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo1']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo2']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo3']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo4']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['entryYear']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['membershipType']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['phone']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['mobile']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['email']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['dateOfBirth']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['username']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['sectionId']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['phoneBusiness']['eval']['readonly'] = 'readonly';
		$GLOBALS['TL_DCA']['tl_member']['fields']['profession']['eval']['readonly'] = 'readonly';
	}
}
