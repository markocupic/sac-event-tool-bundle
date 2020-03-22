<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Table tl_calendar_events_member
 */
$GLOBALS['TL_DCA']['tl_calendar_events_member'] = [

    'config'      => [
        'dataContainer'     => 'Table',
        'notCopyable'       => true,
        // Do not copy nor delete records, if an item has been deleted!
        'onload_callback'   => [
            ['tl_calendar_events_member', 'setStateOfSubscription'],
            ['tl_calendar_events_member', 'onloadCallback'],
            ['tl_calendar_events_member', 'reviseTable'],
            ['tl_calendar_events_member', 'setContaoMemberIdFromSacMemberId'],
            ['tl_calendar_events_member', 'setGlobalOperations'],
            ['tl_calendar_events_member', 'onloadCallbackExportMemberlist'],
        ],
        'onsubmit_callback' => [
            ['tl_calendar_events_member', 'onsubmitCallback'],
        ],
        'ondelete_callback' => [//
        ],
        'sql'               => [
            'keys' => [
                'id'            => 'primary',
                'email,eventId' => 'index',
            ],
        ],
    ],
    // Buttons callback
    'edit'        => [
        'buttons_callback' => [['tl_calendar_events_member', 'buttonsCallback']],
    ],

    // List
    'list'        => [
        'sorting'           => [
            'mode'        => 2,
            'fields'      => ['stateOfSubscription, addedOn'],
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search',
            'filter'      => [['eventId=?', Input::get('id')]],
        ],
        'label'             => [
            'fields'         => ['stateOfSubscription', 'firstname', 'lastname', 'street', 'city'],
            'showColumns'    => true,
            'label_callback' => ['tl_calendar_events_member', 'addIcon'],
        ],
        'global_operations' => [
            'all'                            => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'downloadEventMemberList2Docx'        => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['downloadEventMemberList2Docx'],
                'href'       => 'act=downloadEventMemberList',
                'class'      => 'download_registration_list',
                'icon'       => 'bundles/markocupicsaceventtool/icons/docx.png',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'downloadEventMemberList2Csv' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['downloadEventMemberList2Csv'],
                'href'       => 'action=onloadCallbackExportMemberlist',
                'icon'       => 'bundles/markocupicsaceventtool/icons/excel-file.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'writeTourReport'                => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['writeTourReportButton'],
                'href'       => '',
                'class'      => 'writeTourRapport',
                'icon'       => 'edit.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'printInstructorInvoice'         => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['printInstructorInvoiceButton'],
                'href'       => '',
                'class'      => 'printInstructorInvoice',
                'icon'       => 'bundles/markocupicsaceventtool/icons/docx.png',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],

            'sendEmail'                      => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['sendEmail'],
                'href'       => 'act=edit&call=sendEmail',
                'class'      => 'send_email',
                'icon'       => 'bundles/markocupicsaceventtool/icons/enveloppe.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'backToEventSettings'            => [
                'label'           => &$GLOBALS['TL_LANG']['MSC']['backToEvent'],
                'href'            => 'contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s&ref=%s',
                'button_callback' => ['tl_calendar_events_member', 'buttonCbBackToEventSettings'],
                'icon'            => 'bundles/markocupicsaceventtool/icons/back.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg',
            ],

            'delete'                     => [
                'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ],
            // Regular "toggle" operation but without "icon" and with the haste specific params
            'toggleStateOfParticipation' => [
                'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['toggleStateOfParticipation'],
                'attributes'           => 'onclick="Backend.getScrollOffset();"',
                'haste_ajax_operation' => [
                    'field'   => 'hasParticipated',
                    'options' => [
                        [
                            'value' => '',
                            'icon'  => Config::get('SAC_EVT_ASSETS_DIR') . '/icons/has-not-participated.svg',
                        ],
                        [
                            'value' => '1',
                            'icon'  => Config::get('SAC_EVT_ASSETS_DIR') . '/icons/has-participated.svg',
                        ],
                    ],
                ],
            ],
            'show'                       => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],

        ],
    ],

    // Palettes
    'palettes'    => [
        '__selector__'    => ['addEmailAttachment'],
        'default'         => '{stateOfSubscription_legend},dashboard,stateOfSubscription,addedOn;{notes_legend},carInfo,ticketInfo,notes,instructorNotes,bookingType;{sac_member_id_legend},sacMemberId;{personal_legend},firstname,lastname,gender,dateOfBirth,foodHabits;{address_legend:hide},street,postal,city;{contact_legend},mobile,email;{emergency_phone_legend},emergencyPhone,emergencyPhoneName;{stateOfParticipation_legend},hasParticipated;',
        'sendEmail'       => '{sendEmail_legend},emailRecipients,emailSubject,emailText,addEmailAttachment,emailSendCopy;',
        'refuseWithEmail' => 'refuseWithEmail;',
        'acceptWithEmail' => 'acceptWithEmail;',
        'addToWaitlist'   => 'addToWaitlist;',
    ],

    // Subpalettes
    'subpalettes' => [
        'addEmailAttachment' => 'emailAttachment',
    ],

    // Fields
    'fields'      => [
        'id'                  => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'eventId'             => [
            'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['eventId'],
            'foreignKey' => 'tl_calendar_events.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
            'eval'       => ['readonly' => true],
        ],
        'contaoMemberId'      => [
            'label'      => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['contaoMemberId'],
            'foreignKey' => "tl_member.CONCAT(firstname, ' ', lastname)",
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
            'eval'       => ['readonly' => true],
        ],
        'tstamp'              => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'addedOn'             => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['addedOn'],
            'inputType' => 'text',
            'flag'      => 5,
            'sorting'   => true,
            'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],
        'stateOfSubscription' => [
            'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfSubscription'],
            'filter'        => true,
            'inputType'     => 'select',
            'save_callback' => [['tl_calendar_events_member', 'saveCallbackStateOfSubscription']],
            'default'       => 'subscription-not-confirmed',
            'reference'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
            'options'       => ['subscription-not-confirmed', 'subscription-accepted', 'subscription-refused', 'subscription-waitlisted', 'user-has-unsubscribed'],
            'eval'          => ['doNotShow' => false, 'readonly' => false, 'includeBlankOption' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'           => "varchar(255) NOT NULL default ''",
        ],
        'carInfo'             => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['carInfo'],
            'inputType' => 'select',
            'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['carSeatsInfo'],
            'eval'      => ['includeBlankOption' => true, 'doNotShow' => false, 'doNotCopy' => true,],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'ticketInfo'          => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['ticketInfo'],
            'inputType' => 'select',
            'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['ticketInfo'],
            'eval'      => ['includeBlankOption' => true, 'doNotShow' => false, 'doNotCopy' => true,],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'hasParticipated'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['hasParticipated'],
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true, 'doNotShow' => false, 'doNotCopy' => true,],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'dashboard'           => [
            'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['dashboard'],
            'inputType'            => 'text',
            'input_field_callback' => ['tl_calendar_events_member', 'inputFieldCallbackDashboard'],
            'eval'                 => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'                  => "varchar(255) NOT NULL default ''",
        ],
        'refuseWithEmail'     => [
            'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['refuseWithEmail'],
            'inputType'            => 'text',
            'input_field_callback' => ['tl_calendar_events_member', 'inputFieldCallbackNotifyMemberAboutSubscriptionState'],
            'eval'                 => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'                  => "varchar(255) NOT NULL default ''",
        ],
        'acceptWithEmail'     => [
            'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['acceptWithEmail'],
            'inputType'            => 'text',
            'input_field_callback' => ['tl_calendar_events_member', 'inputFieldCallbackNotifyMemberAboutSubscriptionState'],
            'eval'                 => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'                  => "varchar(255) NOT NULL default ''",
        ],
        'addToWaitlist'       => [
            'label'                => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['acceptWithEmail'],
            'inputType'            => 'text',
            'input_field_callback' => ['tl_calendar_events_member', 'inputFieldCallbackNotifyMemberAboutSubscriptionState'],
            'eval'                 => ['doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'                  => "varchar(255) NOT NULL default ''",
        ],
        'eventName'           => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['eventName'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'notes'               => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['notes'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr', 'decodeEntities' => true, 'mandatory' => false],
            'sql'       => "text NULL",
        ],
        'instructorNotes'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['instructorNotes'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr', 'decodeEntities' => true, 'mandatory' => false],
            'sql'       => "text NULL",
        ],
        'firstname'           => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['firstname'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'lastname'            => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['lastname'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'gender'              => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['gender'],
            'inputType' => 'select',
            'sorting'   => true,
            'options'   => ['male', 'female'],
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'eval'      => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'dateOfBirth'         => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['dateOfBirth'],
            'sorting'   => true,
            'flag'      => 5,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "varchar(11) NOT NULL default ''",
        ],
        'street'              => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['street'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'postal'              => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['postal'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 32, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'city'                => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['city'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'mobile'              => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['mobile'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'emergencyPhone'      => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhone'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'emergencyPhoneName'  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhoneName'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 64, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'email'               => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['email'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'rgxp' => 'email', 'unique' => false, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'sacMemberId'         => [
            'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['sacMemberId'],
            'inputType'     => 'text',
            'save_callback' => [['tl_calendar_events_member', 'saveCallbackSacMemberId']],
            'eval'          => ['doNotShow' => true, 'doNotCopy' => true, 'rgxp' => 'sacMemberId', 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'           => "varchar(255) NOT NULL default ''",
        ],
        'foodHabits'          => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['foodHabits'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => "varchar(1024) NOT NULL default ''",
        ],
        // Send E-mail
        'emailRecipients'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailRecipients'],
            'options'   => [], // Set via onload callback
            'inputType' => 'checkbox',
            'eval'      => ['multiple' => true, 'mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => ''],
            'sql'       => "blob NULL",
        ],
        'emailSubject'        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSubject'],
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => ''],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'emailText'           => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailText'],
            'inputType' => 'textarea',
            'eval'      => ['mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'rows' => 6, 'style' => 'height:50px', 'tl_class' => ''],
            'sql'       => "mediumtext NULL",
        ],
        'addEmailAttachment'  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['addEmailAttachment'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'emailAttachment'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailAttachment'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['multiple' => true, 'fieldType' => 'checkbox', 'extensions' => Config::get('allowedDownload'), 'files' => true, 'filesOnly' => true, 'mandatory' => true],
            'sql'       => "binary(16) NULL",
        ],
        'emailSendCopy'       => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSendCopy'],
            'inputType' => 'checkbox',
            'eval'      => ['doNotShow' => true, 'doNotCopy' => true,],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'agb'                 => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['agb'],
            'inputType' => 'checkbox',
            'eval'      => ['doNotShow' => true, 'doNotCopy' => true,],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'anonymized'          => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['anonymized'],
            'inputType' => 'checkbox',
            'eval'      => ['doNotShow' => true, 'doNotCopy' => true,],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'bookingType'         => [
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['bookingType'],
            'exclude'   => true,
            'inputType' => 'select',
            'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
            'options'   => ['onlineForm', 'manually'],
            'eval'      => ['doNotShow' => true, 'includeBlankOption' => false, 'doNotCopy' => true,],
            'sql'       => "varchar(255) NOT NULL default 'manually'",
        ],
    ],
];

