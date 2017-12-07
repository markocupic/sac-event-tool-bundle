<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */


$GLOBALS['TL_DCA']['tl_calendar_container'] = array
(

    // Config
    'config' => array
    (
        'dataContainer' => 'Table',
        //'notDeletable'                => true,
        'ctable' => array('tl_calendar'),
        'switchToEdit' => true,
        'enableVersioning' => true,
        'onload_callback' => array
        (
            array('tl_calendar_container', 'checkPermission'),
            //array('tl_calendar_container', 'generateFeed')
        ),
        'onsubmit_callback' => array
        (//array('tl_calendar_container', 'scheduleUpdate')
        ),
        'sql' => array
        (
            'keys' => array
            (
                'id' => 'primary'
            )
        )
    ),

    // List
    'list' => array
    (
        'sorting' => array
        (
            'mode' => 1,
            'fields' => array('title'),
            'flag' => 2,
            'panelLayout' => 'filter;search,limit',
            'disableGrouping' => true
        ),
        'label' => array
        (
            'fields' => array('title'),
            'format' => '%s'
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations' => array
        (
            'edit' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['edit'],
                'href' => 'table=tl_calendar',
                'icon' => 'edit.svg'
            ),
            'editheader' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.svg',
                'button_callback' => array('tl_calendar_container', 'editHeader')
            ),
            'copy' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.svg',
                'button_callback' => array('tl_calendar_container', 'copyCalendarContainer')
            ),
            'delete' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
                'button_callback' => array('tl_calendar_container', 'deleteCalendarContainer')
            ),
            'show' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['show'],
                'href' => 'act=show',
                'icon' => 'show.svg'
            )
        )
    ),

    // Palettes
    'palettes' => array
    (
        '__selector__' => array(),
        'default' => '{title_legend},title'
    ),

    // Subpalettes
    'subpalettes' => array
    (
        //'protected'                   => 'groups',
        //'allowComments'               => 'notify,sortOrder,perPage,moderate,bbcode,requireLogin,disableCaptcha'
    ),

    // Fields
    'fields' => array
    (
        'id' => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp' => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'title' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_container']['title'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        )
    )
);
