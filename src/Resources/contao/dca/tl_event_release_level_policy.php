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

use Markocupic\SacEventToolBundle\Dca\TlEventReleaseLevelPolicy;

$GLOBALS['TL_DCA']['tl_event_release_level_policy'] = [
    'config' => [
        'dataContainer' => 'Table',
        'ptable' => 'tl_event_release_level_policy_package',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode' => 4,
            'fields' => ['level'],
            'panelLayout' => 'filter;search,limit',
            'headerFields' => ['level', 'title'],
            'disableGrouping' => true,
            'child_record_callback' => [
                TlEventReleaseLevelPolicy::class,
                'listReleaseLevels',
            ],
        ],
        'label' => [
            'fields' => ['level', 'title'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ],
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_news']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\')) return false; Backend.getScrollOffset();"',
            ],
        ],
    ],
    'palettes' => [
        'default' => 'level,title,description,allowWriteAccessToAuthor,allowWriteAccessToInstructors,allowSwitchingToPrevLevel,allowSwitchingToNextLevel,groupReleaseLevelRights',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_event_release_level_policy_package.title',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'eager'],
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'level' => [
            'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['level'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => range(1, 10),
            'eval' => ['mandatory' => true, 'tl_class' => 'clr'],
            'sql' => "smallint(2) unsigned NOT NULL default '0'",
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'description' => [
            'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['description'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'allowSwitchingToPrevLevel' => [
            'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToPrevLevel'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''",
        ],
        'allowSwitchingToNextLevel' => [
            'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToNextLevel'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''",
        ],
        'allowWriteAccessToAuthor' => [
            'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToAuthor'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''",
        ],
        'allowWriteAccessToAuthor' => [
            'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToAuthor'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''",
        ],
        'allowWriteAccessToInstructors' => [
            'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToInstructors'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''",
        ],
        'groupReleaseLevelRights' => [
            'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['groupReleaseLevelRights'],
            'exclude' => true,
            'inputType' => 'multiColumnWizard',
            'eval' => [
                'mandatory' => false,
                'columnFields' => [
                    'group' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['group'],
                        'exclude' => true,
                        'inputType' => 'select',
                        'reference' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy'],
                        'relation' => ['type' => 'hasMany', 'load' => 'eager'],
                        'foreignKey' => 'tl_user_group.name',
                        'eval' => ['style' => 'width:250px', 'mandatory' => true, 'includeBlankOption' => true],
                    ],
                    'releaseLevelRights' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['releaseLevelRights'],
                        'exclude' => true,
                        'inputType' => 'select',
                        'reference' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy'],
                        'options' => ['up', 'down', 'upAndDown'],
                        'eval' => ['style' => 'width:250px', 'mandatory' => true, 'includeBlankOption' => true],
                    ],
                    'writeAccess' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['writeAccess'],
                        'exclude' => true,
                        'inputType' => 'checkbox',
                        'eval' => ['style' => 'width:100px'],
                    ],
                ],
            ],
            'sql' => 'blob NULL',
        ],
    ],
];
