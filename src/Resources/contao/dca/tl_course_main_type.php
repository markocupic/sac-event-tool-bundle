<?php

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$GLOBALS['TL_DCA']['tl_course_main_type'] = array
(
    'config' => array
    (
        'dataContainer'    => 'Table',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'sql'              => array
        (
            'keys' => array
            (
                'id' => 'primary'
            )
        )
    ),

    'list'     => array
    (
        'sorting'           => array
        (
            'mode'        => 2,
            'fields'      => array('code ASC'),
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label'             => array
        (
            'fields'      => array('code', 'name'),
            'showColumns' => true,
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"'
            )
        ),
        'operations'        => array
        (
            'edit'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_course_main_type']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif'
            ),
            'copy'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_news']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif'
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_course_main_type']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
            )
        )
    ),
    'palettes' => array
    (
        'default' => 'code,name'
    ),

    'fields' => array
    (
        'id'     => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_course_main_type']['tstamp'],
            'flag'  => 6,
            'sql'   => "int(10) unsigned NOT NULL default '0'"
        ),
        'code'   => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_course_main_type']['code'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'select',
            'options'   => range(1, 10),
            'eval'      => array('mandatory' => true, 'unique' => true),
            'sql'       => "int(10) unsigned NOT NULL default '0'"
        ),
        'name'   => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_course_main_type']['name'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''"
        )
    )
);
