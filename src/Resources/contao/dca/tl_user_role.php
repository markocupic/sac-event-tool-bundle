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

use Markocupic\SacEventToolBundle\Dca\TlUserRole;

$GLOBALS['TL_DCA']['tl_user_role'] = [
    'config' => [
        'dataContainer' => 'Table',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 5,
            'fields' => [
                'title',
                'email',
            ],
            'format' => '%s %s',
            //'flag'                  => 1,
            'panelLayout' => 'filter;search,limit',
            'paste_button_callback' => [
                TlUserRole::class,
                'pasteTag',
            ],
        ],
        'label' => [
            'fields' => [
                'title',
                'email',
            ],
            'showColumns' => true,
            'label_callback' => [
                TlUserRole::class,
                'checkForUsage',
            ],
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
                'label' => &$GLOBALS['TL_LANG']['tl_user_role']['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_user_role']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'cut' => [
                'label' => &$GLOBALS['TL_LANG']['tl_tour_type']['cut'],
                'href' => 'act=paste&mode=cut',
                'icon' => 'cut.gif',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_user_role']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\')) return false; Backend.getScrollOffset();"',
            ],
        ],
    ],
    'palettes' => [
        'default' => 'title,belongsToExecutiveBoard,belongsToBeauftragteStammsektion;{address_legend},street,postal,city,phone,mobile,email',
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'sorting' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['sorting'],
            'exclude' => true,
            'search' => false,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => [
                'mandatory' => true,
                'rgxp' => 'natural',
                'maxlength' => 10,
            ],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['title'],
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => [
                'mandatory' => true,
                'maxlength' => 255,
                'tl_class' => 'clr',
            ],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'belongsToExecutiveBoard' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['belongsToExecutiveBoard'],
            'exclude' => true,
            'filter' => true,
            'sorting' => true,
            'inputType' => 'checkbox',
            'eval' => [
                'mandatory' => false,
                'tl_class' => 'clr',
            ],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'belongsToBeauftragteStammsektion' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['belongsToBeauftragteStammsektion'],
            'exclude' => true,
            'filter' => true,
            'sorting' => true,
            'inputType' => 'checkbox',
            'eval' => [
                'mandatory' => false,
                'tl_class' => 'clr',
            ],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'email' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['email'],
            'exclude' => true,
            'search' => true,
            'filter' => true,
            'inputType' => 'text',
            'eval' => [
                'assignTo' => 'tl_user.email',
                'mandatory' => false,
                'maxlength' => 255,
                'rgxp' => 'email',
                'unique' => false,
                'decodeEntities' => true,
                'tl_class' => 'w50',
            ],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'street' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['street'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => [
                'assignTo' => 'tl_user.street',
                'maxlength' => 255,
                'tl_class' => 'w50',
            ],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'postal' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['postal'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => [
                'assignTo' => 'tl_user.postal',
                'maxlength' => 32,
                'tl_class' => 'w50',
            ],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'city' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['city'],
            'exclude' => true,
            'filter' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => [
                'assignTo' => 'tl_user.city',
                'maxlength' => 255,
                'tl_class' => 'w50',
            ],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'phone' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['phone'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => [
                'assignTo' => 'tl_user.phone',
                'maxlength' => 64,
                'rgxp' => 'phone',
                'decodeEntities' => true,
                'tl_class' => 'w50',
            ],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'mobile' => [
            'label' => &$GLOBALS['TL_LANG']['tl_user_role']['mobile'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => [
                'assignTo' => 'tl_user.mobile',
                'maxlength' => 64,
                'rgxp' => 'phone',
                'decodeEntities' => true,
                'tl_class' => 'w50',
            ],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
    ],
];
