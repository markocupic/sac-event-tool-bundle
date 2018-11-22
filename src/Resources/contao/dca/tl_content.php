<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */


if (Input::get('do') == 'sac_calendar_events_tool')
{
    $GLOBALS['TL_DCA']['tl_content']['config']['ptable'] = 'tl_calendar_events';
    $GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] = array('tl_content_calendar', 'checkPermission');
    $GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] = array('tl_content_calendar', 'generateFeed');
    $GLOBALS['TL_DCA']['tl_content']['list']['operations']['toggle']['button_callback'] = array('tl_content_calendar', 'toggleIcon');
}

// Callbacks
$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] = array('tl_content_sac_event_tool', 'setPalette');


// Palettes
$GLOBALS['TL_DCA']['tl_content']['palettes']['userPortraitList'] = 'name,type,headline;{config_legend},userList_selectMode,userList_queryType,userList_users,userList_userRoles,userList_replacePrivateAdressWithRoleAdress,userList_showFieldsToGuests;{image_legend:hide},imgSize;{jumpTo_legend},jumpTo;{template_legend},userList_template,userList_partial_template;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['palettes']['userPortrait'] = 'name,type,headline;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['palettes']['cabanneSacList'] = '{type_legend},type,headline,cabanneSac;{image_legend},singleSRC,size,imagemargin,fullsize,overwriteMeta;{link_legend},jumpTo;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['palettes']['cabanneSacDetail'] = '{type_legend},type,headline,cabanneSac;{image_legend},singleSRC,size,imagemargin,fullsize,overwriteMeta;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID;{invisible_legend:hide},invisible,start,stop';


// Fields
$GLOBALS['TL_DCA']['tl_content']['fields']['cabanneSac'] = array
(
    'label'            => &$GLOBALS['TL_LANG']['tl_content']['cabanneSac'],
    'exclude'          => true,
    'search'           => true,
    'inputType'        => 'select',
    'options_callback' => array('tl_content_sac_event_tool', 'getCabannes'),
    'eval'             => array('mandatory' => true, 'maxlength' => 200, 'tl_class' => 'w50 clr'),
    'sql'              => "int(10) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['jumpTo'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_content']['jumpTo'],
    'exclude'   => true,
    'search'    => true,
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 255, 'fieldType' => 'radio', 'filesOnly' => true, 'tl_class' => 'w50 wizard'),
    'wizard'    => array
    (
        array('tl_content', 'pagePicker'),
    ),
    'sql'       => "varchar(255) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_selectMode'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_content']['userList_selectMode'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'select',
    'reference' => &$GLOBALS['TL_LANG']['tl_content'],
    'options'   => array('selectUserRoles', 'selectUsers'),
    'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
    'sql'       => "char(128) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_replacePrivateAdressWithRoleAdress'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_content']['userList_replacePrivateAdressWithRoleAdress'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'options' => array('email','phone','mobile','street','postal','city'),
    'eval'      => array('submitOnChange' => false, 'multiple' => true, 'tl_class' => 'clr'),
    'sql'        => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_users'] = array(
    'label'      => &$GLOBALS['TL_LANG']['tl_content']['userList_users'],
    'exclude'    => true,
    'search'     => true,
    'filter'     => true,
    'inputType'  => 'select',
    'foreignKey' => "tl_user.name",
    'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
    'eval'       => array('chosen' => true, 'tl_class' => 'clr m12', 'includeBlankOption' => true, 'multiple' => true, 'mandatory' => true),
    'sql'        => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_userRoles'] = array
(
    'label'            => &$GLOBALS['TL_LANG']['tl_content']['userList_userRoles'],
    'exclude'          => true,
    'filter'           => true,
    'inputType'        => 'select',
    'options_callback' => array('tl_content_sac_event_tool', 'optionsCallbackUserRoles'),
    'eval'             => array('multiple' => true, 'chosen' => true, 'tl_class' => 'clr'),
    'sql'              => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_showFieldsToGuests'] = array
(
    'label'            => &$GLOBALS['TL_LANG']['tl_content']['userList_showFieldsToGuests'],
    'exclude'          => true,
    'filter'           => true,
    'inputType'        => 'checkbox',
    'options' => array('email','phone','mobile','street', 'postal', 'city'),
    'eval'             => array('multiple' => true, 'tl_class' => 'clr'),
    'sql'              => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_queryType'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_content']['userList_queryType'],
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'select',
    'options'   => array('AND', 'OR'),
    'eval'      => array('tl_class' => 'clr'),
    'sql'       => "varchar(10) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_template'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_content']['userList_template'],
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => array('tl_content_sac_event_tool', 'getUserListTemplates'),
    'eval'                    => array('tl_class'=>'w50'),
    'sql'                     => "varchar(64) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_content']['fields']['userList_partial_template'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_content']['userList_partial_template'],
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => array('tl_content_sac_event_tool', 'getUserListPartialTemplates'),
    'eval'                    => array('tl_class'=>'w50'),
    'sql'                     => "varchar(64) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_content']['fields']['imgSize'] = array
(
    'label'            => &$GLOBALS['TL_LANG']['tl_module']['imgSize'],
    'exclude'          => true,
    'inputType'        => 'imageSize',
    'reference'        => &$GLOBALS['TL_LANG']['MSC'],
    'eval'             => array('rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'),
    'options_callback' => function () {
        return System::getContainer()->get('contao.image.image_sizes')->getOptionsForUser(BackendUser::getInstance());
    },
    'sql'              => "varchar(64) NOT NULL default ''",
);