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

$GLOBALS['TL_DCA']['tl_calendar_events_journey'] = array
(
    'config' => array
    (
        'dataContainer'    => 'Table',
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
            'mode'        => 1,
            'fields'      => array('title ASC'),
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label'             => array
        (
            'fields'      => array('title'),
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
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_journey']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ),
            'copy'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_journey']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif'
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_journey']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
            )
        )
    ),
    'palettes' => array
    (
        'default' => 'title,alias'
    ),

    'fields' => array
    (
        'id'     => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp' => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'title'  => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_journey']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'alias'  => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_journey']['alias'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true),
            'sql'       => "varchar(255) NOT NULL default ''"
        )
    )
);
