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
$GLOBALS['TL_DCA']['tl_calendar_events_story'] = array
(

    // Config
    'config'      => array
    (
        'dataContainer'     => 'Table',
        'enableVersioning'  => true,
        'notCopyable'       => true,
        'onsubmit_callback' => array
        (//
        ),
        'onload_callback'   => array
        (
            array('tl_calendar_events_story', 'setPalettes'),
            array('tl_calendar_events_story', 'deleteUnfinishedAndOldEntries'),
        ),
        'ondelete_callback' => array
        (//
        ),
        'sql'               => array
        (
            'keys' => array
            (
                'id'      => 'primary',
                'eventId' => 'index',
            ),
        ),
    ),

    // List
    'list'        => array
    (
        'sorting'           => array
        (
            'mode'        => 2,
            'fields'      => array('eventStartDate DESC'),
            //'flag'        => 12,
            'panelLayout' => 'filter;sort,search',
        ),
        'label'             => array
        (
            'fields'         => array('publishState', 'title', 'authorName'),
            'showColumns'    => true,
            'label_callback' => array('tl_calendar_events_story', 'addIcon'),
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ),
        ),
        'operations'        => array
        (
            'edit' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg',
            ),

            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ),

            'show' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ),
        ),
    ),

    // Palettes
    'palettes'    => array
    (
        'default' => '{publishState_legend},publishState;{author_legend},addedOn,sacMemberId,authorName;{event_legend},eventId,title,eventTitle,eventSubstitutionText,organizers,text,youtubeId,multiSRC;',
    ),

    // Subpalettes
    'subpalettes' => array
    (//
    ),

    // Fields
    'fields'      => array
    (
        'id'                    => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'eventId'               => array
        (
            'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventId'],
            'foreignKey' => 'tl_calendar_events.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
            'eval'       => array('readonly' => true),
        ),
        'tstamp'                => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'publishState'          => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['publishState'],
            'filter'    => true,
            'default'   => 1,
            'reference' => $GLOBALS['TL_LANG']['tl_calendar_events_story']['publishStateRef'],
            'inputType' => 'select',
            'options'   => array('1', '2', '3'),
            'eval'      => array('tl_class' => 'clr', 'submitOnChange' => true),
            'sql'       => "char(1) NOT NULL default '1'",
        ),
        'authorName'            => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['authorName'],
            'filter'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'eventTitle'            => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventTitle'],
            'inputType' => 'text',
            'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'readonly' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'eventSubstitutionText' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventSubstitutionText'],
            'inputType' => 'text',
            'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'readonly' => true, 'maxlength' => 64, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'eventStartDate'        => array
        (
            'label'   => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventStartDate'],
            'sorting' => true,
            'flag'    => 6,
            'sql'     => "int(10) unsigned NOT NULL default '0'",
        ),
        'eventEndDate'          => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'eventDates'            => array
        (
            'sql' => "blob NULL",
        ),
        'title'                 => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['title'],
            'inputType' => 'text',
            'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'text'                  => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['text'],
            'inputType' => 'textarea',
            'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'tl_class' => 'clr'),
            'sql'       => "mediumtext NULL",
        ),
        'youtubeId'             => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['youtubeId'],
            'inputType' => 'text',
            'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'sacMemberId'           => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['sacMemberId'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'multiSRC'              => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['multiSRC'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => array('doNotCopy' => true, 'isGallery' => true, 'extensions' => 'jpg,jpeg', 'multiple' => true, 'fieldType' => 'checkbox', 'orderField' => 'orderSRC', 'files' => true, 'mandatory' => false, 'tl_class' => 'clr'),
            'sql'       => "blob NULL",
        ),
        'orderSRC'              => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['orderSRC'],
            'eval'  => array('doNotCopy' => true),
            'sql'   => "blob NULL",
        ),
        'organizers'            => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['organizers'],
            'exclude'    => true,
            'search'     => true,
            'filter'     => true,
            'sorting'    => true,
            'inputType'  => 'select',
            'foreignKey' => 'tl_event_organizer.title',
            'relation'   => array('type' => 'hasMany', 'load' => 'lazy'),
            'eval'       => array('multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'),
            'sql'        => "blob NULL",
        ),
        'securityToken'         => array(
            'sql' => "varchar(255) NOT NULL default ''",
        ),
        'addedOn'               => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['addedOn'],
            'default'   => time(),
            'flag'      => 8,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'date', 'mandatory' => true, 'doNotCopy' => false, 'datepicker' => true, 'tl_class' => 'w50 wizard'),
            'sql'       => "int(10) unsigned NULL",
        ),
    ),
);

