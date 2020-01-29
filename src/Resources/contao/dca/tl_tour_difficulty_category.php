<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$GLOBALS['TL_DCA']['tl_tour_difficulty_category'] = [
    'config'   => [
        'dataContainer'    => 'Table',
        'ctable'           => ['tl_tour_difficulty'],
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list'     => [
        'sorting'           => [
            'mode'        => 1,
            'fields'      => ['title ASC'],
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label'             => [
            'fields'      => ['title'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ],
        ],
        'operations'        => [
            'edit'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['edit'],
                'href'  => 'table=tl_tour_difficulty',
                'icon'  => 'edit.svg',
            ],
            'editheader' => [
                'label'           => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['editheader'],
                'href'            => 'table=tl_tour_difficulty_category&amp;act=edit',
                'icon'            => 'header.svg',
                'button_callback' => ['tl_tour_difficulty_category', 'editHeader'],
            ],
            'copy'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ],
            'delete'     => [
                'label'      => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ],
        ],
    ],
    'palettes' => [
        'default' => 'title',
    ],

    'fields' => [
        'id'     => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
    ],
];

