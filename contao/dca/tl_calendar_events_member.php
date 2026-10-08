<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\DC_Table;
use Contao\DataContainer;
use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Markocupic\SacEventToolBundle\Config\CarSeatInfo;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\TicketInfo;

System::loadLanguageFile('tl_member');

$GLOBALS['TL_DCA']['tl_calendar_events_member'] = [
	'config'      => [
		'dataContainer'    => DC_Table::class,
		'ctable'           => [
			'tl_event_feedback_reminder',
		],
		'notCopyable'      => true,
		'enableVersioning' => true,
		'sql'              => [
			'keys' => [
				'id'            => 'primary',
				'email,eventId' => 'index',
				'sacMemberId'   => 'index',
			],
		],
	],
	'list'        => [
		'sorting'           => [
			'mode'        => DataContainer::SORT_INITIAL_LETTER_DESC,
			'fields'      => ['stateOfSubscription', 'dateAdded', 'lastname', 'firstname'],
			'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
			'panelLayout' => 'filter;sort,search',
			'filter'      => [['eventId=?', Input::get('id')]],
		],
		'label'             => [
			// The field J+S/Jugend does not exist and is only used to show the age group of the member
			'fields'      => ['stateOfSubscription', 'firstname', 'lastname', 'J+S/Jugend', 'street', 'city'],
			'showColumns' => true,
		],
		'global_operations' => [
			'all',
			'backToEventSettings'               => [
				'attributes'             => 'data-turbo="false" onclick="Backend.getScrollOffset()" accesskey="e"',
				'class'                  => 'back_to_event_settings',
				'custom_glob_op'         => true,
				'custom_glob_op_options' => ['add_to_menu_group' => 'registration', 'sorting' => 100],
				'href'                   => System::getContainer()->get('router')->generate('contao_backend', ['do' => 'calendar', 'table' => 'tl_calendar_events', 'id' => '%s', 'act' => 'edit', 'rt' => '%s', 'ref' => '%s']),
				'icon'                   => System::getContainer()->get('assets.packages')->getUrl('icons/fontawesome/left-regular.svg', 'markocupic_sac_event_tool'),
				'label'                  => &$GLOBALS['TL_LANG']['MSC']['backToEvent'],
			],
			'sendEmail'                         => [
				// use a button_callback for generating the url
				'attributes'             => 'data-turbo="false" onclick="Backend.getScrollOffset()" accesskey="e"',
				'class'                  => 'send_email',
				'custom_glob_op'         => true,
				'custom_glob_op_options' => ['add_to_menu_group' => 'registration', 'sorting' => 90],
				'icon'                   => System::getContainer()->get('assets.packages')->getUrl('icons/fontawesome/at-regular.svg', 'markocupic_sac_event_tool'),
			],
			'downloadEventRegistrationListCsv'  => [
				'attributes'             => 'data-turbo="false" onclick="Backend.getScrollOffset()" accesskey="e"',
				'class'                  => 'download_event_registration_list_csv',
				'custom_glob_op'         => true,
				'custom_glob_op_options' => ['add_to_menu_group' => 'registration', 'sorting' => 80],
				'href'                   => 'action=downloadEventRegistrationListCsv&key=noref', // Adding the "key" param to the url will prevent Contao of saving the url in the referer list: https://github.com/contao/contao/blob/178b1daf7a090fcb36351502705f4ce8ac57add6/core-bundle/src/EventListener/StoreRefererListener.php#L88C1-L88C1
				'icon'                   => System::getContainer()->get('assets.packages')->getUrl('icons/fontawesome/file-excel-regular.svg', 'markocupic_sac_event_tool'),
			],
			'downloadEventRegistrationListDocx' => [
				'attributes'             => 'data-turbo="false" onclick="Backend.getScrollOffset()" accesskey="e"',
				'class'                  => 'download_event_registration_list_docx',
				'custom_glob_op'         => true,
				'custom_glob_op_options' => ['add_to_menu_group' => 'registration', 'sorting' => 70],
				'href'                   => 'action=downloadEventRegistrationListDocx&key=noref', // Adding the "key" param to the url will prevent Contao of saving the url in the referer list: https://github.com/contao/contao/blob/178b1daf7a090fcb36351502705f4ce8ac57add6/core-bundle/src/EventListener/StoreRefererListener.php#L88C1-L88C1
				'icon'                   => System::getContainer()->get('assets.packages')->getUrl('icons/fontawesome/file-word-regular.svg', 'markocupic_sac_event_tool'),
			],
			'writeTourReport'                   => [
				'attributes'             => 'data-turbo="false" onclick="Backend.getScrollOffset()" accesskey="e"',
				'class'                  => 'write_tour_report',
				'custom_glob_op'         => true,
				'custom_glob_op_options' => ['add_to_menu_group' => 'tour_report', 'sorting' => 100],
				'href'                   => 'table=tl_calendar_events&act=edit&call=writeTourReport&id=%d',
				'icon'                   => System::getContainer()->get('assets.packages')->getUrl('icons/fontawesome/pencil-regular.svg', 'markocupic_sac_event_tool'),
			],
			'printInstructorInvoice'            => [
				'attributes'             => 'data-turbo="false" onclick="Backend.getScrollOffset()" accesskey="e"',
				'class'                  => 'print_instructor_invoice',
				'custom_glob_op'         => true,
				'custom_glob_op_options' => ['add_to_menu_group' => 'tour_report', 'sorting' => 90],
				'href'                   => 'table=tl_calendar_events_instructor_invoice&id=%d',
				'icon'                   => System::getContainer()->get('assets.packages')->getUrl('icons/fontawesome/print-regular.svg', 'markocupic_sac_event_tool'),
			],
		],
		'operations'        => [
			'edit',
			'delete',
			// Event history of the participant (see docs/features/participant-event-history.md), the URL is set by ParticipantEventHistoryOperationListener
			'participantEventHistory'  => [
				'icon'       => System::getContainer()->get('assets.packages')->getUrl('icons/fontawesome/list-check.svg', 'markocupic_sac_event_tool'),
				'attributes' => 'data-turbo="false"',
			],
			'show',
			'toggleParticipationState' => [
				'href' => 'act=toggle&amp;field=hasParticipated',
				'icon' => System::getContainer()->get('assets.packages')->getUrl('icons/fontawesome/square-check.svg', 'markocupic_sac_event_tool'),
			],
		],
	],
	'palettes'    => [
		'__selector__' => ['addEmailAttachment', 'hasLeadClimbingEducation', 'hasPaid'],
		'default'      => '
		{stateOfSubscription_legend},dashboard,stateOfSubscription,dateAdded,allowMultiSignUp,hasPaid;
		{notes_legend},carInfo,ticketInfo,foodHabits,notes,instructorNotes,bookingType;
		{sac_member_id_legend},sacMemberId;
		{personal_legend},firstname,lastname,gender,dateOfBirth,sectionId,ahvNumber;
		{address_legend:hide},street,postal,city;
		{contact_legend},phone,mobile,email;
		{education_legend},hasLeadClimbingEducation;
		{emergency_phone_legend},emergencyPhone,emergencyPhoneName;
		{stateOfParticipation_legend},hasParticipated;
		{deregistration_legend},deregistrationCause;
		{agb_legend},agb,hasAcknowledgedEventRequirements,avbSbv,hasAcceptedPrivacyRules;
		{onlineFeedback_legend},countOnlineEventFeedbackNotifications
		',
	],
	'subpalettes' => [
		'hasLeadClimbingEducation' => 'dateOfLeadClimbingEducation',
		'hasPaid'                  => 'paymentMethod',
	],
	'fields'      => [
		'id'                                    => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp'                                => [
			'sql' => "int(10) unsigned NOT NULL default 0",
		],
		'uuid'                                  => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['doNotCopy' => true, 'unique' => true],
			'sql'       => "char(36) NOT NULL default ''",
		],
		'contaoMemberId'                        => [
			'exclude'    => true,
			'foreignKey' => "tl_member.CONCAT(firstname, ' ', lastname)",
			'sql'        => "int(10) unsigned NOT NULL default 0",
			'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
			'eval'       => ['readonly' => true],
		],
		'eventId'                               => [
			'exclude'    => true,
			'foreignKey' => 'tl_calendar_events.title',
			'sql'        => "int(10) unsigned NOT NULL default 0",
			'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
			'eval'       => ['doNotShow' => true, 'readonly' => true],
		],
		'eventName'                             => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'dateAdded'                             => [
			'exclude'   => true,
			'inputType' => 'text',
			'flag'      => DataContainer::SORT_DAY_ASC,
			'sorting'   => true,
			'eval'      => ['datepicker' => true, 'doNotCopy' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard'],
			'sql'       => "bigint(11) NOT NULL default 0", // not unsigned, because negative integers are permitted
		],
		'stateOfSubscription'                   => [
			'exclude'   => true,
			'filter'    => true,
			'sorting'   => true,
			'inputType' => 'select',
			'reference' => &$GLOBALS['TL_LANG']['MSC'],
			'eval'      => ['doNotShow' => false, 'includeBlankOption' => false, 'maxlength' => 255, 'readonly' => false, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default '" . EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED . "'",
		],
		'gender'                                => [
			'exclude'   => true,
			'inputType' => 'select',
			'sorting'   => true,
			'options'   => ['male', 'female', 'other'],
			'reference' => &$GLOBALS['TL_LANG']['MSC'],
			'eval'      => ['includeBlankOption' => true, 'mandatory' => true, 'tl_class' => 'w50'],
			'sql'       => "varchar(32) NOT NULL default ''",
		],
		'firstname'                             => [
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'sorting'   => true,
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'lastname'                              => [
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'sorting'   => true,
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'dateOfBirth'                           => [
			'exclude'   => true,
			'sorting'   => true,
			'flag'      => DataContainer::SORT_DAY_ASC,
			'inputType' => 'text',
			'eval'      => ['datepicker' => true, 'mandatory' => false, 'rgxp' => 'date', 'tl_class' => 'w50 wizard'],
			'sql'       => "varchar(11) NOT NULL default ''",
		],
		'street'                                => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'postal'                                => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 32, 'tl_class' => 'w50'],
			'sql'       => "varchar(32) NOT NULL default ''",
		],
		'city'                                  => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'email'                                 => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['decodeEntities' => true, 'feGroup' => 'contact', 'mandatory' => false, 'maxlength' => 255, 'rgxp' => 'email', 'tl_class' => 'w50', 'unique' => false],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'phone'                                 => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['decodeEntities' => true, 'mandatory' => false, 'maxlength' => 64, 'rgxp' => 'phone', 'tl_class' => 'w50'],
			'sql'       => "varchar(64) NOT NULL default ''",
		],
		'mobile'                                => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['decodeEntities' => true, 'mandatory' => false, 'maxlength' => 64, 'rgxp' => 'phone', 'tl_class' => 'w50'],
			'sql'       => "varchar(64) NOT NULL default ''",
		],
		'sectionId'                             => [
			'sorting'   => true,
			'exclude'   => true,
			'inputType' => 'select',
			'eval'      => ['chosen' => true, 'doNotCopy' => true, 'multiple' => true, 'readonly' => false, 'tl_class' => 'w50'],
			'sql'       => 'blob NULL',
		],
		'sacMemberId'                           => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['doNotCopy' => true, 'doNotShow' => true, 'maxlength' => 10, 'rgxp' => 'sacMemberIdOrZero', 'tl_class' => 'clr'],
			'sql'       => "int(10) unsigned NOT NULL default 0",
		],
		'notes'                                 => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['decodeEntities' => true, 'mandatory' => false, 'maxlength' => 5000, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		'emergencyPhone'                        => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['decodeEntities' => true, 'mandatory' => true, 'maxlength' => 64, 'rgxp' => 'phone', 'tl_class' => 'w50'],
			'sql'       => "varchar(64) NOT NULL default ''",
		],
		'emergencyPhoneName'                    => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['decodeEntities' => true, 'mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'instructorNotes'                       => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['decodeEntities' => true, 'mandatory' => false, 'maxlength' => 5000, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		'hasLeadClimbingEducation'              => [
			'exclude'   => true,
			'filter'    => true,
			'sorting'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['submitOnChange' => true],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'dateOfLeadClimbingEducation'           => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['datepicker' => true, 'doNotCopy' => true, 'mandatory' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard'],
			'sql'       => "varchar(11) NOT NULL default ''",
		],
		'agb'                                   => [
			'inputType' => 'checkbox',
			'exclude'   => true,
			'eval'      => ['doNotCopy' => true, 'doNotShow' => false],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'hasAcknowledgedEventRequirements'      => [
			'inputType' => 'checkbox',
			'exclude'   => true,
			'eval'      => ['doNotCopy' => true, 'doNotShow' => false],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'avbSbv'                                => [
			'inputType' => 'checkbox',
			'exclude'   => true,
			'eval'      => ['doNotCopy' => true, 'doNotShow' => false],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'hasAcceptedPrivacyRules'               => [
			'inputType' => 'checkbox',
			'exclude'   => true,
			'eval'      => ['doNotCopy' => true, 'doNotShow' => false],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'ahvNumber'                             => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['decodeEntities' => true, 'mandatory' => false, 'maxlength' => 16, 'placeholder' => '756.7086.3589.03', 'rgxp' => 'ahv', 'tl_class' => 'w50', 'unique' => false],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'foodHabits'                            => [
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => ['maxlength' => 5000, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		'ticketInfo'                            => [
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => System::getContainer()->get(TicketInfo::class)->getAll(),
			'eval'      => ['doNotCopy' => true, 'doNotShow' => false, 'includeBlankOption' => true],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'carInfo'                               => [
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => System::getContainer()->get(CarSeatInfo::class)->getAll(),
			'eval'      => ['doNotCopy' => true, 'doNotShow' => false, 'includeBlankOption' => true],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'hasParticipated'                       => [
			'exclude'   => true,
			'toggle'    => true,
			'inputType' => 'checkbox',
			'eval'      => ['doNotCopy' => true, 'doNotShow' => false, 'submitOnChange' => true],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'hasPaid'                               => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'eval'      => ['mandatory' => false, 'submitOnChange' => true, 'tl_class' => 'clr m12'],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'paymentMethod'                         => [
			'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => ['cashPayment', 'bankTransfer', 'twint'],
			'eval'      => ['includeBlankOption' => true, 'mandatory' => true, 'tl_class' => 'w50'],
			'sql'       => "varchar(32) NOT NULL default ''",
		],
		'bookingType'                           => [
			'exclude'   => true,
			'inputType' => 'select',
			'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
			'options'   => BookingType::ALL,
			'eval'      => ['doNotCopy' => true, 'doNotShow' => true, 'includeBlankOption' => false, 'readonly' => true],
			'sql'       => "varchar(255) NOT NULL default '" . BookingType::MANUALLY . "'",
		],
		'deregistrationCause'                   => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['doNotCopy' => true, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		'allowMultiSignUp'                      => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['doNotCopy' => true, 'doNotShow' => false, 'submitOnChange' => true, 'tl_class' => 'long clr'],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'anonymized'                            => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['doNotCopy' => true, 'doNotShow' => true],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'dashboard'                             => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'countOnlineEventFeedbackNotifications' => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['doNotCopy' => true, 'readonly' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'],
			'sql'       => 'int(3) unsigned NOT NULL default 0',
		],
	],
];
