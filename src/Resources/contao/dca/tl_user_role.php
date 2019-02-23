<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */


$GLOBALS['TL_DCA']['tl_user_role'] = array
(
    'config'   => array
    (
        'dataContainer'    => 'Table',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'sql'              => array
        (
            'keys' => array
            (
                'id'  => 'primary',
                'pid' => 'index',
            ),
        ),
    ),
    'list'     => array
    (
        'sorting'           => array
        (
            'mode'                  => 5,
            'fields'                => array('title', 'email'),
            'format'                => '%s %s',
            //'flag'                  => 1,
            'panelLayout'           => 'filter;search,limit',
            'paste_button_callback' => array('tl_user_role', 'pasteTag'),
        ),
        'label'             => array
        (
            'fields'         => array('title', 'email'),
            'showColumns'    => true,
            'label_callback' => array('tl_user_role', 'checkForUsage'),
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ),
        ),
        'operations'        => array
        (
            'edit'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_user_role']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ),
            'copy'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_user_role']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ),
            'cut'    => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_tour_type']['cut'],
                'href'       => 'act=paste&mode=cut',
                'icon'       => 'cut.gif',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_user_role']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ),
        ),
    ),
    'palettes' => array
    (
        'default' => 'title,belongsToExecutiveBoard,belongsToBeauftragteStammsektion;{address_legend},street,postal,city,phone,mobile,email',
    ),

    'fields' => array
    (
        'id'                               => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'pid'                              => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'sorting'                          => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['sorting'],
            'exclude'   => true,
            'search'    => false,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 10),
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ),
        'tstamp'                           => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'title'                            => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'belongsToExecutiveBoard'          => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['belongsToExecutiveBoard'],
            'exclude'   => true,
            'filter'    => true,
            'sorting'   => true,
            'inputType' => 'checkbox',
            'eval'      => array('mandatory' => false, 'tl_class' => 'clr'),
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'belongsToBeauftragteStammsektion' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['belongsToBeauftragteStammsektion'],
            'exclude'   => true,
            'filter'    => true,
            'sorting'   => true,
            'inputType' => 'checkbox',
            'eval'      => array('mandatory' => false, 'tl_class' => 'clr'),
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'email'                            => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['email'],
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => array('assignTo' => 'tl_user.email', 'mandatory' => false, 'maxlength' => 255, 'rgxp' => 'email', 'unique' => false, 'decodeEntities' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'street'                           => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['street'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('assignTo' => 'tl_user.street', 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'postal'                           => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['postal'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('assignTo' => 'tl_user.postal', 'maxlength' => 32, 'tl_class' => 'w50'),
            'sql'       => "varchar(32) NOT NULL default ''"
        ),
        'city'                             => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['city'],
            'exclude'   => true,
            'filter'    => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('assignTo' => 'tl_user.city', 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'phone'                            => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['phone'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('assignTo' => 'tl_user.phone', 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(64) NOT NULL default ''"
        ),
        'mobile'                           => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_role']['mobile'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('assignTo' => 'tl_user.mobile', 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(64) NOT NULL default ''"
        ),
    ),
);
