<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */



$GLOBALS['TL_DCA']['tl_course_sub_type'] = array(

    'config'   => array(
        'dataContainer'    => 'Table',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'ptable'           => 'tl_course_main_type',
        'sql'              => array(
            'keys' => array(
                'id'  => 'primary',
                'pid' => 'index',
            ),
        ),
    ),
    'list'     => array(
        'sorting'           => array(
            'mode'        => 2,
            'fields'      => array('code ASC'),
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit',
        ),
        'label'             => array(
            'fields'      => array('code', 'pid:tl_course_main_type.name', 'name'),
            'showColumns' => true,
        ),
        'global_operations' => array(
            'all' => array(
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ),
        ),
        'operations'        => array(
            'edit'   => array(
                'label' => &$GLOBALS['TL_LANG']['tl_course_sub_type']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ),
            'copy'   => array(
                'label' => &$GLOBALS['TL_LANG']['tl_news']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ),
            'delete' => array(
                'label'      => &$GLOBALS['TL_LANG']['tl_course_sub_type']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ),
        ),
    ),
    'palettes' => array(
        'default' => 'pid,code,name,',
    ),
    'fields'   => array(
        'id'     => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'pid'    => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_course_sub_type']['pid'],
            'inputType'  => 'select',
            'sorting'    => true,
            'filter'     => true,
            'foreignKey' => 'tl_course_main_type.name',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
        ),
        'tstamp' => array(
            'label' => &$GLOBALS['TL_LANG']['tl_course_sub_type']['tstamp'],
            'flag'  => 6,
            'sql'   => "int(10) unsigned NOT NULL default '0'",

        ),
        'code'   => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_course_sub_type']['code'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'unique' => true),
            'sql'       => "varchar(5) NOT NULL default ''",
        ),
        'name'   => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_course_sub_type']['name'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
    ),
);
