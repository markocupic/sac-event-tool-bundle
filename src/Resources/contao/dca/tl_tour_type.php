<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$GLOBALS['TL_DCA']['tl_tour_type'] = array
(
    'config'   => array
    (
        'dataContainer'    => 'Table',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'sql'              => array
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
            'mode'                  => 5,
            'fields'                => array('sorting'),
            'flag'                  => 1,
            'panelLayout'           => 'filter;search,limit',
            'paste_button_callback' => array('tl_tour_type', 'pasteTag'),
        ),
        'label'             => array
        (
            'fields' => array('shortcut', 'title'),
            'format' => '%s %s',
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
                'label' => &$GLOBALS['TL_LANG']['tl_tour_type']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ),
            'copy'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_tour_type']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ),
            'cut'    => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_tour_type']['cut'],
                'href'       => 'act=paste&mode=cut',
                'icon'       => 'cut.gif',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_tour_type']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ),
        ),
    ),
    'palettes' => array
    (
        'default' => 'shortcut,title,description',
    ),

    'fields' => array
    (
        'id'          => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'tstamp'      => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'pid'         => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'sorting'     => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'title'       => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_type']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => false,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'shortcut'    => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_type']['shortcut'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => false,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'description' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_type']['description'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => false,
            'inputType' => 'textarea',
            'eval'      => array('mandatory' => false),
            'sql'       => "text NULL",
        ),
    ),
);

