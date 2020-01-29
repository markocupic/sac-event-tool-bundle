<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Table tl_calendar_events_story
 */
$GLOBALS['TL_DCA']['tl_calendar_events_story'] = [

    // Config
    'config'      => [
        'dataContainer'     => 'Table',
        'enableVersioning'  => true,
        'notCopyable'       => true,
        'onsubmit_callback' => [//
        ],
        'onload_callback'   => [
            ['tl_calendar_events_story', 'setPalettes'],
            ['tl_calendar_events_story', 'deleteUnfinishedAndOldEntries'],
        ],
        'ondelete_callback' => [//
        ],
        'sql'               => [
            'keys' => [
                'id'      => 'primary',
                'eventId' => 'index',
            ],
        ],
    ],

    // List
    'list'        => [
        'sorting'           => [
            'mode'        => 2,
            'fields'      => ['eventStartDate DESC'],
            //'flag'        => 12,
            'panelLayout' => 'filter;sort,search',
        ],
        'label'             => [
            'fields'         => ['publishState', 'title', 'authorName'],
            'showColumns'    => true,
            'label_callback' => ['tl_calendar_events_story', 'addIcon'],
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
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg',
            ],

            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ],

            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],
        ],
    ],

    // Palettes
    'palettes'    => [
        'default' => '{publishState_legend},publishState;{author_legend},addedOn,sacMemberId,authorName;{event_legend},eventId,title,eventTitle,eventSubstitutionText,organizers,text,youtubeId,multiSRC;',
    ],

    // Subpalettes
    'subpalettes' => [//
    ],

    // Fields
    'fields'      => [
        'id'                    => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'eventId'               => [
            'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventId'],
            'foreignKey' => 'tl_calendar_events.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
            'eval'       => ['readonly' => true],
        ],
        'tstamp'                => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'publishState'          => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['publishState'],
            'filter'    => true,
            'default'   => 1,
            'reference' => $GLOBALS['TL_LANG']['tl_calendar_events_story']['publishStateRef'],
            'inputType' => 'select',
            'options'   => ['1', '2', '3'],
            'eval'      => ['tl_class' => 'clr', 'submitOnChange' => true],
            'sql'       => "char(1) NOT NULL default '1'",
        ],
        'authorName'            => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['authorName'],
            'filter'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'eventTitle'            => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventTitle'],
            'inputType' => 'text',
            'eval'      => ['doNotCopy' => true, 'mandatory' => true, 'readonly' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'eventSubstitutionText' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventSubstitutionText'],
            'inputType' => 'text',
            'eval'      => ['doNotCopy' => true, 'mandatory' => false, 'readonly' => true, 'maxlength' => 64, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'eventStartDate'        => [
            'label'   => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventStartDate'],
            'sorting' => true,
            'flag'    => 6,
            'sql'     => "int(10) unsigned NOT NULL default '0'",
        ],
        'eventEndDate'          => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'eventDates'            => [
            'sql' => "blob NULL",
        ],
        'title'                 => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['title'],
            'inputType' => 'text',
            'eval'      => ['doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'text'                  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['text'],
            'inputType' => 'textarea',
            'eval'      => ['doNotCopy' => true, 'mandatory' => true, 'tl_class' => 'clr'],
            'sql'       => "mediumtext NULL",
        ],
        'youtubeId'             => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['youtubeId'],
            'inputType' => 'text',
            'eval'      => ['doNotCopy' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'sacMemberId'           => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['sacMemberId'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'multiSRC'              => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['multiSRC'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['doNotCopy' => true, 'isGallery' => true, 'extensions' => 'jpg,jpeg', 'multiple' => true, 'fieldType' => 'checkbox', 'orderField' => 'orderSRC', 'files' => true, 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'       => "blob NULL",
        ],
        'orderSRC'              => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['orderSRC'],
            'eval'  => ['doNotCopy' => true],
            'sql'   => "blob NULL",
        ],
        'organizers'            => [
            'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['organizers'],
            'exclude'    => true,
            'search'     => true,
            'filter'     => true,
            'sorting'    => true,
            'inputType'  => 'select',
            'foreignKey' => 'tl_event_organizer.title',
            'relation'   => ['type' => 'hasMany', 'load' => 'lazy'],
            'eval'       => ['multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'],
            'sql'        => "blob NULL",
        ],
        'securityToken'         => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'addedOn'               => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['addedOn'],
            'default'   => time(),
            'flag'      => 8,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'date', 'mandatory' => true, 'doNotCopy' => false, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "int(10) unsigned NULL",
        ],
    ],
];

