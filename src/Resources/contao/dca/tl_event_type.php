<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Markocupic\SacEventToolBundle\Dca\TlEventType;

/**
 * Table tl_event_type
 */
$GLOBALS['TL_DCA']['tl_event_type'] = array
(

    // Config
    'config'   => array
    (
        'dataContainer'    => 'Table',
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
            'mode'        => 2,
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
            'edit'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_type']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg',
            ),
            'copy'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_type']['copy'],
                'href'  => 'act=paste&amp;mode=copy',
                'icon'  => 'copy.svg',
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_event_type']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ),
            'show'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_type']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ),
        ),
    ),

    // Palettes
    'palettes' => array
    (
        'default' => '{title_legend},alias,title;{release_level_legend},levelAccessPermissionPackage;{preview_page_legend},previewPage;',
    ),

    // Fields
    'fields'   => array
    (
        'id'                           => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'tstamp'                       => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'alias'                        => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_event_type']['alias'],
            'inputType'     => 'text',
            'exclude'       => true,
            'search'        => true,
            'flag'          => 1,
            'load_callback' => array(array(TlEventType::class, 'loadCallbackAlias')),
            'eval'          => array('mandatory' => true, 'rgxp' => 'alnum', 'maxlength' => 128, 'tl_class' => 'w50'),
            'sql'           => "varchar(128) NULL",
        ),
        'title'                        => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_type']['title'],
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'flag'      => 1,
            'eval'      => array('mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w50'),
            'sql'       => "varchar(128) NULL",
        ),
        'levelAccessPermissionPackage' => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_event_type']['levelAccessPermissionPackage'],
            'exclude'    => true,
            'inputType'  => 'select',
            'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
            'foreignKey' => 'tl_event_release_level_policy_package.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'eval'       => array('includeBlankOption' => true, 'mandatory' => true, 'tl_class' => 'clr'),
        ),
       'previewPage' => array(

            'label'      => &$GLOBALS['TL_LANG']['tl_event_type']['previewPage'],
            'exclude'    => true,
            'inputType'  => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval'       => array('mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'),
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
        ),
    ),
);


