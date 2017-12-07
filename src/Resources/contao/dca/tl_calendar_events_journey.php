<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */



$GLOBALS['TL_DCA']['tl_calendar_events_journey'] = array
(
    /************************************************************************************
     *         CONFIGURATIONS
     ************************************************************************************/
    'config' => array
    (
        'dataContainer' => 'Table',
        'enableVersioning' => true,
        'switchToEdit' => true,
        'sql' => array
        (
            'keys' => array
            (
                'id' => 'primary'
            )
        )
    ),

    'list' => array
    (
        'sorting' => array
        (
            'mode' => 1,
            'fields' => array('title ASC'),
            'flag' => 1,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label' => array
        (
            'fields' => array('title'),
            'showColumns' => true,
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"'
            )
        ),
        'operations' => array
        (
            'edit' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_journey']['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.svg'
            ),
            'copy' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_journey']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.gif'
            ),
            'delete' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_journey']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
            )
        )
    ),
    'palettes' => array
    (
        'default' => 'title'
    ),

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
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_journey']['title'],
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'filter' => true,
            'inputType' => 'text',
            'eval' => array('mandatory' => true),
            'sql' => "varchar(255) NOT NULL default ''"
        )
    )
);

