<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\DC_Table;
use Contao\DataContainer;
use Contao\BackendUser;
use Markocupic\SacEventToolBundle\Config\Bundle;

$GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'ptable'           => 'tl_calendar_events',
		'doNotCopyRecords' => true,
		'notCopyable'      => true,
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
			'mode'            => DataContainer::MODE_PARENT,
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
			'all',
		],
		'operations'        => [
			'edit',
			'delete',
			'show',
			'generateInvoicePdf'      => [
				'href'       => 'action=generateInvoicePdf&key=noref', // Adding the "key" param to the url will prevent Contao of saving the url in the referer list: https://github.com/contao/contao/blob/178b1daf7a090fcb36351502705f4ce8ac57add6/core-bundle/src/EventListener/StoreRefererListener.php#L88C1-L88C1
				'icon'       => Bundle::ASSET_DIR.'/icons/fontawesome/default/file-pdf-regular.svg',
				'attributes' => 'onclick="if (!confirm(\''.($GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateInvoicePdfConfirm'] ?? null).'\')) return false; Backend.getScrollOffset();"',
			],
			'generateInvoiceDocx'     => [
				'href'       => 'action=generateInvoiceDocx&key=noref', // Adding the "key" param to the url will prevent Contao of saving the url in the referer list: https://github.com/contao/contao/blob/178b1daf7a090fcb36351502705f4ce8ac57add6/core-bundle/src/EventListener/StoreRefererListener.php#L88C1-L88C1
				'icon'       => Bundle::ASSET_DIR.'/icons/fontawesome/default/file-word-regular.svg',
				'attributes' => 'onclick="if (!confirm(\''.($GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateInvoiceDocxConfirm'] ?? null).'\')) return false; Backend.getScrollOffset();"',
			],
			'generateTourRapportPdf'  => [
				'href'       => 'action=generateTourRapportPdf&key=noref', // Adding the "key" param to the url will prevent Contao of saving the url in the referer list: https://github.com/contao/contao/blob/178b1daf7a090fcb36351502705f4ce8ac57add6/core-bundle/src/EventListener/StoreRefererListener.php#L88C1-L88C1
				'icon'       => Bundle::ASSET_DIR.'/icons/fontawesome/default/file-pdf-regular.svg',
				'attributes' => 'onclick="if (!confirm(\''.($GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateTourRapportPdfConfirm'] ?? null).'\')) return false; Backend.getScrollOffset();"',
			],
			'generateTourRapportDocx' => [
				'href'       => 'action=generateTourRapportDocx&key=noref', // Adding the "key" param to the url will prevent Contao of saving the url in the referer list: https://github.com/contao/contao/blob/178b1daf7a090fcb36351502705f4ce8ac57add6/core-bundle/src/EventListener/StoreRefererListener.php#L88C1-L88C1
				'icon'       => Bundle::ASSET_DIR.'/icons/fontawesome/default/file-word-regular.svg',
				'attributes' => 'onclick="if (!confirm(\''.($GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateTourRapportDocxConfirm'] ?? null).'\')) return false; Backend.getScrollOffset();"',
			],
			'sendRapport'             => [
				'href'       => 'action=sendRapport',
				'icon'       => Bundle::ASSET_DIR.'/icons/fontawesome/default/paper-plane-solid.svg',
				'attributes' => 'onclick="if (!confirm(\''.($GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['submitRapportConfirm'] ?? null).'\')) return false; Backend.getScrollOffset();"',
			],
		],
	],
	'palettes' => [
		'default' => '
        userPid;
        {event_legend},eventDuration;
        {expenses_legend},sleepingTaxes,sleepingTaxesText,miscTaxes,miscTaxesText;
        {transport_legend},railwTaxes,railwTaxesText,cableCarTaxes,cableCarTaxesText,roadTaxes,carTaxesKm,countCars,privateArrival;
        {phone_costs_legend},expenseReimbursement,organizationalFlatRate;
        {iban_legend},iban;
        {notice_legend},notice
        ',
	],
	'fields'   => [
		'id'                     => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'pid'                    => [
			'foreignKey' => 'tl_calendar_events.title',
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
		],
		'userPid'                => [
			'default'    => BackendUser::getInstance()->id,
			'foreignKey' => 'tl_user.name',
			'inputType'  => 'select',
			'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
			'eval'       => ['submitOnChange' => true, 'mandatory' => true, 'readonly' => true, 'multiple' => false, 'class' => 'clr'],
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		],
		'tstamp'                 => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'eventDuration'          => [
			'exclude'   => true,
			'default'   => '0',
			'options'   => range(0, 30),
			'inputType' => 'select',
			'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 2, 'tl_class' => 'clr'],
			'sql'       => "varchar(6) NOT NULL default '0'",
		],
		'iban'                   => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 34, 'doNotCopy' => true, 'tl_class' => 'clr'],
			'sql'       => "varchar(34) NOT NULL default ''",
		],
		'sleepingTaxes'          => [
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
			'sql'       => "varchar(6) NOT NULL default '0'",
		],
		'sleepingTaxesText'      => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'miscTaxes'              => [
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
			'sql'       => "varchar(6) NOT NULL default '0'",
		],
		'miscTaxesText'          => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'privateArrival'         => [
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => range(0, 20),
			'eval'      => ['multiple' => false, 'mandatory' => true, 'doNotCopy' => true, 'tl_class' => 'clr m12'],
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		],
		'railwTaxes'             => [
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
			'sql'       => "varchar(6) NOT NULL default '0'",
		],
		'railwTaxesText'         => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'cableCarTaxes'          => [
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
			'sql'       => "varchar(6) NOT NULL default '0'",
		],
		'cableCarTaxesText'      => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'roadTaxes'              => [
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'],
			'sql'       => "varchar(6) NOT NULL default '0'",
		],
		'carTaxesKm'             => [
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 6, 'doNotCopy' => true, 'tl_class' => 'clr'],
			'sql'       => "varchar(6) NOT NULL default '0'",
		],
		'countCars'              => [
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'select',
			'options'   => range(0, 9),
			'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 1, 'tl_class' => 'clr'],
			'sql'       => "varchar(1) NOT NULL default '0'",
		],
		'expenseReimbursement'   => [
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 3, 'doNotCopy' => true, 'tl_class' => 'clr'],
			'sql'       => "varchar(3) NOT NULL default '0'",
		],
		'organizationalFlatRate' => [
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 3, 'doNotCopy' => true, 'tl_class' => 'clr'],
			'sql'       => "varchar(3) NOT NULL default '0'",
		],
		'notice'                 => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['mandatory' => false, 'doNotCopy' => true, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		'countNotifications'     => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'notificationSentOn'     => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
	],
];
