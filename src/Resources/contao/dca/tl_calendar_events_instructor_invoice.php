<?php

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\BackendUser;
use Markocupic\SacEventToolBundle\Dca\TlCalendarEventsInstructorInvoice;

$GLOBALS['TL_DCA']['tl_calendar_events_instructor_invoice'] = array(
	'config' => array(
		'dataContainer'    => 'Table',
		'ptable'           => 'tl_calendar_events',
		'doNotCopyRecords' => true,
		'enableVersioning' => true,
		'switchToEdit'     => true,
		'onload_callback'  => array(
			array(TlCalendarEventsInstructorInvoice::class, 'checkAccesRights'),
			array(TlCalendarEventsInstructorInvoice::class, 'routeActions'),
			array(TlCalendarEventsInstructorInvoice::class, 'warnIfReportFormHasNotFilledIn'),
			array(TlCalendarEventsInstructorInvoice::class, 'reviseTable'),
		),
		'sql'              => array(
			'keys' => array(
				'pid' => 'index',
				'id'  => 'primary'
			)
		)
	),

	// Buttons callback
	'edit'   => array(
		'buttons_callback' => array(array(TlCalendarEventsInstructorInvoice::class, 'buttonsCallback'))
	),

	'list'     => array(
		'sorting'           => array(
			'mode'                  => 4,
			'fields'                => array('userPid'),
			'panelLayout'           => 'filter;search,limit',
			'headerFields'          => array('title'),
			'disableGrouping'       => true,
			'child_record_callback' => array(TlCalendarEventsInstructorInvoice::class, 'listInvoices')
		),
		'label'             => array(
			'fields'      => array('pid'),
			'showColumns' => true,
		),
		'global_operations' => array(
			'all' => array(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset();"'
			)
		),
		'operations'        => array(
			'edit'                    => array(
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['edit'],
				'href'  => 'act=edit',
				'icon'  => 'edit.gif'
			),
			'copy'                    => array(
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['copy'],
				'href'  => 'act=copy',
				'icon'  => 'copy.gif'
			),
			'delete'                  => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.gif',
				'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'generateInvoicePdf'      => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateInvoicePdf'],
				'href'       => 'action=generateInvoicePdf',
				'icon'       => 'bundles/markocupicsaceventtool/icons/pdf.png',
				'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['generateInvoice'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'generateInvoiceDocx'     => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateInvoiceDocx'],
				'href'       => 'action=generateInvoiceDocx',
				'icon'       => 'bundles/markocupicsaceventtool/icons/docx.png',
				'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['generateInvoice'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'generateTourRapportPdf'  => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateTourRapportPdf'],
				'href'       => 'action=generateTourRapportPdf',
				'icon'       => 'bundles/markocupicsaceventtool/icons/pdf.png',
				'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['generateTourRapport'] . '\')) return false; Backend.getScrollOffset();"'
			),
			'generateTourRapportDocx' => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['generateTourRapportDocx'],
				'href'       => 'action=generateTourRapportDocx',
				'icon'       => 'bundles/markocupicsaceventtool/icons/docx.png',
				'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['generateTourRapport'] . '\')) return false; Backend.getScrollOffset();"'
			)
		)
	),
	'palettes' => array(
		'default' => 'userPid;{event_legend},eventDuration;{expenses_legend},sleepingTaxes,sleepingTaxesText,miscTaxes,miscTaxesText;{transport_legend},railwTaxes,railwTaxesText,cabelCarTaxes,cabelCarTaxesText,roadTaxes,carTaxesKm,countCars;{phone_costs_legend},phoneTaxes;{iban_legend},iban;{notice_legend},notice'
	),

	'fields' => array(
		'id'                => array(
			'sql' => "int(10) unsigned NOT NULL auto_increment"
		),
		'pid'               => array(
			'foreignKey' => 'tl_calendar_events.title',
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => array('type' => 'belongsTo', 'load' => 'eager')
		),
		'userPid'           => array(
			'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['userPid'],
			'default'    => BackendUser::getInstance()->id,
			'foreignKey' => 'tl_user.name',
			'inputType'  => 'select',
			'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
			'eval'       => array('submitOnChange' => true, 'mandatory' => true, 'multiple' => false, 'class' => 'clr'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'tstamp'            => array(
			'sql' => "int(10) unsigned NOT NULL default '0'"
		),
		'eventDuration'     => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['eventDuration'],
			'exclude'   => true,
			'default'   => '0',
			'options'   => range(0, 30),
			'inputType' => 'select',
			'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 2, 'tl_class' => 'clr'),
			'sql'       => "varchar(6) NOT NULL default '0'"
		),
		'iban'              => array(
			'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['iban'],
			'exclude'       => true,
			'inputType'     => 'text',
			'load_callback' => array(array('tl_calendar_events_instructor_invoice', 'getIbanFromUser')),
			'eval'          => array('mandatory' => true, 'maxlength' => 34, 'tl_class' => 'clr'),
			'sql'           => "varchar(34) NOT NULL default ''"
		),
		'sleepingTaxes'     => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['sleepingTaxes'],
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'),
			'sql'       => "varchar(6) NOT NULL default '0'"
		),
		'sleepingTaxesText' => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['sleepingTaxesText'],
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''"
		),
		'miscTaxes'         => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['miscTaxes'],
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'),
			'sql'       => "varchar(6) NOT NULL default '0'"
		),
		'miscTaxesText'     => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['miscTaxesText'],
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''"
		),
		'railwTaxes'        => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['railwTaxes'],
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'),
			'sql'       => "varchar(6) NOT NULL default '0'"
		),
		'railwTaxesText'    => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['railwTaxesText'],
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''"
		),
		'cabelCarTaxes'     => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['cabelCarTaxes'],
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'),
			'sql'       => "varchar(6) NOT NULL default '0'"
		),
		'cabelCarTaxesText' => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['cabelCarTaxesText'],
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''"
		),
		'roadTaxes'         => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['roadTaxes'],
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 6, 'tl_class' => 'clr'),
			'sql'       => "varchar(6) NOT NULL default '0'"
		),
		'carTaxesKm'        => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['carTaxesKm'],
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 6, 'tl_class' => 'clr'),
			'sql'       => "varchar(6) NOT NULL default '0'"
		),
		'countCars'         => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['countCars'],
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'select',
			'options'   => range(0, 9),
			'eval'      => array('mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 1, 'tl_class' => 'clr'),
			'sql'       => "varchar(1) NOT NULL default '0'"
		),
		'phoneTaxes'        => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['phoneTaxes'],
			'exclude'   => true,
			'default'   => '0',
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'rgxp' => 'digit', 'maxlength' => 3, 'tl_class' => 'clr'),
			'sql'       => "varchar(3) NOT NULL default '0'"
		),
		'notice'            => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_instructor_invoice']['notice'],
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => array('mandatory' => false, 'tl_class' => 'clr'),
			'sql'       => "text NULL",
		),
	)
);
