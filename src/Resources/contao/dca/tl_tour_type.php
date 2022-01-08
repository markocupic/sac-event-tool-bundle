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

use Markocupic\SacEventToolBundle\Dca\TlTourType;

$GLOBALS['TL_DCA']['tl_tour_type'] = [
    'config'   => [
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
    'list'     => [
        'sorting'           => [
            'mode'                  => 5,
            'fields'                => ['sorting'],
            'flag'                  => 1,
            'panelLayout'           => 'filter;search,limit',
            'paste_button_callback' => [
                TlTourType::class,
                'pasteTag',
            ],
        ],
        'label'             => [
            'fields' => [
                'shortcut',
                'title',
            ],
            'format' => '%s %s',
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
                'label' => &$GLOBALS['TL_LANG']['tl_tour_type']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ],
            'copy'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_tour_type']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ],
            'cut'    => [
                'label'      => &$GLOBALS['TL_LANG']['tl_tour_type']['cut'],
                'href'       => 'act=paste&mode=cut',
                'icon'       => 'cut.gif',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_tour_type']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\')) return false; Backend.getScrollOffset();"',
            ],
        ],
    ],
    'palettes' => [
        'default' => 'shortcut,title,description',
    ],

    'fields' => [
        'id'          => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp'      => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'pid'         => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'sorting'     => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'       => [
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_type']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => false,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'maxlength' => 255,
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'shortcut'    => [
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_type']['shortcut'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => false,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'maxlength' => 255,
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'description' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_tour_type']['description'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => false,
            'inputType' => 'textarea',
            'eval'      => ['mandatory' => false],
            'sql'       => 'text NULL',
        ],
    ],
];
