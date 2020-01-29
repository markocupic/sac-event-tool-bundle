<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Table tl_event_release_level_policy_package
 */
$GLOBALS['TL_DCA']['tl_event_release_level_policy_package'] = [

    // Config
    'config'   => [
        'dataContainer'    => 'Table',
        'ctable'           => ['tl_event_release_level_policy'],
        'switchToEdit'     => true,
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    // List
    'list'     => [
        'sorting'           => [
            'mode'        => 1,
            'fields'      => ['title'],
            'flag'        => 1,
            'panelLayout' => 'filter;search,limit',
        ],
        'label'             => [
            'fields' => ['title'],
            'format' => '%s',
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [
            'edit'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['edit'],
                'href'  => 'table=tl_event_release_level_policy',
                'icon'  => 'edit.svg',
            ],
            'editheader' => [
                'label'           => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['editheader'],
                'href'            => 'table=tl_event_release_level_policy_package&amp;act=edit',
                'icon'            => 'header.svg',
                'button_callback' => ['tl_event_release_level_policy_package', 'editHeader'],
            ],
            'copy'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['copy'],
                'href'  => 'act=paste&amp;mode=copy',
                'icon'  => 'copy.svg',
            ],
            'delete'     => [
                'label'      => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ],
            'show'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],
        ],
    ],

    // Palettes
    'palettes' => [
        'default' => '{title_legend},title;',
    ],

    // Fields
    'fields'   => [
        'id'     => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['title'],
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'flag'      => 1,
            'eval'      => ['mandatory' => true, 'rgxp' => 'alnum', 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NULL",
        ],
    ],
];


