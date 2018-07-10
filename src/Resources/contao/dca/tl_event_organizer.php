<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */


$GLOBALS['TL_DCA']['tl_event_organizer'] = array
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
                'id' => 'primary',
            ),
        ),
    ),

    'list'     => array
    (
        'sorting'           => array
        (
            'mode'        => 2,
            'fields'      => array('sorting ASC'),
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit',
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
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ),
        ),
        'operations'        => array
        (
            'edit'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_organizer']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ),
            'copy'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_organizer']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_event_organizer']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ),
            'show'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_organizer']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ),
        ),
    ),
    'palettes' => array
    (
        'default' => '{title_legend},title,titlePrint,sorting,emergencyConcept',
    ),

    'fields' => array
    (
        'id'               => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'tstamp'           => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'title'            => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'titlePrint'            => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['titlePrint'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'sorting'          => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['sorting'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'digit', 'mandatory' => true, 'maxlength' => 255),
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ),
        'emergencyConcept' => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['emergencyConcept'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => array('tl_class' => 'clr m12', 'mandatory' => true),
            'sql'       => "text NULL",
        ),
    ),
);

