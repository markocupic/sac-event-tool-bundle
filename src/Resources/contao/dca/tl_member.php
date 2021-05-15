<?php

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\Config;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Markocupic\SacEventToolBundle\Dca\TlMember;

// Manipulate palette default
PaletteManipulator::create()
	->addLegend('food_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addLegend('section_info_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addLegend('section_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addLegend('emergency_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addLegend('avatar_legend', 'contact_legend', PaletteManipulator::POSITION_AFTER)
	->addField(array('avatar'), 'avatar_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('foodHabits'), 'food_legend', PaletteManipulator::POSITION_AFTER)
	->addField(array('isSacMember', 'sacMemberId', 'ahvNumber', 'uuid', 'sectionId', 'profession', 'addressExtra', 'streetExtra', 'phoneBusiness', 'entryYear', 'membershipType', 'sectionInfo1', 'sectionInfo2', 'sectionInfo3', 'sectionInfo4', 'debit', 'memberStatus'), 'section_legend', PaletteManipulator::POSITION_APPEND)
	->addField(array('emergencyPhone', 'emergencyPhoneName'), 'emergency_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('default', 'tl_member');

/**
 * Table tl_member
 */
$GLOBALS['TL_DCA']['tl_member']['list']['sorting']['fields'] = array('lastname ASC');
$GLOBALS['TL_DCA']['tl_member']['config']['ondelete_callback'][] = array(TlMember::class, 'ondeleteCallback');

// Add tl_member.sacMemberId to index
$GLOBALS['TL_DCA']['tl_member']['config']['sql']['keys']['sacMemberId'] = 'index';

// More fields...
// Avatar
$GLOBALS['TL_DCA']['tl_member']['fields']['avatar'] = array(
	'exclude'   => true,
	'inputType' => 'fileTree',
	'eval'      => array('filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'),
	'sql'       => "binary(16) NULL"
);

// Uuid from SAC central committee in Bern
$GLOBALS['TL_DCA']['tl_member']['fields']['uuid'] = array(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('mandatory' => false, 'tl_class' => 'w50'),
	'sql'       => "varchar(128) NOT NULL default ''",
);

// activationLinkLifetime
$GLOBALS['TL_DCA']['tl_member']['fields']['activationLinkLifetime'] = array(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'datim', 'mandatory' => false, 'datepicker' => true, 'tl_class' => 'clr wizard'),
	'sql'       => "int(10) unsigned NULL",
);

// activation
$GLOBALS['TL_DCA']['tl_member']['fields']['activation'] = array(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('mandatory' => false, 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

// activationFalseTokenCounter
$GLOBALS['TL_DCA']['tl_member']['fields']['activationFalseTokenCounter'] = array(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural'),
	'sql'       => "int(10) unsigned NULL",
);

// isSacMember
$GLOBALS['TL_DCA']['tl_member']['fields']['isSacMember'] = array(
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => false),
	'sql'       => "char(1) NOT NULL default ''",
);

// sacMemberId
$GLOBALS['TL_DCA']['tl_member']['fields']['sacMemberId'] = array(
	'exclude'   => true,
	'search'    => true,
	'sorting'   => true,
	'flag'      => 1,
	'inputType' => 'text',
	'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'rgxp' => 'natural'),
	'sql'       => "int(10) unsigned NOT NULL default '0'",
);

// ahvNumber
$GLOBALS['TL_DCA']['tl_member']['fields']['ahvNumber'] = array(
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => array('mandatory' => false, 'maxlength' => 16, 'decodeEntities' => true, 'feEditable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
    'sql'       => "varchar(255) NOT NULL default ''",
);

// sectionId
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionId'] = array(
	'exclude'   => true,
	'reference' => &$GLOBALS['TL_LANG']['tl_member']['section'],
	'inputType' => 'checkbox',
	'filter'    => true,
	'eval'      => array('multiple' => true, 'tl_class' => 'clr'),
	'options'   => explode(',', Config::get('SAC_EVT_SAC_SECTION_IDS')),
	'sql'       => "blob NULL",
);

// profession
$GLOBALS['TL_DCA']['tl_member']['fields']['profession'] = array(
	'exclude'   => true,
	'search'    => true,
	'sorting'   => true,
	'flag'      => 1,
	'inputType' => 'text',
	'eval'      => array('maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
	'sql'       => "varchar(255) NOT NULL default ''",
);

// addressExtra
$GLOBALS['TL_DCA']['tl_member']['fields']['addressExtra'] = array(
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => array('maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
	'sql'       => "varchar(255) NOT NULL default ''",
);

// streetExtra
$GLOBALS['TL_DCA']['tl_member']['fields']['streetExtra'] = array(
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => array('maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
	'sql'       => "varchar(255) NOT NULL default ''",
);

// phoneBusiness
$GLOBALS['TL_DCA']['tl_member']['fields']['phoneBusiness'] = array(
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => array('maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

// entryYear
$GLOBALS['TL_DCA']['tl_member']['fields']['entryYear'] = array(
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(5) NOT NULL default ''",
);

// membershipType
$GLOBALS['TL_DCA']['tl_member']['fields']['membershipType'] = array(
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(256) NOT NULL default ''",
);

// sectionInfo1
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo1'] = array(
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(256) NOT NULL default ''",
);

// sectionInfo2
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo2'] = array(
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(256) NOT NULL default ''",
);

// sectionInfo3
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo3'] = array(
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(256) NOT NULL default ''",
);

// sectionInfo4
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo4'] = array(
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(256) NOT NULL default ''",
);

// debit
$GLOBALS['TL_DCA']['tl_member']['fields']['debit'] = array(
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(256) NOT NULL default ''",
);

// memberStatus
$GLOBALS['TL_DCA']['tl_member']['fields']['memberStatus'] = array(
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'w50'),
	'sql'       => "varchar(256) NOT NULL default ''",
);

// emergencyPhone
$GLOBALS['TL_DCA']['tl_member']['fields']['emergencyPhone'] = array(
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => array('maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

// emergencyPhoneName
$GLOBALS['TL_DCA']['tl_member']['fields']['emergencyPhoneName'] = array(
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => array('maxlength' => 64, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

// foodHabits
$GLOBALS['TL_DCA']['tl_member']['fields']['foodHabits'] = array(
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'clr'),
	'sql'       => "varchar(1024) NOT NULL default ''",
);

if (TL_MODE === 'BE')
{
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
