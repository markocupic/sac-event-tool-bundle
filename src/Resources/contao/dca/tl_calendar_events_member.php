<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */



/**
 * Table tl_calendar_events_member
 */
$GLOBALS['TL_DCA']['tl_calendar_events_member'] = array
(

    // Config
    'config' => array
    (
        'dataContainer' => 'Table',
        'ptable' => 'tl_calendar_events',
        //'enableVersioning' => true,
        'notCopyable' => true,
        'onsubmit_callback' => array
        (//
        ),
        'onload_callback' => array
        (
            array('tl_calendar_events_member', 'setStateOfSubscription'),
            array('tl_calendar_events_member', 'onloadCallback'),
            array('tl_calendar_events_member', 'setContaoMemberIdFromSacMemberId'),
        ),
        'onsubmit_callback' => array
        (
            array('tl_calendar_events_member', 'onsubmitCallback'),
        ),
        'ondelete_callback' => array
        (//
        ),
        'sql' => array
        (
            'keys' => array
            (
                'id' => 'primary',
                'email,pid' => 'index',
            )
        )
    ),
    // Buttons callback
    'edit' => array(
        'buttons_callback' => array(array('tl_calendar_events_member', 'buttonsCallback'))
    ),

    // List
    'list' => array
    (
        'sorting' => array
        (
            'mode' => 2,
            'fields' => array('stateOfSubscription, addedOn'),
            'flag' => 1,
            'panelLayout' => 'filter;sort,search'
        ),
        'label' => array
        (
            'fields' => array('stateOfSubscription', 'firstname', 'lastname', 'street', 'city'),
            'showColumns' => true,
            'label_callback' => array('tl_calendar_events_member', 'addIcon')
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ),
            'downloadRegistrationList' => array
            (
                'label' => &$GLOBALS['TL_LANG']['MSC']['downloadRegistrationList'],
                'href' => 'act=downloadRegistrationList',
                'class' => 'download_registration_list',
                'icon' => 'bundles/markocupicsaceventtool/icons/excel-file.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ),
            'sendEmail' => array
            (
                'label' => &$GLOBALS['TL_LANG']['MSC']['sendEmail'],
                'href' => 'act=edit&call=sendEmail',
                'class' => 'send_email',
                'icon' => 'bundles/markocupicsaceventtool/icons/enveloppe.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ),

        ),
        'operations' => array
        (
            'edit' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.svg'
            ),

            'delete' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            // Regular "toggle" operation but without "icon" and with the haste specific params
            'toggleStateOfParticipation' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['toggleStateOfParticipation'],
                'attributes' => 'onclick="Backend.getScrollOffset();"',
                'haste_ajax_operation' => [
                    'field' => 'hasParticipated',
                    'options' => [
                        [
                            'value' => '',
                            'icon' => SAC_EVT_ASSETS_DIR . '/icons/has-not-participated.svg'
                        ],
                        [
                            'value' => '1',
                            'icon' => SAC_EVT_ASSETS_DIR . '/icons/has-participated.svg'
                        ]
                    ]
                ]
            ),
            'show' => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['show'],
                'href' => 'act=show',
                'icon' => 'show.svg'
            )

        )
    ),

    // Palettes
    'palettes' => array
    (
        'default' => '{stateOfSubscription_legend},dashboard,stateOfSubscription,addedOn;{notes_legend}, notes;{sac_member_id_legend},sacMemberId;{personal_legend},firstname,lastname,gender,dateOfBirth,vegetarian;{address_legend:hide},street,postal,city;{contact_legend},phone,email;{emergency_phone_legend},emergencyPhone,emergencyPhoneName;{stateOfParticipation_legend},hasParticipated;',
        'sendEmail' => '{sendEmail_legend},emailRecipients,emailSubject,emailText,emailSendCopy;',
        'refuseWithEmail' => 'refuseWithEmail;',
        'acceptWithEmail' => 'acceptWithEmail;',
        'addToWaitlist' => 'addToWaitlist;',
    ),

    // Subpalettes
    'subpalettes' => array
    (//
    ),


    // Fields
    'fields' => array
    (
        'id' => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'pid' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['pid'],
            'foreignKey' => 'tl_calendar_events.title',
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => array('type' => 'belongsTo', 'load' => 'eager'),
            'eval' => array('readonly' => true),
        ),
        'contaoMemberId' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['contaoMemberId'],
            'foreignKey' => "tl_member.CONCAT(firstname, ' ', lastname)",
            'sql' => "int(10) unsigned NOT NULL default '0'",
            'relation' => array('type' => 'belongsTo', 'load' => 'eager'),
            'eval' => array('readonly' => true),
        ),
        'tstamp' => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'addedOn' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['addedOn'],
            'inputType' => 'text',
            'eval' => array('rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
            'sql' => "varchar(10) NOT NULL default ''"
        ),
        'stateOfSubscription' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['stateOfSubscription'],
            'filter' => true,
            'inputType' => 'select',
            'default' => 'subscription-not-confirmed',
            'reference' => &$GLOBALS['TL_LANG']['tl_calendar_events_member'],
            'options' => array('subscription-not-confirmed', 'subscription-accepted', 'subscription-refused', 'subscription-waitlisted'),
            'eval' => array('doNotShow' => false, 'readonly' => false, 'includeBlankOption' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'hasParticipated' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['hasParticipated'],
            'inputType' => 'checkbox',
            'eval' => array('submitOnChange' => true, 'doNotShow' => false, 'doNotCopy' => true,),
            'sql' => "char(1) NOT NULL default ''"
        ),
        'dashboard' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['dashboard'],
            'inputType' => 'text',
            'input_field_callback' => array('tl_calendar_events_member', 'inputFieldCallbackDashboard'),
            'eval' => array('doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'refuseWithEmail' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['refuseWithEmail'],
            'inputType' => 'text',
            'input_field_callback' => array('tl_calendar_events_member', 'inputFieldCallbackRefuseWithEmail'),
            'eval' => array('doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'acceptWithEmail' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['acceptWithEmail'],
            'inputType' => 'text',
            'input_field_callback' => array('tl_calendar_events_member', 'inputFieldCallbackAcceptWithEmail'),
            'eval' => array('doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'addToWaitlist' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['acceptWithEmail'],
            'inputType' => 'text',
            'input_field_callback' => array('tl_calendar_events_member', 'inputFieldCallbackAddToWaitlist'),
            'eval' => array('doNotShow' => true, 'mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'eventName' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['eventName'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'notes' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['notes'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => array('tl_class' => 'clr', 'mandatory' => true),
            'sql' => "text NULL",
        ),
        'firstname' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['firstname'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'lastname' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['lastname'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'gender' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['gender'],
            'inputType' => 'select',
            'options' => array('male', 'female'),
            'reference' => &$GLOBALS['TL_LANG']['MSC'],
            'eval' => array('mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'),
            'sql' => "varchar(32) NOT NULL default ''"
        ),
        'dateOfBirth' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['dateOfBirth'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
            'sql' => "varchar(10) NOT NULL default ''"
        ),
        'street' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['street'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'postal' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['postal'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 32, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
            'sql' => "varchar(32) NOT NULL default ''"
        ),
        'city' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['city'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'phone' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['phone'],
            'inputType' => 'text',
            'eval' => array('mandatory' => false, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
            'sql' => "varchar(64) NOT NULL default ''"
        ),
        'emergencyPhone' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhone'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 64, 'rgxp' => 'phone', 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
            'sql' => "varchar(64) NOT NULL default ''"
        ),
        'emergencyPhoneName' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emergencyPhoneName'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 64, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
            'sql' => "varchar(64) NOT NULL default ''"
        ),
        'email' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['email'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'rgxp' => 'email', 'unique' => false, 'decodeEntities' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'contact', 'tl_class' => 'w50'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'sacMemberId' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['sacMemberId'],
            'inputType' => 'text',
            'eval' => array('doNotShow' => true, 'doNotCopy' => true, 'rgxp' => 'sacMemberId', 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'vegetarian' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['vegetarian'],
            'inputType' => 'select',
            'options' => array('false' => 'Nein', 'true' => 'Ja'),
            'eval' => array('doNotShow' => false, 'doNotCopy' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql' => "varchar(32) NOT NULL default ''"
        ),
        // Send E-mail
        'emailRecipients' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailRecipients'],
            'options' => array(), // Set via onload callback
            'inputType' => 'checkbox',
            'eval' => array('multiple' => true, 'mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => ''),
            'sql' => "blob NULL",
        ),
        'emailSubject' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSubject'],
            'inputType' => 'text',
            'eval' => array('mandatory' => true, 'maxlength' => 255, 'doNotShow' => true, 'doNotCopy' => true, 'tl_class' => ''),
            'sql' => "varchar(255) NOT NULL default ''"
        ),
        'emailText' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailText'],
            'inputType' => 'textarea',
            'eval' => array('mandatory' => true, 'doNotShow' => true, 'doNotCopy' => true, 'rows' => 6, 'style' => 'height:50px', 'tl_class' => ''),
            'sql' => "mediumtext NULL"
        ),
        'emailSendCopy' => array
        (
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events_member']['emailSendCopy'],
            'inputType' => 'checkbox',
            'eval' => array('doNotShow' => true, 'doNotCopy' => true,),
            'sql' => "char(1) NOT NULL default ''"
        ),
    )
);

