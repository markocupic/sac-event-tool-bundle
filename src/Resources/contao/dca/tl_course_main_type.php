<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$GLOBALS['TL_DCA']['tl_course_main_type'] = [
    'config' => [
        'dataContainer'    => 'Table',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],

    'list'     => [
        'sorting'           => [
            'mode'        => 2,
            'fields'      => ['code ASC'],
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit'
        ],
        'label'             => [
            'fields'      => ['code', 'name'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"'
            ]
        ],
        'operations'        => [
            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_course_main_type']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif'
            ],
            'copy'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_news']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif'
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_course_main_type']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
            ]
        ]
    ],
    'palettes' => [
        'default' => 'code,name'
    ],

    'fields' => [
        'id'     => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp' => [
            'label' => &$GLOBALS['TL_LANG']['tl_course_main_type']['tstamp'],
            'flag'  => 6,
            'sql'   => "int(10) unsigned NOT NULL default '0'"

        ],
        'code'   => [
            'label'     => &$GLOBALS['TL_LANG']['tl_course_main_type']['code'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'select',
            'options'   => range(1, 10),
            'eval'      => ['mandatory' => true, 'unique' => true],
            'sql'       => "int(10) unsigned NOT NULL default '0'"
        ],
        'name'   => [
            'label'     => &$GLOBALS['TL_LANG']['tl_course_main_type']['name'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255],
            'sql'       => "varchar(255) NOT NULL default ''"
        ]
    ]
];
