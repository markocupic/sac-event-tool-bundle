<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
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
        'ptable'            => 'tl_calendar_events',
        'enableVersioning'  => true,
        'notCopyable'       => true,
        'onsubmit_callback' => array
        (//
        ),
        'onload_callback'   => array
        (
            array('tl_calendar_events_story', 'onloadCallback'),
        ),
        'ondelete_callback' => array
        (//
        ),
        'sql'               => array
        (
            'keys' => array
            (
                'id'  => 'primary',
                'pid' => 'index',
            )
        )
    ),

    // List
    'list'        => array
    (
        'sorting'           => array
        (
            'mode'        => 2,
            'fields'      => array('title'),
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search'
        ),
        'label'             => array
        (
            'fields'         => array('publishState', 'title', 'authorName'),
            'showColumns'    => true,
            'label_callback' => array('tl_calendar_events_story', 'addIcon')
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ),
        ),
        'operations'        => array
        (
            'edit' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ),

            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),

            'toggle' => array
            (
                'label'           => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['toggle'],
                'icon'            => 'visible.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => array('tl_calendar_events_story', 'toggleIcon')
            ),

            'show' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg'
            )

        )
    ),

    // Palettes
    'palettes'    => array
    (
        'default' => '{publishState_legend},publishState;{author_legend},addedOn,sacMemberId,authorName;{event_legend},pid,title,text,youtubeId,multiSRC;',
    ),

    // Subpalettes
    'subpalettes' => array
    (//
    ),

    // Fields
    'fields'      => array
    (
        'id'           => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'pid'          => array
        (
            'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['pid'],
            'foreignKey' => 'tl_calendar_events.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
            'eval'       => array('readonly' => true),
        ),
        'addedOn'      => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['addedOn'],
            'default'   => time(),
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'date', 'mandatory' => true, 'doNotCopy' => false, 'datepicker' => true, 'tl_class' => 'w50 wizard'),
            'sql'       => "int(10) unsigned NULL"
        ),
        'tstamp'       => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'publishState' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['publishState'],
            'filter'    => true,
            'default'   => 1,
            'reference' => $GLOBALS['TL_LANG']['tl_calendar_events_story']['publishStateRef'],
            'inputType' => 'select',
            'options'   => array('1', '2', '3'),
            'eval'      => array('tl_class' => 'clr', 'submitOnChange' => true),
            'sql'       => "char(1) NOT NULL default '1'"
        ),
        'authorName'   => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['authorName'],
            'inputType' => 'text',
            'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'title'        => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['title'],
            'inputType' => 'text',
            'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'text'         => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['text'],
            'inputType' => 'textarea',
            'eval'      => array('doNotCopy' => true, 'mandatory' => true, 'tl_class' => 'clr'),
            'sql'       => "mediumtext NULL"
        ),
        'youtubeId'    => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['youtubeId'],
            'inputType' => 'text',
            'eval'      => array('doNotCopy' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'sacMemberId'  => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['sacMemberId'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'multiSRC'     => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['multiSRC'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => array('doNotCopy' => true, 'isGallery' => true, 'extensions' => 'jpg,jpeg', 'multiple' => true, 'fieldType' => 'checkbox', 'orderField' => 'orderSRC', 'files' => true, 'mandatory' => false, 'tl_class' => 'clr'),
            'sql'       => "blob NULL",
        ),
        'orderSRC'     => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['orderSRC'],
            'eval'  => array('doNotCopy' => true),
            'sql'   => "blob NULL"
        )
    )
);

