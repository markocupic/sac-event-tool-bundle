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

use Contao\BackendUser;

$GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice'] = [
    'config' => [
        'dataContainer'    => 'Table',
        'ptable'           => 'tl_calendar_events',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'sql'              => [
            'keys' => [
                'pid' => 'index',
                'id'  => 'primary',
            ],
        ],
    ],

    'list'     => [
        'sorting'           => [
            'mode'            => 4,
            'fields'          => ['userPid'],
            'panelLayout'     => 'filter;search,limit',
            'headerFields'    => ['title'],
            'disableGrouping' => true,
        ],
        'label'             => [
            'fields'      => ['pid'],
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
            'edit'                    => [
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'copy'                    => [
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete'                  => [
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
            ],
            'generateInvoicePdf'      => [
                'href'       => 'action=generateInvoicePdf',
                'icon'       => 'bundles/markocupicsaceventtool/icons/pdf.png',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['generateInvoice'].'\')) return false; Backend.getScrollOffset();"',
            ],
            'generateInvoiceDocx'     => [
                'href'       => 'action=generateInvoiceDocx',
                'icon'       => 'bundles/markocupicsaceventtool/icons/docx.png',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['generateInvoice'].'\')) return false; Backend.getScrollOffset();"',
            ],
            'generateTourRapportPdf'  => [
                'href'       => 'action=generateTourRapportPdf',
                'icon'       => 'bundles/markocupicsaceventtool/icons/pdf.png',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['generateTourRapport'].'\')) return false; Backend.getScrollOffset();"',
            ],
            'generateTourRapportDocx' => [
                'href'       => 'action=generateTourRapportDocx',
                'icon'       => 'bundles/markocupicsaceventtool/icons/docx.png',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['generateTourRapport'].'\')) return false; Backend.getScrollOffset();"',
            ],
        ],
    ],
    'palettes' => [
        'default' => 'userPid;{event_legend},eventDuration;{expenses_legend},sleepingTaxes,sleepingTaxesText,miscTaxes,miscTaxesText;{transport_legend},railwTaxes,railwTaxesText,cabelCarTaxes,cabelCarTaxesText,roadTaxes,carTaxesKm,countCars;{phone_costs_legend},phoneTaxes;{iban_legend},iban;{notice_legend},notice',
    ],

    'fields' => [
        'id'                => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid'               => [
            'foreignKey' => 'tl_calendar_events.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
        ],
        'userPid'           => [
            'default'    => BackendUser::getInstance()->id,
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
            'eval'       => ['submitOnChange' => true, 'mandatory' => true, 'multiple' => false, 'class' => 'clr'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'            => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'eventDuration'     => [
            'exclude'   => true,
            'default'   => '0',
            'options'   => range(0, 30),
            'inputType' => 'select',
            'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 2, 'tl_class' => 'clr'],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'iban'              => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 34, 'tl_class' => 'clr'],
            'sql'       => "varchar(34) NOT NULL default ''",
        ],
        'sleepingTaxes'     => [
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'sleepingTaxesText' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'miscTaxes'         => [
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'miscTaxesText'     => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'railwTaxes'        => [
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'railwTaxesText'    => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'cabelCarTaxes'     => [
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'cabelCarTaxesText' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'roadTaxes'         => [
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'carTaxesKm'        => [
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 6, 'tl_class' => 'clr'],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'countCars'         => [
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'select',
            'options'   => range(0, 9),
            'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 1, 'tl_class' => 'clr'],
            'sql'       => "varchar(1) NOT NULL default '0'",
        ],
        'phoneTaxes'        => [
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 3, 'tl_class' => 'clr'],
            'sql'       => "varchar(3) NOT NULL default '0'",
        ],
        'notice'            => [
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['mandatory' => false, 'tl_class' => 'clr'],
            'sql'       => 'text NULL',
        ],
    ],
];
