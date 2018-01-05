<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */


$GLOBALS['TL_DCA']['tl_tour_difficulty'] = array
(
    'config' => array
    (
        'dataContainer'      => 'Table',
        'ptable'             => 'tl_tour_difficulty_category',
        'doNotCopyRecords'   => true,
        'enableVersioning'   => true,
        'switchToEdit'       => true,
        'doNotDeleteRecords' => true,
        'sql'                => array
        (
            'keys' => array
            (
                'id' => 'primary',
            ),
        ),
    ),

    'list'     => array
    (
        'sorting'           => array
        (
            'mode'                  => 4,
            'fields'                => array('code ASC'),
            'flag'                  => 1,
            'panelLayout'           => 'filter;sort,search,limit',
            'headerFields'          => array('level', 'title'),
            'disableGrouping'       => true,
            'child_record_callback' => array('tl_tour_difficulty', 'listDifficulties'),
        ),
        'label'             => array
        (
            'fields'      => array('title', 'shortcut'),
            'showColumns' => true,
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ),
        ),
        'operations'        => array
        (
            'edit'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ),
            'copy'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ),
        ),
    ),
    'palettes' => array
    (
        'default' => 'code,shortcut,title,description',
    ),

    'fields' => array
    (
        'id'          => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'pid'         => array
        (
            'foreignKey' => 'tl_tour_difficulty_category.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
        ),
        'sorting'     => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'tstamp'      => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'shortcut'    => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['shortcut'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'title'       => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'code'        => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['code'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'description' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['description'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'textarea',
            'eval'      => array('mandatory' => true),
            'sql'       => "text NULL",
        ),
    ),
);
