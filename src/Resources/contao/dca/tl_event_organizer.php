<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 *
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Markocupic\SacEventToolBundle\Dca\TlEventOrganizer;

$GLOBALS['TL_DCA']['tl_event_organizer'] = [

    'config' => [
        'dataContainer'    => 'Table',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    'list'        => [
        'sorting'           => [
            'mode'        => 2,
            'fields'      => ['sorting ASC'],
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
            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_event_organizer']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ],
            'copy'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_event_organizer']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_event_organizer']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ],
            'show'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_event_organizer']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],
        ],
    ],
    'palettes'    => [
        '__selector__' => ['addLogo'],
        'default'      => '{title_legend},title,titlePrint,sorting;{eventList_legend},ignoreFilterInEventList,hideInEventFilter;{event_regulation_legend},tourRegulationExtract,tourRegulationSRC,courseRegulationExtract,courseRegulationSRC;{event_story_legend},notifyWebmasterOnNewEventStory;{emergency_concept_legend},emergencyConcept;{logo_legend},addLogo;{annual_program_legend},annualProgramShowHeadline,annualProgramShowTeaser,annualProgramShowDetails',
    ],
    // Subpalettes
    'subpalettes' => [
        'addLogo' => 'singleSRC',
    ],

    'fields' => [
        'id'                             => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp'                         => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'                          => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'titlePrint'                     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['titlePrint'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'sorting'                        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['sorting'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'mandatory' => true, 'maxlength' => 255],
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'ignoreFilterInEventList'        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['ignoreFilterInEventList'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'clr m12'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'hideInEventFilter'              => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['hideInEventFilter'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'clr m12'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'tourRegulationExtract'          => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['tourRegulationExtract'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr m12', 'rte' => 'tinyMCE', 'helpwizard' => true, 'mandatory' => true],
            'sql'       => "text NULL",
        ],
        'courseRegulationExtract'        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['courseRegulationExtract'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr m12', 'rte' => 'tinyMCE', 'helpwizard' => true, 'mandatory' => true],
            'sql'       => "text NULL",
        ],
        'tourRegulationSRC'              => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['tourRegulationSRC'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'       => "binary(16) NULL",
        ],
        'courseRegulationSRC'            => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['courseRegulationSRC'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
            'sql'       => "binary(16) NULL",
        ],
        'singleSRC'                      => [
            'label'         => &$GLOBALS['TL_LANG']['tl_event_organizer']['singleSRC'],
            'exclude'       => true,
            'inputType'     => 'fileTree',
            'eval'          => ['filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'],
            'load_callback' => [
                [TlEventOrganizer::class, 'setSingleSrcFlags'],
            ],
            'sql'           => "binary(16) NULL",
        ],
        'notifyWebmasterOnNewEventStory' => [
            'label'      => &$GLOBALS['TL_LANG']['tl_event_organizer']['notifyWebmasterOnNewEventStory'],
            'exclude'    => true,
            'filter'     => true,
            'inputType'  => 'select',
            'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
            'foreignKey' => 'tl_user.name',
            'eval'       => ['multiple' => true, 'chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'clr'],
            'sql'        => "blob NULL",
        ],
        'emergencyConcept'               => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['emergencyConcept'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
            'sql'       => "text NULL",
        ],
        'addLogo'                        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['addLogo'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'singleSRC'                      => [
            'label'         => &$GLOBALS['TL_LANG']['tl_event_organizer']['singleSRC'],
            'exclude'       => true,
            'inputType'     => 'fileTree',
            'eval'          => ['filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'],
            'load_callback' => [
                [TlEventOrganizer::class, 'setSingleSrcFlags'],
            ],
            'sql'           => "binary(16) NULL",
        ],
        'annualProgramShowHeadline'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['annualProgramShowHeadline'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'annualProgramShowTeaser'  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['annualProgramShowTeaser'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'annualProgramShowDetails'      => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_organizer']['annualProgramShowDetails'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
    ],
];



