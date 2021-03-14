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

use Contao\Config;
use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\Dca\TlCalendarEventsMember;

System::loadLanguageFile('tl_member');

/**
 * Table tl_calendar_events_member
 */
$GLOBALS['TL_DCA']['tl_calendar_events_member'] = array(
	'config'      => array(
		'dataContainer'     => 'Table',
		'notCopyable'       => true,
		// Do not copy nor delete records, if an item has been deleted!
		'onload_callback'   => array(
			array(TlCalendarEventsMember::class, 'setStateOfSubscription'),
			array(TlCalendarEventsMember::class, 'onloadCallback'),
			array(TlCalendarEventsMember::class, 'reviseTable'),
			array(TlCalendarEventsMember::class, 'setContaoMemberIdFromSacMemberId'),
			array(TlCalendarEventsMember::class, 'setGlobalOperations'),
			array(TlCalendarEventsMember::class, 'onloadCallbackExportMemberlist'),
		),
		'onsubmit_callback' => array(
			array(TlCalendarEventsMember::class, 'onsubmitCallback'),
		),
		'ondelete_callback' => array(
		),
		'sql'               => array(
			'keys' => array(
				'id'            => 'primary',
				'email,eventId' => 'index',
			),
		),
	),
	// Buttons callback
	'edit'        => array(
		'buttons_callback' => array(array(TlCalendarEventsMember::class, 'buttonsCallback')),
	),

	// List
	'list'        => array(
		'sorting'           => array(
			'mode'        => 2,
			'fields'      => array('stateOfSubscription, addedOn'),
			'flag'        => 1,
			'panelLayout' => 'filter;sort,search',
			'filter'      => array(array('eventId=?', Input::get('id'))),
		),
		'label'             => array(
			'fields'         => array('stateOfSubscription', 'firstname', 'lastname', 'street', 'city'),
			'showColumns'    => true,
			'label_callback' => array(TlCalendarEventsMember::class, 'addIcon'),
		),
		'global_operations' => array(
			'all'                            => array(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
			'downloadEventMemberList2Docx'        => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['downloadEventMemberList2Docx'],
				'href'       => 'act=downloadEventMemberList',
				'class'      => 'download_registration_list',
				'icon'       => 'bundles/markocupicsaceventtool/icons/docx.png',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
			'downloadEventMemberList2Csv' => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['downloadEventMemberList2Csv'],
				'href'       => 'action=onloadCallbackExportMemberlist',
				'class'      => 'header_icon',
				'icon'       => 'bundles/markocupicsaceventtool/icons/excel.svg',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
			'writeTourReport'                => array(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['writeTourReportButton'],
				'href'       => '',
				'class'      => 'writeTourRapport',
				'icon'       => 'edit.svg',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
			'printInstructorInvoice'         => array(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['printInstructorInvoiceButton'],
				'href'       => '',
				'class'      => 'printInstructorInvoice',
				'icon'       => 'bundles/markocupicsaceventtool/icons/docx.png',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),

			'sendEmail'                      => array(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['sendEmail'],
				'href'       => 'act=edit&call=sendEmail',
				'class'      => 'send_email',
				'icon'       => 'bundles/markocupicsaceventtool/icons/enveloppe.svg',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
			'backToEventSettings'            => array(
				'label'           => &$GLOBALS['TL_LANG']['MSC']['backToEvent'],
				'href'            => 'contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s&ref=%s',
				'button_callback' => array(TlCalendarEventsMember::class, 'buttonCbBackToEventSettings'),
				'icon'            => 'bundles/markocupicsaceventtool/icons/back.svg',
				'attributes'      => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
		),
		'operations'        => array(
			'edit' => array(
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['edit'],
				'href'  => 'act=edit',
				'icon'  => 'edit.svg',
			),

			'delete'                     => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
			),
			// Regular "toggle" operation but without "icon" and with the haste specific params
			'toggleStateOfParticipation' => array(
				'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['toggleStateOfParticipation'],
				'attributes'           => 'onclick="Backend.getScrollOffset();"',
				'haste_ajax_operation' => array(
					'field'   => 'hasParticipated',
					'options' => array(
						array(
							'value' => '',
							'icon'  => Config::get('SAC_EVT_ASSETS_DIR') . '/icons/has-not-participated.svg',
						),
						array(
							'value' => '1',
							'icon'  => Config::get('SAC_EVT_ASSETS_DIR') . '/icons/has-participated.svg',
						),
					),
				),
			),
			'show'                       => array(
				'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['show'],
				'href'  => 'act=show',
				'icon'  => 'show.svg',
			),
		),
	),

	// Palettes
	'palettes'    => array(
		'__selector__'    => array('addEmailAttachment'),
		'default'         => '{stateOfSubscription_legend},dashboard,stateOfSubscription,addedOn;{notes_legend},carInfo,ticketInfo,foodHabits,notes,instructorNotes,bookingType;{sac_member_id_legend},sacMemberId;{personal_legend},firstname,lastname,gender,dateOfBirth,sectionIds;{address_legend:hide},street,postal,city;{contact_legend},mobile,email;{emergency_phone_legend},emergencyPhone,emergencyPhoneName;{stateOfParticipation_legend},hasParticipated;',
		'sendEmail'       => '{sendEmail_legend},emailRecipients,emailSubject,emailText,addEmailAttachment,emailSendCopy;',
		'refuseWithEmail' => 'refuseWithEmail;',
		'acceptWithEmail' => 'acceptWithEmail;',
		'addToWaitlist'   => 'addToWaitlist;',
	),

	// Subpalettes
	'subpalettes' => array(
		'addEmailAttachment' => 'emailAttachment',
	),

	// Fields
	'fields'      => array(
		'id'                  => array(
			'sql' => "int(10) unsigned NOT NULL auto_increment",
		),
		'eventId'             => array(
			'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['eventId'],
			'foreignKey' => 'tl_calendar_events.title',
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
			'eval'       => array('readonly' => true),
		),
		'contaoMemberId'      => array(
			'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['contaoMemberId'],
			'foreignKey' => "tl_member.CONCAT(firstname, ' ', lastname)",
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
			'eval'       => array('readonly' => true),
		),
		'tstamp'              => array(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'addedOn'             => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['addedOn'],
			'inputType' => 'text',
			'flag'      => 5,
			'sorting'   => true,
			'eval'      => array('rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
			'sql'       => "varchar(10) NOT NULL default ''",
		),
		'stateOfSubscription' => array(
			'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfSubscription'],
			'filter'        => true,
			'inputType'     => 'select',
			'save_callback' => array(array(TlCalendarEventsMember::class, 'saveCallbackStateOfSubscription')),
			'default'       => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'][0],
			'reference'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
			'options'       => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'],
			'eval'          => array('doNotShow' => false, 'readonly' => false, 'includeBlankOption' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'           => "varchar(255) NOT NULL default ''",
		),
		'carInfo'             => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['carInfo'],
			'inputType' => 'select',
			'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'],
			'eval'      => array('includeBlankOption' => true, 'doNotShow' => false, 'doNotCopy' => true),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'ticketInfo'          => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['ticketInfo'],
			'inputType' => 'select',
			'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'],
			'eval'      => array('includeBlankOption' => true, 'doNotShow' => false, 'doNotCopy' => true),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'hasParticipated'     => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['hasParticipated'],
			'inputType' => 'checkbox',
			'eval'      => array('submitOnChange' => true, 'doNotShow' => false, 'doNotCopy' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'dashboard'           => array(
			'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['dashboard'],
			'inputType'            => 'text',
			'input_field_callback' => array(TlCalendarEventsMember::class, 'inputFieldCallbackDashboard'),
			'eval'                 => array('doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'                  => "varchar(255) NOT NULL default ''",
		),
		'refuseWithEmail'     => array(
			'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['refuseWithEmail'],
			'inputType'            => 'text',
			'input_field_callback' => array(TlCalendarEventsMember::class, 'inputFieldCallbackNotifyMemberAboutSubscriptionState'),
			'eval'                 => array('doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'                  => "varchar(255) NOT NULL default ''",
		),
		'acceptWithEmail'     => array(
			'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['acceptWithEmail'],
			'inputType'            => 'text',
			'input_field_callback' => array(TlCalendarEventsMember::class, 'inputFieldCallbackNotifyMemberAboutSubscriptionState'),
			'eval'                 => array('doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'                  => "varchar(255) NOT NULL default ''",
		),
		'addToWaitlist'       => array(
			'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['acceptWithEmail'],
			'inputType'            => 'text',
			'input_field_callback' => array(TlCalendarEventsMember::class, 'inputFieldCallbackNotifyMemberAboutSubscriptionState'),
			'eval'                 => array('doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'                  => "varchar(255) NOT NULL default ''",
		),
		'eventName'           => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['eventName'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'notes'               => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['notes'],
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => array('tl_class' => 'clr', 'decodeEntities' => true, 'mandatory' => false),
			'sql'       => "text NULL",
		),
		'instructorNotes'     => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['instructorNotes'],
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => array('tl_class' => 'clr', 'decodeEntities' => true, 'mandatory' => false),
			'sql'       => "text NULL",
		),
		'firstname'           => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['firstname'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'lastname'            => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['lastname'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'gender'              => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['gender'],
			'inputType' => 'select',
			'sorting'   => true,
			'options'   => array('male', 'female'),
			'reference' => &$GLOBALS['TL_LANG']['MSC'],
			'eval'      => array('mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
			'sql'       => "varchar(32) NOT NULL default ''",
		),
		'dateOfBirth'         => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['dateOfBirth'],
			'sorting'   => true,
			'flag'      => 5,
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
			'sql'       => "varchar(11) NOT NULL default ''",
		),
		'street'              => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['street'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'postal'              => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['postal'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 32, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
			'sql'       => "varchar(32) NOT NULL default ''",
		),
		'city'                => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['city'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'mobile'              => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['mobile'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
			'sql'       => "varchar(64) NOT NULL default ''",
		),
		'emergencyPhone'      => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhone'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
			'sql'       => "varchar(64) NOT NULL default ''",
		),
		'emergencyPhoneName'  => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhoneName'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 64, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
			'sql'       => "varchar(64) NOT NULL default ''",
		),
		'email'               => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['email'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'maxlength' => 255, 'rgxp' => 'email', 'unique' => false, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'sacMemberId'         => array(
			'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['sacMemberId'],
			'inputType'     => 'text',
			'save_callback' => array(array(TlCalendarEventsMember::class, 'saveCallbackSacMemberId')),
			'eval'          => array('doNotShow' => true, 'doNotCopy' => true, 'rgxp' => 'sacMemberId', 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'           => "varchar(255) NOT NULL default ''",
		),
		'foodHabits'          => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['foodHabits'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('tl_class' => 'clr'),
			'sql'       => "varchar(1024) NOT NULL default ''",
		),
		// Send E-mail
		'emailRecipients'     => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailRecipients'],
			'options'   => array(), // Set via onload callback
			'inputType' => 'checkbox',
			'eval'      => array('multiple' => true, 'mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => ''),
			'sql'       => "blob NULL",
		),
		'emailSubject'        => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSubject'],
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => ''),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'emailText'           => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailText'],
			'inputType' => 'textarea',
			'eval'      => array('mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'rows' => 6, 'style' => 'height:50px', 'tl_class' => ''),
			'sql'       => "mediumtext NULL",
		),
		'addEmailAttachment'  => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['addEmailAttachment'],
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'eval'      => array('submitOnChange' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'emailAttachment'     => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailAttachment'],
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => array('multiple' => true, 'fieldType' => 'checkbox', 'extensions' => Config::get('allowedDownload'), 'files' => true, 'filesOnly' => true, 'mandatory' => true),
			'sql'       => "binary(16) NULL",
		),
		'emailSendCopy'       => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSendCopy'],
			'inputType' => 'checkbox',
			'eval'      => array('doNotShow' => true, 'doNotCopy' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'agb'                 => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['agb'],
			'inputType' => 'checkbox',
			'eval'      => array('doNotShow' => true, 'doNotCopy' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'anonymized'          => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['anonymized'],
			'inputType' => 'checkbox',
			'eval'      => array('doNotShow' => true, 'doNotCopy' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'bookingType'         => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['bookingType'],
			'exclude'   => true,
			'inputType' => 'select',
			'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
			'options'   => array('onlineForm', 'manually'),
			'eval'      => array('doNotShow' => true, 'includeBlankOption' => false, 'doNotCopy' => true),
			'sql'       => "varchar(255) NOT NULL default 'manually'",
		),
		'sectionIds'         => array(
            'sorting' => true,
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['sectionIds'],
			'exclude'   => true,
			'inputType' => 'select',
			'reference' => &$GLOBALS['TL_LANG']['tl_member']['section'],
			'options_callback' => array(TlCalendarEventsMember::class, 'listSections'),
			'eval'      => array('multiple' => true, 'chosen' => true, 'doNotCopy' => true, 'readonly' => false, 'tl_class' => 'w50'),
			'sql'       => "blob NULL",
		),
	),
);
