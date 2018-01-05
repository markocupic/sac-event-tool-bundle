<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */


/**
 * Table tl_event_release_level_policy_package
 */
$GLOBALS['TL_DCA']['tl_event_release_level_policy_package'] = array
(

    // Config
    'config'   => array
    (
        'dataContainer'    => 'Table',
        'ctable'           => array('tl_event_release_level_policy'),
        'switchToEdit'     => true,
        'enableVersioning' => true,
        'sql'              => array
        (
            'keys' => array
            (
                'id' => 'primary',
            ),
        ),
    ),

    // List
    'list'     => array
    (
        'sorting'           => array
        (
            'mode'        => 1,
            'fields'      => array('title'),
            'flag'        => 1,
            'panelLayout' => 'filter;search,limit',
        ),
        'label'             => array
        (
            'fields' => array('title'),
            'format' => '%s',
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
            'edit'       => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['edit'],
                'href'  => 'table=tl_event_release_level_policy',
                'icon'  => 'edit.svg',
            ),
            'editheader' => array
            (
                'label'           => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['editheader'],
                'href'            => 'table=tl_event_release_level_policy_package&amp;act=edit',
                'icon'            => 'header.svg',
                'button_callback' => array('tl_event_release_level_policy_package', 'editHeader'),
            ),
            'copy'       => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['copy'],
                'href'  => 'act=paste&amp;mode=copy',
                'icon'  => 'copy.svg',
            ),
            'delete'     => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ),
            'show'       => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ),
        ),
    ),

    // Palettes
    'palettes' => array
    (
        'default' => '{title_legend},title;',
    ),

    // Fields
    'fields'   => array
    (
        'id'     => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'tstamp' => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'title'  => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy_package']['title'],
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'flag'      => 1,
            'eval'      => array('mandatory' => true, 'rgxp' => 'alnum', 'maxlength' => 64, 'tl_class' => 'w50'),
            'sql'       => "varchar(64) NULL",
        ),
    ),
);


