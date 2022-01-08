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

use Markocupic\SacEventToolBundle\Dca\TlTourDifficulty;

$GLOBALS['TL_DCA']['tl_tour_difficulty'] = [
    'config' => [
        'dataContainer'      => 'Table',
        'ptable'             => 'tl_tour_difficulty_category',
        'doNotCopyRecords'   => true,
        'enableVersioning'   => true,
        'switchToEdit'       => true,
        'doNotDeleteRecords' => true,
        'sql'                => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    'list'     => [
        'sorting'           => [
            'mode'                  => 4,
            'fields'                => ['code ASC'],
            'flag'                  => 1,
            'panelLayout'           => 'filter;sort,search,limit',
            'headerFields'          => [
                'level',
                'title',
            ],
            'disableGrouping'       => true,
            'child_record_callback' => [
                TlTourDifficulty::class,
                'listDifficulties',
            ],
        ],
        'label'             => [
            'fields'      => [
                'title',
                'shortcut',
            ],
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
                'label' => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ],
            'copy'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\')) return false; Backend.getScrollOffset();"',
            ],
        ],
    ],
    'palettes' => [
        'default' => 'code,shortcut,title,description',
    ],

    'fields' => [
        'id'          => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid'         => [
            'foreignKey' => 'tl_tour_difficulty_category.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => [
                'type' => 'belongsTo',
                'load' => 'eager',
            ],
        ],
        'sorting'     => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'      => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'shortcut'    => [
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['shortcut'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'maxlength' => 255,
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'title'       => [
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'maxlength' => 255,
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'code'        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['code'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'maxlength' => 255,
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'description' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty']['description'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'textarea',
            'eval'      => ['mandatory' => true],
            'sql'       => 'text NULL',
        ],
    ],
];
