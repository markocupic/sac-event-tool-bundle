<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$GLOBALS['TL_DCA']['tl_course_sub_type'] = [
    'config'   => [
        'dataContainer'    => 'Table',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'ptable'           => 'tl_course_main_type',
        'sql'              => [
            'keys' => [
                'id'  => 'primary',
                'pid' => 'index',
            ],
        ],
    ],
    'list'     => [
        'sorting'           => [
            'mode'        => 2,
            'fields'      => ['code ASC'],
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label'             => [
            'fields'      => [
                'code',
                'pid:tl_course_main_type.name',
                'name',
            ],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ],
        ],
        'operations'        => [
            'edit'   => [
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'copy'   => [
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
            ],
        ],
    ],
    'palettes' => [
        'default' => 'pid,code,name,',
    ],
    'fields'   => [
        'id'     => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid'    => [
            'inputType'  => 'select',
            'sorting'    => true,
            'filter'     => true,
            'foreignKey' => 'tl_course_main_type.name',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
        ],
        'tstamp' => [
            'flag' => 6,
            'sql'  => "int(10) unsigned NOT NULL default '0'",
        ],
        'code'   => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'unique' => true],
            'sql'       => "varchar(5) NOT NULL default ''",
        ],
        'name'   => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
    ],
];
