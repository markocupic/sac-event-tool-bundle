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
use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Config\CarSeatInfo;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\TicketInfo;
use Ramsey\Uuid\Uuid;

System::loadLanguageFile('tl_member');

$GLOBALS['TL_DCA']['tl_calendar_events_member'] = [
    'config'      => [
        'dataContainer'    => DC_Table::class,
        'notCopyable'      => true,
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id'            => 'primary',
                'email,eventId' => 'index',
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
            'fields'      => ['stateOfSubscription', 'firstname', 'lastname', 'street', 'city'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all',
            'backToEventSettings'               => [
                'label'                  => &$GLOBALS['TL_LANG']['MSC']['backToEvent'],
                'href'                   => System::getContainer()->get('router')->generate('contao_backend', ['do' => 'calendar', 'table' => 'tl_calendar_events', 'id' => '%s', 'act' => 'edit', 'rt' => '%s', 'ref' => '%s']),
                'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/left-regular.svg',
                'attributes'             => 'onclick="Backend.getScrollOffset()" accesskey="e"',
                'custom_glob_op'         => true,
                'custom_glob_op_options' => ['add_to_menu_group' => 'registration', 'sorting' => 100],
            ],
            'sendEmail'                         => [
                // use a button_callback for generating the url
                'class'                  => 'send_email',
                'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/at-regular.svg',
                'attributes'             => 'onclick="Backend.getScrollOffset()" accesskey="e"',
                'custom_glob_op'         => true,
                'custom_glob_op_options' => ['add_to_menu_group' => 'registration', 'sorting' => 90],
            ],
            'downloadEventRegistrationListCsv'  => [
                'href'                   => 'action=downloadEventRegistrationListCsv&key=noref', // Adding the "key" param to the url will prevent Contao of saving the url in the referer list: https://github.com/contao/contao/blob/178b1daf7a090fcb36351502705f4ce8ac57add6/core-bundle/src/EventListener/StoreRefererListener.php#L88C1-L88C1
                'class'                  => 'header_icon',
                'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/file-excel-regular.svg',
                'attributes'             => 'onclick="Backend.getScrollOffset()" accesskey="e"',
                'custom_glob_op'         => true,
                'custom_glob_op_options' => ['add_to_menu_group' => 'registration', 'sorting' => 80],
            ],
            'downloadEventRegistrationListDocx' => [
                'href'                   => 'action=downloadEventRegistrationListDocx&key=noref', // Adding the "key" param to the url will prevent Contao of saving the url in the referer list: https://github.com/contao/contao/blob/178b1daf7a090fcb36351502705f4ce8ac57add6/core-bundle/src/EventListener/StoreRefererListener.php#L88C1-L88C1
                'class'                  => 'download_event_registration_list',
                'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/file-word-regular.svg',
                'attributes'             => 'onclick="Backend.getScrollOffset()" accesskey="e"',
                'custom_glob_op'         => true,
                'custom_glob_op_options' => ['add_to_menu_group' => 'registration', 'sorting' => 70],
            ],
            'writeTourReport'                   => [
                'href'                   => 'table=tl_calendar_events&act=edit&call=writeTourReport&id=%d',
                'class'                  => 'writeTourRapport',
                'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/pencil-regular.svg',
                'attributes'             => 'onclick="Backend.getScrollOffset()" accesskey="e"',
                'custom_glob_op'         => true,
                'custom_glob_op_options' => ['add_to_menu_group' => 'tour_report', 'sorting' => 100],
            ],
            'printInstructorInvoice'            => [
                'href'                   => 'table=tl_calendar_events_instructor_invoice&id=%d',
                'class'                  => 'printInstructorInvoice',
                'icon'                   => Bundle::ASSET_DIR.'/icons/fontawesome/default/print-regular.svg',
                'attributes'             => 'onclick="Backend.getScrollOffset()" accesskey="e"',
                'custom_glob_op'         => true,
                'custom_glob_op_options' => ['add_to_menu_group' => 'tour_report', 'sorting' => 90],
            ],
        ],
        'operations'        => [
            'edit',
            'delete',
            'show',
            'toggleParticipationState' => [
                'href' => 'act=toggle&amp;field=hasParticipated',
                'icon' => Bundle::ASSET_DIR.'/icons/fontawesome/default/square-check-regular.svg',
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
		{contact_legend},mobile,email;
		{education_legend},hasLeadClimbingEducation;
		{emergency_phone_legend},emergencyPhone,emergencyPhoneName;
		{stateOfParticipation_legend},hasParticipated;
		{agb_legend},agb,hasAcceptedPrivacyRules
		',
    ],
    'subpalettes' => [
        'hasLeadClimbingEducation' => 'dateOfLeadClimbingEducation',
        'hasPaid'                  => 'paymentMethod',
    ],
    'fields'      => [
        'id'                          => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp'                      => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'uuid'                        => [
            'exclude'   => true,
            'inputType' => 'text',
            'default'   => Uuid::uuid4()->toString(),
            'eval'      => ['unique' => true, 'doNotCopy' => true],
            'sql'       => "char(36) NOT NULL default ''",
        ],
        'contaoMemberId'              => [
            'exclude'    => true,
            'foreignKey' => "tl_member.CONCAT(firstname, ' ', lastname)",
            'sql'        => "int(10) unsigned NOT NULL default 0",
            'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
            'eval'       => ['readonly' => true],
        ],
        'eventId'                     => [
            'exclude'    => true,
            'foreignKey' => 'tl_calendar_events.title',
            'default'    => Input::get('id'),
            'sql'        => "int(10) unsigned NOT NULL default 0",
            'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
            'eval'       => ['doNotShow' => true, 'readonly' => true],
        ],
        'eventName'                   => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'dateAdded'                   => [
            'exclude'   => true,
            'inputType' => 'text',
            'flag'      => DataContainer::SORT_DAY_ASC,
            'sorting'   => true,
            'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'doNotCopy' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "bigint(11) NOT NULL default 0", // not unsigned, because negative integers are permitted
        ],
        'stateOfSubscription'         => [
            'exclude'   => true,
            'filter'    => true,
            'sorting'   => true,
            'inputType' => 'select',
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'eval'      => ['doNotShow' => false, 'readonly' => false, 'includeBlankOption' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default '".EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED."'",
        ],
        'gender'                      => [
            'exclude'   => true,
            'inputType' => 'select',
            'sorting'   => true,
            'options'   => ['male', 'female'],
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'eval'      => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'firstname'                   => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'lastname'                    => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'dateOfBirth'                 => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_DAY_ASC,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "varchar(11) NOT NULL default ''",
        ],
        'street'                      => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'postal'                      => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'city'                        => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'email'                       => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'rgxp' => 'email', 'unique' => false, 'decodeEntities' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'mobile'                      => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sectionId'                   => [
            'sorting'   => true,
            'exclude'   => true,
            'inputType' => 'select',
            'eval'      => ['multiple' => true, 'chosen' => true, 'doNotCopy' => true, 'readonly' => false, 'tl_class' => 'w50'],
            'sql'       => 'blob NULL',
        ],
        'sacMemberId'                 => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['doNotShow' => true, 'doNotCopy' => true, 'rgxp' => 'sacMemberId', 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'notes'                       => [
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr', 'maxlength' => 5000, 'decodeEntities' => true, 'mandatory' => false],
            'sql'       => 'text NULL',
        ],
        'emergencyPhone'              => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'emergencyPhoneName'          => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'instructorNotes'             => [
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr', 'maxlength' => 5000, 'decodeEntities' => true, 'mandatory' => false],
            'sql'       => 'text NULL',
        ],
        'hasLeadClimbingEducation'    => [
            'exclude'   => true,
            'filter'    => true,
            'sorting'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'dateOfLeadClimbingEducation' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'date', 'datepicker' => true, 'doNotCopy' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "varchar(11) NOT NULL default ''",
        ],
        'agb'                         => [
            'inputType' => 'checkbox',
            'exclude'   => true,
            'eval'      => ['doNotShow' => false, 'doNotCopy' => true],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'hasAcceptedPrivacyRules'     => [
            'inputType' => 'checkbox',
            'exclude'   => true,
            'eval'      => ['doNotShow' => false, 'doNotCopy' => true],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'ahvNumber'                   => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 16, 'unique' => false, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'foodHabits'                  => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['tl_class' => 'clr', 'maxlength' => 5000],
            'sql'       => 'text NULL',
        ],
        'ticketInfo'                  => [
            'exclude'   => true,
            'inputType' => 'select',
            'options'   => System::getContainer()->get(TicketInfo::class)->getAll(),
            'eval'      => ['includeBlankOption' => true, 'doNotShow' => false, 'doNotCopy' => true],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'carInfo'                     => [
            'exclude'   => true,
            'inputType' => 'select',
            'options'   => System::getContainer()->get(CarSeatInfo::class)->getAll(),
            'eval'      => ['includeBlankOption' => true, 'doNotShow' => false, 'doNotCopy' => true],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'hasParticipated'             => [
            'exclude'   => true,
            'toggle'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['doNotShow' => false, 'submitOnChange' => true, 'doNotCopy' => true],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'hasPaid'                     => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr m12', 'mandatory' => false],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'paymentMethod'               => [
            'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
            'exclude'   => true,
            'inputType' => 'select',
            'options'   => ['cashPayment', 'bankTransfer', 'twint'],
            'eval'      => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'bookingType'                 => [
            'exclude'   => true,
            'inputType' => 'select',
            'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
            'options'   => BookingType::ALL,
            'eval'      => ['readonly' => true, 'doNotShow' => true, 'includeBlankOption' => false, 'doNotCopy' => true],
            'sql'       => "varchar(255) NOT NULL default '".BookingType::MANUALLY."'",
        ],
        'allowMultiSignUp'            => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true, 'doNotShow' => false, 'doNotCopy' => true, 'tl_class' => 'long clr'],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'anonymized'                  => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['doNotShow' => true, 'doNotCopy' => true],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'dashboard'                   => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
    ],
];
