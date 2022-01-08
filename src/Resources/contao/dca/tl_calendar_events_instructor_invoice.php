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
use Markocupic\SacEventToolBundle\Dca\TlCalendarEventsInstructorInvoice;

$GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice'] = [
    'config' => [
        'dataContainer'    => 'Table',
        'ptable'           => 'tl_calendar_events',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'onload_callback'  => [
            [
                TlCalendarEventsInstructorInvoice::class,
                'checkAccesRights',
            ],
            [
                TlCalendarEventsInstructorInvoice::class,
                'routeActions',
            ],
            [
                TlCalendarEventsInstructorInvoice::class,
                'warnIfReportFormHasNotFilledIn',
            ],
            [
                TlCalendarEventsInstructorInvoice::class,
                'reviseTable',
            ],
        ],
        'sql'              => [
            'keys' => [
                'pid' => 'index',
                'id'  => 'primary',
            ],
        ],
    ],

    // Buttons callback
    'edit'   => [
        'buttons_callback' => [
            [
                TlCalendarEventsInstructorInvoice::class,
                'buttonsCallback',
            ],
        ],
    ],

    'list'     => [
        'sorting'           => [
            'mode'                  => 4,
            'fields'                => ['userPid'],
            'panelLayout'           => 'filter;search,limit',
            'headerFields'          => ['title'],
            'disableGrouping'       => true,
            'child_record_callback' => [
                TlCalendarEventsInstructorInvoice::class,
                'listInvoices',
            ],
        ],
        'label'             => [
            'fields'      => ['pid'],
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
            'edit'                    => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ],
            'copy'                    => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ],
            'delete'                  => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\')) return false; Backend.getScrollOffset();"',
            ],
            'generateInvoicePdf'      => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateInvoicePdf'],
                'href'       => 'action=generateInvoicePdf',
                'icon'       => 'bundles/markocupicsaceventtool/icons/pdf.png',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['generateInvoice'].'\')) return false; Backend.getScrollOffset();"',
            ],
            'generateInvoiceDocx'     => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateInvoiceDocx'],
                'href'       => 'action=generateInvoiceDocx',
                'icon'       => 'bundles/markocupicsaceventtool/icons/docx.png',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['generateInvoice'].'\')) return false; Backend.getScrollOffset();"',
            ],
            'generateTourRapportPdf'  => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateTourRapportPdf'],
                'href'       => 'action=generateTourRapportPdf',
                'icon'       => 'bundles/markocupicsaceventtool/icons/pdf.png',
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['generateTourRapport'].'\')) return false; Backend.getScrollOffset();"',
            ],
            'generateTourRapportDocx' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateTourRapportDocx'],
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
            'relation'   => [
                'type' => 'belongsTo',
                'load' => 'eager',
            ],
        ],
        'userPid'           => [
            'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['userPid'],
            'default'    => BackendUser::getInstance(
            )->id,
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'relation'   => [
                'type' => 'belongsTo',
                'load' => 'eager',
            ],
            'eval'       => [
                'submitOnChange' => true,
                'mandatory'      => true,
                'multiple'       => false,
                'class'          => 'clr',
            ],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'            => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'eventDuration'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['eventDuration'],
            'exclude'   => true,
            'default'   => '0',
            'options'   => range(
                0,
                30
            ),
            'inputType' => 'select',
            'eval'      => [
                'mandatory' => true,
                'rgxp'      => 'natural',
                'maxlength' => 2,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'iban'              => [
            'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['iban'],
            'exclude'       => true,
            'inputType'     => 'text',
            'load_callback' => [
                [
                    TlCalendarEventsInstructorInvoice::class,
                    'getIbanFromUser',
                ],
            ],
            'eval'          => [
                'mandatory' => true,
                'maxlength' => 34,
                'tl_class'  => 'clr',
            ],
            'sql'           => "varchar(34) NOT NULL default ''",
        ],
        'sleepingTaxes'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['sleepingTaxes'],
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'rgxp'      => 'digit',
                'maxlength' => 6,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'sleepingTaxesText' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['sleepingTaxesText'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => false,
                'maxlength' => 255,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'miscTaxes'         => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['miscTaxes'],
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'rgxp'      => 'digit',
                'maxlength' => 6,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'miscTaxesText'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['miscTaxesText'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => false,
                'maxlength' => 255,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'railwTaxes'        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['railwTaxes'],
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'rgxp'      => 'digit',
                'maxlength' => 6,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'railwTaxesText'    => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['railwTaxesText'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => false,
                'maxlength' => 255,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'cabelCarTaxes'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['cabelCarTaxes'],
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'rgxp'      => 'digit',
                'maxlength' => 6,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'cabelCarTaxesText' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['cabelCarTaxesText'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => false,
                'maxlength' => 255,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'roadTaxes'         => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['roadTaxes'],
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'rgxp'      => 'digit',
                'maxlength' => 6,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'carTaxesKm'        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['carTaxesKm'],
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'rgxp'      => 'natural',
                'maxlength' => 6,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(6) NOT NULL default '0'",
        ],
        'countCars'         => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['countCars'],
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'select',
            'options'   => range(
                0,
                9
            ),
            'eval'      => [
                'mandatory' => true,
                'rgxp'      => 'natural',
                'maxlength' => 1,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(1) NOT NULL default '0'",
        ],
        'phoneTaxes'        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['phoneTaxes'],
            'exclude'   => true,
            'default'   => '0',
            'inputType' => 'text',
            'eval'      => [
                'mandatory' => true,
                'rgxp'      => 'digit',
                'maxlength' => 3,
                'tl_class'  => 'clr',
            ],
            'sql'       => "varchar(3) NOT NULL default '0'",
        ],
        'notice'            => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['notice'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => [
                'mandatory' => false,
                'tl_class'  => 'clr',
            ],
            'sql'       => 'text NULL',
        ],
    ],
];
