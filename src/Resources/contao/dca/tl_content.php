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

use Contao\BackendUser;
use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\Dca\TlContent;

if ('sac_calendar_events_tool' === Input::get('do')) {
    $GLOBALS['TL_DCA']['tl_content']['config']['ptable'] = 'tl_calendar_events';
    $GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] = [
        TlContent::class,
        'checkPermission',
    ];
    $GLOBALS['TL_DCA']['tl_content']['list']['operations']['toggle']['button_callback'] = [
        TlContent::class,
        'toggleIcon',
    ];
}

// Callbacks
$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] = [
    TlContent::class,
    'setPalette',
];

// Palettes
$GLOBALS['TL_DCA']['tl_content']['palettes']['user_portrait_list'] = 'name,type,headline;{config_legend},userList_selectMode,userList_queryType,userList_users,userList_userRoles,userList_replacePrivateAdressWithRoleAdress,userList_showFieldsToGuests;{image_legend:hide},imgSize;{jumpTo_legend},jumpTo;{template_legend},userList_template,userList_partial_template;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['palettes']['user_portrait'] = 'name,type,headline;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['palettes']['cabanne_sac_list'] = '{type_legend},type,headline,cabanneSac;{image_legend},singleSRC,size,imagemargin,fullsize,overwriteMeta;{link_legend},jumpTo;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['palettes']['cabanne_sac_detail'] = '{type_legend},type,headline,cabanneSac;{image_legend},singleSRC,size,imagemargin,fullsize,overwriteMeta;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID;{invisible_legend:hide},invisible,start,stop';

// Fields
$GLOBALS['TL_DCA']['tl_content']['fields']['cabanneSac'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['cabanneSac'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'select',
    'options_callback' => [
        TlContent::class,
        'getCabannes',
    ],
    'eval' => ['mandatory' => true, 'maxlength' => 200, 'tl_class' => 'w50 clr'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['jumpTo'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['jumpTo'],
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 255, 'fieldType' => 'radio', 'filesOnly' => true, 'tl_class' => 'w50 wizard'],
    'wizard' => [['tl_content', 'pagePicker']],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_selectMode'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['userList_selectMode'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'reference' => &$GLOBALS['TL_LANG']['tl_content'],
    'options' => ['selectUserRoles', 'selectUsers'],
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql' => "char(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_replacePrivateAdressWithRoleAdress'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['userList_replacePrivateAdressWithRoleAdress'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'options' => ['email', 'phone', 'mobile', 'street', 'postal', 'city'],
    'eval' => ['submitOnChange' => false, 'multiple' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_users'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['userList_users'],
    'exclude' => true,
    'search' => true,
    'filter' => true,
    'inputType' => 'select',
    'foreignKey' => 'tl_user.name',
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
    'eval' => ['chosen' => true, 'tl_class' => 'clr m12', 'includeBlankOption' => true, 'multiple' => true, 'mandatory' => true],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_userRoles'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['userList_userRoles'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'options_callback' => [
        TlContent::class,
        'optionsCallbackUserRoles',
    ],
    'eval' => ['multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_showFieldsToGuests'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['userList_showFieldsToGuests'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'options' => ['email', 'phone', 'mobile', 'street', 'postal', 'city'],
    'eval' => ['multiple' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_queryType'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['userList_queryType'],
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'options' => ['AND', 'OR'],
    'eval' => ['tl_class' => 'clr'],
    'sql' => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['userList_template'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [
        TlContent::class,
        'getUserListTemplates',
    ],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_partial_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['userList_partial_template'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => [
        TlContent::class,
        'getUserListPartialTemplates',
    ],
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['imgSize'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['imgSize'],
    'exclude' => true,
    'inputType' => 'imageSize',
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval' => ['rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'],
    'options_callback' => static fn () => System::getContainer()->get('contao.image.image_sizes')->getOptionsForUser(BackendUser::getInstance()),
    'sql' => "varchar(64) NOT NULL default ''",
];
