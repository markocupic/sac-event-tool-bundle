<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
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

    'list'        => array
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
    'palettes'    => array
    (
        '__selector__' => array('addLogo'),
        'default'      => '{title_legend},title,titlePrint,sorting;{event_regulation_legend},tourRegulationExtract,tourRegulationSRC,courseRegulationExtract,courseRegulationSRC;{event_story_legend},notifyWebmasterOnNewEventStory;{emergency_concept_legend},emergencyConcept;{logo_legend},addLogo',
    ),
    // Subpalettes
    'subpalettes' => array
    (
        'addLogo' => 'singleSRC',
    ),

    'fields' => array
    (
        'id'                             => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'tstamp'                         => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'title'                          => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'titlePrint'                     => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['titlePrint'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'sorting'                        => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['sorting'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'digit', 'mandatory' => true, 'maxlength' => 255),
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ),
        'tourRegulationExtract'               => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['tourRegulationExtract'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => array('tl_class' => 'clr m12', 'rte'=>'tinyMCE', 'helpwizard'=>true,'mandatory' => true),
            'sql'       => "text NULL",
        ),
        'courseRegulationExtract'               => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['courseRegulationExtract'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => array('tl_class' => 'clr m12', 'rte'=>'tinyMCE', 'helpwizard'=>true,'mandatory' => true),
            'sql'       => "text NULL",
        ),
        'tourRegulationSRC'                      => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_event_organizer']['tourRegulationSRC'],
            'exclude'       => true,
            'inputType'     => 'fileTree',
            'eval'          => array('filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'),
            'sql'           => "binary(16) NULL",
        ),
        'courseRegulationSRC'                      => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_event_organizer']['courseRegulationSRC'],
            'exclude'       => true,
            'inputType'     => 'fileTree',
            'eval'          => array('filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'),
            'sql'           => "binary(16) NULL",
        ),
        'singleSRC'                      => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_event_organizer']['singleSRC'],
            'exclude'       => true,
            'inputType'     => 'fileTree',
            'eval'          => array('filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'),
            'load_callback' => array
            (
                array('tl_event_organizer', 'setSingleSrcFlags'),
            ),
            'sql'           => "binary(16) NULL",
        ),
        'notifyWebmasterOnNewEventStory' => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_event_organizer']['notifyWebmasterOnNewEventStory'],
            'exclude'    => true,
            'filter'     => true,
            'inputType'  => 'select',
            'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
            'foreignKey' => 'tl_user.name',
            'eval'       => array('multiple' => true, 'chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'clr'),
            'sql'        => "blob NULL",
        ),
        'emergencyConcept'               => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['emergencyConcept'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => array('tl_class' => 'clr m12', 'mandatory' => true),
            'sql'       => "text NULL",
        ),
        'addLogo'                        => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['addLogo'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => array('submitOnChange' => true),
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'singleSRC'                      => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_event_organizer']['singleSRC'],
            'exclude'       => true,
            'inputType'     => 'fileTree',
            'eval'          => array('filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'),
            'load_callback' => array
            (
                array('tl_event_organizer', 'setSingleSrcFlags'),
            ),
            'sql'           => "binary(16) NULL",
        ),
    ),
);



