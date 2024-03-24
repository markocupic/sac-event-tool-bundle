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

use Contao\BackendUser;
use Contao\System;
use Markocupic\SacEventToolBundle\Controller\ContentElement\UserPortraitController;
use Markocupic\SacEventToolBundle\Controller\ContentElement\UserPortraitListController;

// Palettes
$GLOBALS['TL_DCA']['tl_content']['palettes'][UserPortraitListController::TYPE] = 'name,type,headline;{config_legend},userList_selectMode,userList_queryType,userList_users,userList_userRoles,userList_replacePrivateAdressWithRoleAdress,userList_showFieldsToGuests;{image_legend:hide},imgSize;{jumpTo_legend},jumpTo;{template_legend},userList_template,userList_partial_template;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['palettes'][UserPortraitController::TYPE] = 'name,type,headline;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space;{invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['fields']['jumpTo'] = [
	'exclude'    => true,
	'search'     => true,
	'inputType'  => 'pageTree',
	'foreignKey' => 'tl_page.title',
	'eval'       => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'w50 wizard'],
	'sql'        => "int(10) unsigned NOT NULL default 0",
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_selectMode'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'reference' => &$GLOBALS['TL_LANG']['tl_content'],
	'options'   => ['selectUserRoles', 'selectUsers'],
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
	'sql'       => "char(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_replacePrivateAdressWithRoleAdress'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'options'   => ['email', 'phone', 'mobile', 'street', 'postal', 'city'],
	'eval'      => ['submitOnChange' => false, 'multiple' => true, 'tl_class' => 'clr'],
	'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_users'] = [
	'exclude'    => true,
	'search'     => true,
	'filter'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_user.name',
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
	'eval'       => ['chosen' => true, 'tl_class' => 'clr m12', 'includeBlankOption' => true, 'multiple' => true, 'mandatory' => true],
	'sql'        => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_userRoles'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'eval'      => ['multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
	'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_showFieldsToGuests'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'options'   => ['email', 'phone', 'mobile', 'street', 'postal', 'city'],
	'eval'      => ['multiple' => true, 'tl_class' => 'clr'],
	'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_queryType'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'select',
	'options'   => ['AND', 'OR'],
	'eval'      => ['tl_class' => 'clr'],
	'sql'       => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_template'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_partial_template'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['imgSize'] = [
	'exclude'          => true,
	'inputType'        => 'imageSize',
	'reference'        => &$GLOBALS['TL_LANG']['MSC'],
	'eval'             => ['rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'],
	'options_callback' => static fn() => System::getContainer()->get('contao.image.sizes')->getAllOptions(),
	'sql'              => "varchar(64) NOT NULL default ''",
];
