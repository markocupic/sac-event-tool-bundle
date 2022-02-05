<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\Dca\TlCalendarEventsStory;

$GLOBALS['TL_DCA']['tl_calendar_events_story'] = [
    'config' => [
        'dataContainer' => 'Table',
        'enableVersioning' => true,
        'notCopyable' => true,
        'closed' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'eventId' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 2,
            'fields' => ['eventStartDate DESC'],
            'panelLayout' => 'filter;sort,search',
        ],
        'label' => [
            'fields' => [
                'publishState',
                'checkedByInstructor',
                'title',
                'authorName',
            ],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['show'],
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
            'exportArticle' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['exportArticle'],
                'href' => 'action=exportArticle',
                'icon' => 'bundles/markocupicsaceventtool/icons/docx.png',
                //'button_callback' => array(TlCalendarEventsStory::class, 'exportArticle'),
            ],
        ],
    ],
    'palettes' => [
        'default' => '
		{publishState_legend},publishState,checkedByInstructor;
		{author_legend},addedOn,sacMemberId,authorName;
		{event_legend},eventId,title,eventTitle,eventSubstitutionText,organizers,tourWaypoints,tourProfile,tourTechDifficulty,text,tourHighlights,tourPublicTransportInfo,youtubeId,multiSRC',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'eventId' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_story']['eventId'],
            'foreignKey' => 'tl_calendar_events.title',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'eager'],
            'eval' => ['readonly' => true],
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'publishState' => [
            'filter' => true,
            'default' => 1,
            'exclude' => true,
            'reference' => $GLOBALS['TL_LANG']['tl_calendar_events_story']['publishStateRef'],
            'inputType' => 'select',
            'options' => ['1', '2', '3'],
            'eval' => ['tl_class' => 'clr', 'submitOnChange' => true],
            'sql' => "char(1) NOT NULL default '1'",
        ],
        'checkedByInstructor' => [
            'filter' => true,
            'default' => 1,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr', 'submitOnChange' => false],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'authorName' => [
            'filter' => true,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => ['doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'eventTitle' => [
            'inputType' => 'text',
            'eval' => ['doNotCopy' => true, 'mandatory' => true, 'readonly' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'eventSubstitutionText' => [
            'inputType' => 'text',
            'eval' => ['doNotCopy' => true, 'mandatory' => false, 'readonly' => true, 'maxlength' => 64, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'eventStartDate' => [
            'sorting' => true,
            'flag' => 6,
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'eventEndDate' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'eventDates' => [
            'sql' => 'blob NULL',
        ],
        'title' => [
            'inputType' => 'text',
            'eval' => ['doNotCopy' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'text' => [
            'inputType' => 'textarea',
            'eval' => ['doNotCopy' => true, 'max-length' => 1700, 'mandatory' => true, 'tl_class' => 'clr'],
            'sql' => 'mediumtext NULL',
        ],
        'youtubeId' => [
            'inputType' => 'text',
            'eval' => ['doNotCopy' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'sacMemberId' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50', 'readonly' => true],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'multiSRC' => [
            'inputType' => 'fileTree',
            'eval' => [
                'path' => System::getContainer()->getParameter('sacevt.event.story.asset_dir').'/'.Input::get('id'),
                'doNotCopy' => true,
                'isGallery' => true,
                'extensions' => 'jpg,jpeg',
                'multiple' => true,
                'fieldType' => 'checkbox',
                'orderField' => 'orderSRC',
                'files' => true,
                'mandatory' => false,
                'tl_class' => 'clr',
            ],
            'sql' => 'blob NULL',
        ],
        'orderSRC' => [
            'eval' => ['doNotCopy' => true],
            'sql' => 'blob NULL',
        ],
        'organizers' => [
            'search' => true,
            'filter' => true,
            'sorting' => true,
            'inputType' => 'select',
            'foreignKey' => 'tl_event_organizer.title',
            'relation' => ['type' => 'hasMany', 'load' => 'lazy'],
            'eval' => ['multiple' => true, 'chosen' => true, 'mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr m12'],
            'sql' => 'blob NULL',
        ],
        'securityToken' => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'addedOn' => [
            'default' => time(),
            'flag' => 8,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'date', 'mandatory' => true, 'doNotCopy' => false, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => 'int(10) unsigned NULL',
        ],
        'tourWaypoints' => [
            'inputType' => 'textarea',
            'eval' => ['doNotCopy' => true, 'max-length' => 300, 'mandatory' => false, 'tl_class' => 'clr'],
            'sql' => 'mediumtext NULL',
        ],
        'tourProfile' => [
            'inputType' => 'textarea',
            'eval' => ['doNotCopy' => true, 'mandatory' => false, 'tl_class' => 'clr'],
            'sql' => 'mediumtext NULL',
        ],
        'tourTechDifficulty' => [
            'inputType' => 'textarea',
            'eval' => ['doNotCopy' => true, 'mandatory' => false, 'tl_class' => 'clr'],
            'sql' => 'mediumtext NULL',
        ],
        'tourHighlights' => [
            'inputType' => 'textarea',
            'eval' => ['doNotCopy' => true, 'mandatory' => false, 'tl_class' => 'clr'],
            'sql' => 'mediumtext NULL',
        ],
        'tourPublicTransportInfo' => [
            'inputType' => 'textarea',
            'eval' => ['doNotCopy' => true, 'mandatory' => false, 'tl_class' => 'clr'],
            'sql' => 'mediumtext NULL',
        ],
    ],
];
