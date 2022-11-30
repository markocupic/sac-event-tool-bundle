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

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;

// Add tl_user.sacMemberId to index
$GLOBALS['TL_DCA']['tl_user']['config']['sql']['keys']['sacMemberId'] = 'index';

// Manipulate palette default
PaletteManipulator::create()
    ->addLegend('admin_legend', 'password_legend', PaletteManipulator::POSITION_AFTER)
    ->addLegend('frontend_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('bank_account_legend', 'frontend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('role_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('instructor_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('emergency_phone_legend', 'bank_account_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('event_tool_legend', 'emergency_phone_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('rescission_legend', 'event_tool_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField('iban', 'bank_account_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['uuid', 'sacMemberId', 'firstname', 'lastname', 'sectionId', 'dateOfBirth', 'street', 'postal', 'city', 'phone', 'mobile', 'website'], 'name_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('userRole', 'role_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('leiterQualifikation', 'instructor_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['emergencyPhone', 'emergencyPhoneName'], 'emergency_phone_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('avatar', 'frontend_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['calendar_containers', 'calendar_containerp'], 'calendars_legend', PaletteManipulator::POSITION_PREPEND)
    ->addField('admin', 'admin_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration'], 'event_tool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('rescissionCause', 'rescission_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_user');

// Manipulate palette extend
$arrRemove = [
    'alternate_email',
    'alternate_email_2',
];

foreach ($arrRemove as $field) {
    $GLOBALS['TL_DCA']['tl_user']['palettes']['extend'] = str_replace(','.$field, '', $GLOBALS['TL_DCA']['tl_user']['palettes']['extend']);
}

PaletteManipulator::create()
    ->addLegend('frontend_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('bank_account_legend', 'frontend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('role_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('instructor_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('event_tool_legend', 'instructor_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('rescission_legend', 'event_tool_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField('iban', 'bank_account_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['uuid', 'sacMemberId', 'firstname', 'lastname', 'sectionId', 'dateOfBirth', 'street', 'postal', 'city', 'phone', 'mobile', 'website'], 'name_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['hideInFrontendListings', 'userRole'], 'role_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('leiterQualifikation', 'instructor_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['avatar', 'emergencyPhone', 'emergencyPhoneName', 'hobbies', 'introducing'], 'frontend_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['calendar_containers', 'calendar_containerp'], 'calendars_legend', PaletteManipulator::POSITION_PREPEND)
    ->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration'], 'event_tool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('rescissionCause', 'rescission_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('extend', 'tl_user');

// Manipulate palette admin
$arrRemove = [
    'alternate_email',
    'alternate_email_2',
];

foreach ($arrRemove as $field) {
    $GLOBALS['TL_DCA']['tl_user']['palettes']['admin'] = str_replace(','.$field, '', $GLOBALS['TL_DCA']['tl_user']['palettes']['login']);
}
PaletteManipulator::create()
    ->addLegend('admin_legend', 'password_legend', PaletteManipulator::POSITION_AFTER)
    ->addLegend('frontend_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('bank_account_legend', 'frontend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('role_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('instructor_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('event_tool_legend', 'instructor_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('rescission_legend', 'event_tool_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField('iban', 'bank_account_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['uuid', 'sacMemberId', 'firstname', 'lastname', 'dateOfBirth', 'street', 'postal', 'city', 'phone', 'mobile', 'website'], 'name_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['hideInFrontendListings', 'userRole'], 'role_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('leiterQualifikation', 'instructor_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('admin', 'admin_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration'], 'event_tool_legend', PaletteManipulator::POSITION_APPEND)->addField(['rescissionCause'], 'rescission_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('admin', 'tl_user');

// Manipulate palette login
$arrRemove = [
    'name',
    'alternate_email',
    'alternate_email_2',
];

foreach ($arrRemove as $field) {
    $GLOBALS['TL_DCA']['tl_user']['palettes']['login'] = str_replace(','.$field, '', $GLOBALS['TL_DCA']['tl_user']['palettes']['login']);
}

PaletteManipulator::create()
    ->addLegend('frontend_legend', 'backend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('bank_account_legend', 'frontend_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('emergency_phone_legend', 'bank_account_legend', PaletteManipulator::POSITION_BEFORE)
    ->addLegend('event_tool_legend', 'emergency_phone_legend', PaletteManipulator::POSITION_BEFORE)
    //->addLegend('rescission_legend', 'event_tool_legend', PaletteManipulator::POSITION_BEFORE)
    ->addField(['firstname', 'lastname', 'dateOfBirth', 'street', 'postal', 'city', 'phone', 'mobile', 'website'], 'name_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['emergencyPhone', 'emergencyPhoneName'], 'emergency_phone_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('iban', 'bank_account_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['avatar', 'hobbies', 'introducing'], 'frontend_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(['generateMainInstructorContactDataFromDb', 'disableOnlineRegistration'], 'event_tool_legend', PaletteManipulator::POSITION_APPEND)
    //->addField(array('rescissionCause'), 'rescission_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('login', 'tl_user');

// Fields
$GLOBALS['TL_DCA']['tl_user']['fields']['username']['eval']['tl_class'] = 'clr';
$GLOBALS['TL_DCA']['tl_user']['fields']['name']['eval']['tl_class'] = 'clr';
$GLOBALS['TL_DCA']['tl_user']['fields']['email']['eval']['tl_class'] = 'clr';
$GLOBALS['TL_DCA']['tl_user']['fields']['email']['sorting'] = true;
$GLOBALS['TL_DCA']['tl_user']['fields']['tstamp']['sorting'] = true;
$GLOBALS['TL_DCA']['tl_user']['fields']['tstamp']['flag'] = DataContainer::SORT_DAY_ASC;

$GLOBALS['TL_DCA']['tl_user']['fields']['uuid'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory' => false, 'readonly' => true, 'tl_class' => 'w50'],
    'sql'       => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['calendar_containers'] = [
    'exclude'    => true,
    'inputType'  => 'checkbox',
    'foreignKey' => 'tl_calendar_container.title',
    'eval'       => ['multiple' => true],
    'sql'        => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['calendar_containerp'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'options'   => ['create', 'delete'],
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval'      => ['multiple' => true],
    'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['firstname'] = [
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'flag'      => 1,
    'inputType' => 'text',
    'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['lastname'] = [
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'flag'      => 1,
    'inputType' => 'text',
    'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['iban'] = [
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'flag'      => 1,
    'inputType' => 'text',
    'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['sacMemberId'] = [
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'flag'      => 1,
    'inputType' => 'text',
    'eval'      => ['doNotCopy' => true, 'rgxp' => 'sacMemberIdIsUniqueAndValid', 'readonly' => false, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
    'sql'       => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['sectionId'] = [
    'filter'    => true,
    'exclude'   => true,
    'sorting'   => true,
    'inputType' => 'select',
    'eval'      => ['multiple' => true, 'chosen' => true, 'doNotCopy' => true, 'tl_class' => 'clr'],
    'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['dateOfBirth'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'clr wizard'],
    'sql'       => "varchar(11) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['hideInFrontendListings'] = [
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'flag'      => 1,
    'inputType' => 'checkbox',
    'eval'      => ['mandatory' => false, 'tl_class' => 'clr'],
    'sql'       => "varchar(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['gender'] = [
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => ['male', 'female'],
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval'      => ['includeBlankOption' => true, 'tl_class' => 'clr'],
    'sql'       => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['street'] = [
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 255, 'tl_class' => 'clr'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['postal'] = [
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 32, 'tl_class' => 'clr'],
    'sql'       => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['city'] = [
    'exclude'   => true,
    'filter'    => true,
    'search'    => true,
    'sorting'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 255, 'tl_class' => 'clr'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['state'] = [
    'exclude'   => true,
    'sorting'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 64, 'tl_class' => 'clr'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['country'] = [
    'exclude'   => true,
    'filter'    => true,
    'sorting'   => true,
    'inputType' => 'select',
    'eval'      => ['includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql'       => "varchar(2) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['phone'] = [
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'tl_class' => 'clr'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['emergencyPhone'] = [
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 64, 'rgxp' => 'phone', 'mandatory' => false, 'decodeEntities' => true, 'tl_class' => 'clr'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['emergencyPhoneName'] = [
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 255, 'mandatory' => false, 'decodeEntities' => true, 'tl_class' => 'clr'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['mobile'] = [
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'tl_class' => 'clr'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['website'] = [
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'url', 'maxlength' => 255, 'tl_class' => 'clr'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['hobbies'] = [
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['introducing'] = [
    'exclude'   => true,
    'inputType' => 'textarea',
    'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
    'sql'       => 'text NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['leiterQualifikation'] = [
    'exclude'   => true,
    'search'    => true,
    'filter'    => true,
    'inputType' => 'checkboxWizard',
    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['leiterQualifikation'],
    'eval'      => ['tl_class' => 'clr', 'multiple' => true, 'orderField' => 'orderLeiterQualifikation'],
    'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['orderLeiterQualifikation'] = [
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['avatar'] = [
    'exclude'   => true,
    'inputType' => 'fileTree',
    'eval'      => ['doNotCopy' => true, 'filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => '', 'extensions' => '%contao.image.valid_extensions%'],
    'sql'       => 'binary(16) NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['userRole'] = [
    'exclude'   => true,
    'search'    => true,
    'filter'    => true,
    'inputType' => 'select',
    'eval'      => ['chosen' => true, 'tl_class' => 'clr m12', 'includeBlankOption' => true, 'multiple' => true, 'mandatory' => false],
    'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_user']['fields']['disableOnlineRegistration'] = [
    'exclude'   => true,
    'search'    => true,
    'sorting'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'clr'],
    'sql'       => "varchar(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['generateMainInstructorContactDataFromDb'] = [
    'filter'    => true,
    'sorting'   => true,
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['rescissionCause'] = [
    'reference' => &$GLOBALS['TL_LANG']['tl_user']['rescissionCauseOptions'],
    'filter'    => true,
    'sorting'   => true,
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['userRescissionCause'],
    'eval'      => ['includeBlankOption' => true, 'tl_class' => 'clr'],
    'sql'       => "varchar(128) NOT NULL default ''",
];
