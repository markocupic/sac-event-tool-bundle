<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Table tl_calendar_events_instructor
 */
$GLOBALS['TL_DCA']['tl_calendar_events_instructor'] = [

    'config'      => [
        'dataContainer'     => 'Table',
        'notCopyable'       => true,
        'ptable'            => 'tl_calendar_events',
        // Do not copy nor delete records, if an item has been deleted!
        'onload_callback'   => [//
        ],
        'onsubmit_callback' => [],
        'ondelete_callback' => [],
        'sql'               => [
            'keys' => [
                'id'     => 'primary',
                'pid'    => 'index',
                'userId' => 'index'
            ],
        ],
    ],
    // Buttons callback
    'edit'        => [//'buttons_callback' => array(array('tl_calendar_events_instructor', 'buttonsCallback')),
    ],

    // List
    'list'        => [
        'sorting'           => [//
        ],
        'label'             => [//
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
            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ],
        ],
    ],

    // Palettes
    'palettes'    => [],

    // Subpalettes
    'subpalettes' => [],

    // Fields
    'fields'      => [
        'id'               => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        // Parent: tl_calendar_events.id
        'pid'              => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'tstamp'           => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        // Parent tl_user.id
        'userId'           => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'isMainInstructor' => [
            'sql' => "char(1) NOT NULL default ''",
        ],
    ],
];

