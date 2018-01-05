<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */


/**
 * Table tl_member
 */
$GLOBALS['TL_DCA']['tl_member']['list']['sorting']['fields'] = array('lastname ASC');
$GLOBALS['TL_DCA']['tl_member']['config']['ondelete_callback'][] = array('tl_member_sac_bundle', 'ondeleteCallback');


// Fields
// isSacMember
$GLOBALS['TL_DCA']['tl_member']['fields']['isSacMember'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['isSacMember'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => array('submitOnChange' => false),
    'sql'       => "char(1) NOT NULL default ''",
);

// newsletterSent
$GLOBALS['TL_DCA']['tl_member']['fields']['newsletterSent'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['newsletterSent'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => array('submitOnChange' => false),
    'sql'       => "char(1) NOT NULL default ''",
);

// sacMemberId
$GLOBALS['TL_DCA']['tl_member']['fields']['sacMemberId'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['sacMemberId'],
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'flag'      => 1,
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'rgxp' => 'natural'),
    'sql'       => "int(10) unsigned NOT NULL default '0'",
);

// sectionId
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionId'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['sectionId'],
    'reference' => &$GLOBALS['TL_LANG']['tl_member']['section'],
    'inputType' => 'checkbox',
    'filter'    => true,
    'eval'      => array('multiple' => true),
    'options'   => range(4250, 4254),
    'sql'       => "blob NULL",
);

// profession
$GLOBALS['TL_DCA']['tl_member']['fields']['profession'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['profession'],
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
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['addressExtra'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => array('maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
    'sql'       => "varchar(255) NOT NULL default ''",
);

// street
$GLOBALS['TL_DCA']['tl_member']['fields']['street'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['street'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => array('maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
    'sql'       => "varchar(255) NOT NULL default ''",
);

// streetExtra
$GLOBALS['TL_DCA']['tl_member']['fields']['streetExtra'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['streetExtra'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => array('maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
    'sql'       => "varchar(255) NOT NULL default ''",
);

// phoneBusiness
$GLOBALS['TL_DCA']['tl_member']['fields']['phoneBusiness'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['phoneBusiness'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => array('maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
    'sql'       => "varchar(64) NOT NULL default ''",
);

// entryYear
$GLOBALS['TL_DCA']['tl_member']['fields']['entryYear'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['entryYear'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'w50'),
    'sql'       => "varchar(5) NOT NULL default ''",
);

// membershipType
$GLOBALS['TL_DCA']['tl_member']['fields']['membershipType'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['membershipType'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'w50'),
    'sql'       => "varchar(256) NOT NULL default ''",
);

// sectionInfo1
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo1'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['sectionInfo1'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'w50'),
    'sql'       => "varchar(256) NOT NULL default ''",
);

// sectionInfo2
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo2'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['sectionInfo2'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'w50'),
    'sql'       => "varchar(256) NOT NULL default ''",
);

// sectionInfo3
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo3'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['sectionInfo3'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'w50'),
    'sql'       => "varchar(256) NOT NULL default ''",
);

// sectionInfo4
$GLOBALS['TL_DCA']['tl_member']['fields']['sectionInfo4'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['sectionInfo4'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'w50'),
    'sql'       => "varchar(256) NOT NULL default ''",
);

// debit
$GLOBALS['TL_DCA']['tl_member']['fields']['debit'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['debit'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'w50'),
    'sql'       => "varchar(256) NOT NULL default ''",
);

// memberStatus
$GLOBALS['TL_DCA']['tl_member']['fields']['memberStatus'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['memberStatus'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => array('tl_class' => 'w50'),
    'sql'       => "varchar(256) NOT NULL default ''",
);

// emergencyPhone
$GLOBALS['TL_DCA']['tl_member']['fields']['emergencyPhone'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['emergencyPhone'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => array('maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
    'sql'       => "varchar(64) NOT NULL default ''",
);

// emergencyPhoneName
$GLOBALS['TL_DCA']['tl_member']['fields']['emergencyPhoneName'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['emergencyPhoneName'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => array('maxlength' => 64, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
    'sql'       => "varchar(64) NOT NULL default ''",
);

// vegetarian
$GLOBALS['TL_DCA']['tl_member']['fields']['vegetarian'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_member']['vegetarian'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'select',
    'options'   => array('false' => 'Nein', 'true' => 'Ja'),
    'eval'      => array('tl_class' => 'w50'),
    'sql'       => "varchar(32) NOT NULL default ''",
);


