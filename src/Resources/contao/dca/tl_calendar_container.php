<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$GLOBALS['TL_DCA']['tl_calendar_container'] = [

    // Config
    'config'      => [
        'dataContainer'    => 'Table',
        'ctable'           => ['tl_calendar'],
        'switchToEdit'     => true,
        'enableVersioning' => true,
        'onload_callback'  => [
            ['tl_calendar_container', 'checkPermission'],
        ],
        'sql'              => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],

    // List
    'list'        => [
        'sorting'           => [
            'mode'            => 1,
            'fields'          => ['title'],
            'flag'            => 2,
            'panelLayout'     => 'filter;search,limit',
            'disableGrouping' => true
        ],
        'label'             => [
            'fields' => ['title'],
            'format' => '%s'
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ]
        ],
        'operations'        => [
            'edit'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['edit'],
                'href'  => 'table=tl_calendar',
                'icon'  => 'edit.svg'
            ],
            'editheader' => [
                'label'           => &$GLOBALS['TL_LANG']['tl_calendar_container']['editheader'],
                'href'            => 'act=edit',
                'icon'            => 'header.svg',
                'button_callback' => ['tl_calendar_container', 'editHeader']
            ],
            'copy'       => [
                'label'           => &$GLOBALS['TL_LANG']['tl_calendar_container']['copy'],
                'href'            => 'act=copy',
                'icon'            => 'copy.svg',
                'button_callback' => ['tl_calendar_container', 'copyCalendarContainer']
            ],
            'delete'     => [
                'label'           => &$GLOBALS['TL_LANG']['tl_calendar_container']['delete'],
                'href'            => 'act=delete',
                'icon'            => 'delete.svg',
                'attributes'      => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
                'button_callback' => ['tl_calendar_container', 'deleteCalendarContainer']
            ],
            'show'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg'
            ]
        ]
    ],

    // Palettes
    'palettes'    => [
        '__selector__' => [],
        'default'      => '{title_legend},title'
    ],

    // Subpalettes
    'subpalettes' => [
        //
    ],

    // Fields
    'fields'      => [
        'id'     => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ],
        'title'  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_container']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ]
    ]
];
