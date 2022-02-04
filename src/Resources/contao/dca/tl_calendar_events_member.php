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
use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Dca\TlCalendarEventsMember;
use Ramsey\Uuid\Uuid;

System::loadLanguageFile('tl_member');

/*
 * Table tl_calendar_events_member
 */
$GLOBALS['TL_DCA']['tl_calendar_events_member'] = [
    'config' => [
        'dataContainer' => 'Table',
        'notCopyable' => true,
        // Do not copy nor delete records, if an item has been deleted!
        'onload_callback' => [
            [
                TlCalendarEventsMember::class,
                'setStateOfSubscription',
            ],
            [
                TlCalendarEventsMember::class,
                'onloadCallback',
            ],
            [
                TlCalendarEventsMember::class,
                'reviseTable',
            ],
            [
                TlCalendarEventsMember::class,
                'setContaoMemberIdFromSacMemberId',
            ],
            [
                TlCalendarEventsMember::class,
                'setGlobalOperations',
            ],
            [
                TlCalendarEventsMember::class,
                'onloadCallbackExportMemberlist',
            ],
        ],
        'onsubmit_callback' => [
            [
                TlCalendarEventsMember::class,
                'onsubmitCallback',
            ],
        ],
        'ondelete_callback' => [],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'email,eventId' => 'index',
            ],
        ],
    ],
    // Buttons callback
    'edit' => [
        'buttons_callback' => [
            [
                TlCalendarEventsMember::class,
                'buttonsCallback',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 2,
            'fields' => ['stateOfSubscription, addedOn'],
            'flag' => 1,
            'panelLayout' => 'filter;sort,search',
            'filter' => [['eventId=?', Input::get('id')]],
        ],
        'label' => [
            'fields' => ['stateOfSubscription', 'firstname', 'lastname', 'street', 'city'],
            'showColumns' => true,
            'label_callback' => [
                TlCalendarEventsMember::class,
                'addIcon',
            ],
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'downloadEventMemberList2Docx' => [
                'href' => 'act=downloadEventMemberList',
                'class' => 'download_registration_list',
                'icon' => 'bundles/markocupicsaceventtool/icons/docx.png',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'downloadEventMemberList2Csv' => [
                'href' => 'action=onloadCallbackExportMemberlist',
                'class' => 'header_icon',
                'icon' => 'bundles/markocupicsaceventtool/icons/excel.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'writeTourReport' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['writeTourReportButton'],
                'href' => '',
                'class' => 'writeTourRapport',
                'icon' => 'edit.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'printInstructorInvoice' => [
                'href' => '',
                'class' => 'printInstructorInvoice',
                'icon' => 'bundles/markocupicsaceventtool/icons/docx.png',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'sendEmail' => [
                'href' => 'act=edit&call=sendEmail',
                'class' => 'send_email',
                'icon' => 'bundles/markocupicsaceventtool/icons/enveloppe.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'backToEventSettings' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['backToEvent'],
                'href' => 'contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s&ref=%s',
                'button_callback' => [
                    TlCalendarEventsMember::class,
                    'buttonCbBackToEventSettings',
                ],
                'icon' => 'bundles/markocupicsaceventtool/icons/back.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\'))return false;Backend.getScrollOffset()"',
            ],
            // Regular "toggle" operation but without "icon" and with the haste specific params
            'toggleStateOfParticipation' => [
                'attributes' => 'onclick="Backend.getScrollOffset();"',
                'haste_ajax_operation' => [
                    'field' => 'hasParticipated',
                    'options' => [
                        [
                            'value' => '',
                            'icon' => Bundle::ASSET_DIR.'/icons/has-not-participated.svg',
                        ],
                        [
                            'value' => '1',
                            'icon' => Bundle::ASSET_DIR.'/icons/has-participated.svg',
                        ],
                    ],
                ],
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes' => [
        '__selector__' => ['addEmailAttachment', 'hasLeadClimbingEducation', 'hasPaid'],
        'default' => '{stateOfSubscription_legend},dashboard,stateOfSubscription,addedOn,allowMultiSignUp,hasPaid;{notes_legend},carInfo,ticketInfo,foodHabits,notes,instructorNotes,bookingType;{sac_member_id_legend},sacMemberId;{personal_legend},firstname,lastname,gender,dateOfBirth,sectionIds,ahvNumber;{address_legend:hide},street,postal,city;{contact_legend},mobile,email;{education_legend},hasLeadClimbingEducation;{emergency_phone_legend},emergencyPhone,emergencyPhoneName;{stateOfParticipation_legend},hasParticipated',
        'sendEmail' => '{sendEmail_legend},emailRecipients,emailSubject,emailText,addEmailAttachment,emailSendCopy',
        'refuseWithEmail' => '{refuseWithEmail_legend},refuseWithEmail',
        'acceptWithEmail' => '{acceptWithEmail_legend},acceptWithEmail',
        'addToWaitlist' => '{addToWaitlist_legend},addToWaitlist',
    ],
    'subpalettes' => [
        'addEmailAttachment' => 'emailAttachment',
        'hasLeadClimbingEducation' => 'dateOfLeadClimbingEducation',
        'hasPaid' => 'paymentMethod',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'eventId' => [
            'foreignKey' => 'tl_calendar_events.title',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'eager'],
            'eval' => ['readonly' => true],
        ],
        'contaoMemberId' => [
            'foreignKey' => "tl_member.CONCAT(firstname, ' ', lastname)",
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => ['type' => 'belongsTo', 'load' => 'eager'],
            'eval' => ['readonly' => true],
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'uuid' => [
            'inputType' => 'text',
            'default' => Uuid::uuid4()->toString(),
            'eval' => ['unique' => true, 'doNotCopy' => true],
            'sql' => "char(36) NOT NULL default ''",
        ],
        'addedOn' => [
            'inputType' => 'text',
            'flag' => 5,
            'sorting' => true,
            'eval' => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(10) NOT NULL default ''",
        ],
        'allowMultiSignUp' => [
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true, 'doNotShow' => false, 'doNotCopy' => true, 'tl_class' => 'long clr'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'hasPaid' => [
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true, 'tl_class' => 'clr m12', 'mandatory' => false],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'paymentMethod' => [
            'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => ['cashPayment', 'bankTransfer', 'twint'],
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'stateOfSubscription' => [
            'filter' => true,
            'inputType' => 'select',
            'save_callback' => [
                [
                    TlCalendarEventsMember::class,
                    'saveCallbackStateOfSubscription',
                ],
            ],
            'default' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'][0],
            'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
            'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'],
            'eval' => ['doNotShow' => false, 'readonly' => false, 'includeBlankOption' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'carInfo' => [
            'inputType' => 'select',
            'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'],
            'eval' => ['includeBlankOption' => true, 'doNotShow' => false, 'doNotCopy' => true],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'ticketInfo' => [
            'inputType' => 'select',
            'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'],
            'eval' => ['includeBlankOption' => true, 'doNotShow' => false, 'doNotCopy' => true],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'hasParticipated' => [
            'inputType' => 'checkbox',
            'eval' => ['doNotShow' => false, 'submitOnChange' => true, 'doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'dashboard' => [
            'inputType' => 'text',
            'input_field_callback' => [
                TlCalendarEventsMember::class,
                'inputFieldCallbackDashboard',
            ],
            'eval' => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'refuseWithEmail' => [
            'inputType' => 'text',
            'input_field_callback' => [
                TlCalendarEventsMember::class,
                'inputFieldCallbackNotifyMemberAboutSubscriptionState',
            ],
            'eval' => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'acceptWithEmail' => [
            'inputType' => 'text',
            'input_field_callback' => [
                TlCalendarEventsMember::class,
                'inputFieldCallbackNotifyMemberAboutSubscriptionState',
            ],
            'eval' => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'addToWaitlist' => [
            'inputType' => 'text',
            'input_field_callback' => [
                TlCalendarEventsMember::class,
                'inputFieldCallbackNotifyMemberAboutSubscriptionState',
            ],
            'eval' => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'eventName' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'notes' => [
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['tl_class' => 'clr', 'maxlength' => 5000, 'decodeEntities' => true, 'mandatory' => false],
            'sql' => 'text NULL',
        ],
        'instructorNotes' => [
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['tl_class' => 'clr', 'maxlength' => 5000, 'decodeEntities' => true, 'mandatory' => false],
            'sql' => 'text NULL',
        ],
        'firstname' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'lastname' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'gender' => [
            'inputType' => 'select',
            'sorting' => true,
            'options' => ['male', 'female'],
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'dateOfBirth' => [
            'sorting' => true,
            'flag' => 5,
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(11) NOT NULL default ''",
        ],
        'street' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'postal' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 32, 'tl_class' => 'w50'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'city' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'mobile' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'emergencyPhone' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'emergencyPhoneName' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'email' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'maxlength' => 255, 'rgxp' => 'email', 'unique' => false, 'decodeEntities' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'ahvNumber' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'maxlength' => 16, 'unique' => false, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'sacMemberId' => [
            'inputType' => 'text',
            'save_callback' => [
                [
                    TlCalendarEventsMember::class,
                    'saveCallbackSacMemberId',
                ],
            ],
            'eval' => ['doNotShow' => true, 'doNotCopy' => true, 'rgxp' => 'sacMemberId', 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'hasLeadClimbingEducation' => [
            'exclude' => true,
            'filter' => true,
            'sorting' => true,
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'dateOfLeadClimbingEducation' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql' => "varchar(11) NOT NULL default ''",
        ],
        'foodHabits' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['tl_class' => 'clr', 'maxlength' => 5000],
            'sql' => 'text NULL',
        ],
       'emailRecipients' => [
            'options' => [],
            // Set via onload callback
            'inputType' => 'checkbox',
            'eval' => ['multiple' => true, 'mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => ''],
            'sql' => 'blob NULL',
        ],
        'emailSubject' => [
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => ''],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'emailText' => [
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'rows' => 6, 'style' => 'height:50px', 'tl_class' => ''],
            'sql' => 'mediumtext NULL',
        ],
        'addEmailAttachment' => [
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'eval' => ['submitOnChange' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'emailAttachment' => [
            'exclude' => true,
            'inputType' => 'fileTree',
            'eval' => ['multiple' => true, 'fieldType' => 'checkbox', 'extensions' => Config::get('allowedDownload'), 'files' => true, 'filesOnly' => true, 'mandatory' => true],
            'sql' => 'binary(16) NULL',
        ],
        'emailSendCopy' => [
            'inputType' => 'checkbox',
            'eval' => ['doNotShow' => true, 'doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'agb' => [
            'inputType' => 'checkbox',
            'eval' => ['doNotShow' => true, 'doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'anonymized' => [
            'inputType' => 'checkbox',
            'eval' => ['doNotShow' => true, 'doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'bookingType' => [
            'exclude' => true,
            'inputType' => 'select',
            'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
            'options' => ['onlineForm', 'manually'],
            'eval' => ['doNotShow' => true, 'includeBlankOption' => false, 'doNotCopy' => true],
            'sql' => "varchar(255) NOT NULL default 'manually'",
        ],
        'sectionIds' => [
            'sorting' => true,
            'exclude' => true,
            'inputType' => 'select',
            'reference' => &$GLOBALS['TL_LANG']['tl_member']['section'],
            'options_callback' => [
                TlCalendarEventsMember::class,
                'listSections',
            ],
            'eval' => ['multiple' => true, 'chosen' => true, 'doNotCopy' => true, 'readonly' => false, 'tl_class' => 'w50'],
            'sql' => 'blob NULL',
        ],
    ],
];
