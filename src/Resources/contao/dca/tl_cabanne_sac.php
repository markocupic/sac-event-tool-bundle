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

use Contao\Config;

$GLOBALS['TL_DCA']['tl_cabanne_sac'] = [
    'config' => [
        'dataContainer' => 'Table',
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
            'mode' => 2,
            'fields' => ['name ASC'],
            'flag' => 1,
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label' => [
            'fields' => ['name'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'copy' => [
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\')) return false; Backend.getScrollOffset();"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{contact_legend},name,canton,altitude,hutWarden,phone,email,url,bookingMethod;{image_legend},singleSRC;{details_legend},huettenchef,capacity,coordsCH1903,coordsWGS84,openingTime;{ascent_legend},ascent',
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'name' => [
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
        'canton' => [
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
        'altitude' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => [
                'rgxp' => 'natural',
                'mandatory' => true,
                'maxlength' => 255,
                'tl_class' => 'clr',
            ],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'hutWarden' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'textarea',
            'eval' => ['rgxp' => '', 'mandatory' => true, 'maxlength' => 512, 'tl_class' => 'clr'],
            'sql' => "varchar(512) NOT NULL default ''",
        ],
        'phone' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'phone', 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'email' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'email', 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'bookingMethod' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'textarea',
            'eval' => ['mandatory' => false, 'maxlength' => 512, 'tl_class' => 'clr'],
            'sql' => "varchar(512) NOT NULL default ''",
        ],
        'url' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'url', 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'singleSRC' => [
            'exclude' => true,
            'inputType' => 'fileTree',
            'eval' => ['fieldType' => 'radio', 'filesOnly' => true, 'extensions' => Config::get('validImageTypes'), 'mandatory' => true],
            'sql' => 'binary(16) NULL',
        ],
        'huettenchef' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'maxlength' => 512, 'tl_class' => 'clr'],
            'sql' => "varchar(512) NOT NULL default ''",
        ],
        'capacity' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'maxlength' => 512, 'tl_class' => 'clr'],
            'sql' => "varchar(512) NOT NULL default ''",
        ],
        'coordsCH1903' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'coordsWGS84' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'openingTime' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'maxlength' => 512, 'tl_class' => 'clr'],
            'sql' => "varchar(512) NOT NULL default ''",
        ],
        'ascent' => [
            'label' => &$GLOBALS['TL_LANG']['tl_cabanne_sac']['ascent'],
            'exclude' => true,
            'inputType' => 'multiColumnWizard',
            'eval' => [
                'columnFields' => [
                    'ascentDescription' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_cabanne_sac']['ascentDescription'],
                        'exclude' => true,
                        'inputType' => 'textarea',
                        'eval' => ['style' => 'width:150px'],
                    ],
                    'ascentTime' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_cabanne_sac']['ascentTime'],
                        'exclude' => true,
                        'inputType' => 'text',
                        'eval' => ['style' => 'width:80px'],
                    ],
                    'ascentDifficulty' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_cabanne_sac']['ascentDifficulty'],
                        'exclude' => true,
                        'inputType' => 'textarea',
                        'eval' => ['style' => 'width:80px'],
                    ],
                    'ascentSummer' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_cabanne_sac']['ascentSummer'],
                        'exclude' => true,
                        'inputType' => 'select',
                        'options' => ['possible', 'not-possible'],
                        'reference' => &$GLOBALS['TL_LANG']['tl_cabanne_sac'],
                        'eval' => ['style' => 'width:50px'],
                    ],
                    'ascentWinter' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_cabanne_sac']['ascentWinter'],
                        'exclude' => true,
                        'inputType' => 'select',
                        'options' => ['possible', 'not-possible'],
                        'reference' => &$GLOBALS['TL_LANG']['tl_cabanne_sac'],
                        'eval' => ['style' => 'width:50px'],
                    ],
                    'ascentComment' => [
                        'label' => &$GLOBALS['TL_LANG']['tl_cabanne_sac']['ascentComment'],
                        'exclude' => true,
                        'inputType' => 'textarea',
                        'eval' => ['style' => 'width:150px'],
                    ],
                ],
            ],
            'sql' => 'blob NULL',
        ],
    ],
];
