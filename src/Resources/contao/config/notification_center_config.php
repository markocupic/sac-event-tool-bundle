<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

// notification_center_config.php
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['sac_event_tool'] = [

    // Type
    'onchange_state_of_subscription' => [
        // Field in tl_nc_language
        //'email_sender_name' => array(),
        //'email_sender_address' => array(),
        'recipients'    => ['participant_email'],
        //'email_replyTo' => array(),
        //'email_recipient_cc' => array(),
        'email_subject' => ['event_name'],
        'email_text'    => ['event_name', 'participant_state_of_subscription', 'participant_name', 'event_link_detail'],
        'email_html'    => ['event_name', 'participant_state_of_subscription', 'participant_name', 'event_link_detail'],
    ],

    // Type
    'receipt_event_registration'     => [
        // Field in tl_nc_language
        'email_sender_name'    => ['instructor_name'],
        'email_sender_address' => ['instructor_email'],
        'recipients'           => ['participant_email', 'instructor_email'],
        'email_replyTo'        => ['instructor_email'],
        'email_subject'        => ['event_name'],
        'email_text'           => ['event_name', 'event_type', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name', 'participant_email', 'participant_street', 'participant_postal', 'participant_city', 'participant_date_of_birth', 'participant_sac_member_id', 'participant_contao_member_id', 'participant_mobile', 'participant_emergency_phone', 'participant_emergency_phone_name', 'participant_food_habits', 'participant_notes', 'event_id', 'event_link_detail', 'event_state'],
        'email_html'           => ['event_name', 'event_type', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name', 'participant_email', 'participant_street', 'participant_postal', 'participant_city', 'participant_date_of_birth', 'participant_sac_member_id', 'participant_contao_member_id', 'participant_mobile', 'participant_emergency_phone', 'participant_emergency_phone_name', 'participant_food_habits', 'participant_notes', 'event_id', 'event_link_detail', 'event_state'],
    ],

    // Type
    'accept_event_participation'     => [
        // Field in tl_nc_language
        'email_sender_name'    => ['instructor_name'],
        'email_sender_address' => ['instructor_email'],
        'recipients'           => ['participant_email'],
        'email_replyTo'        => ['instructor_email'],
        'email_subject'        => ['event_name'],
        'email_text'           => ['event_name', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name'],
        'email_html'           => ['event_name', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name'],
    ],
    // Type
    'sign_out_from_event'            => [
        // Field in tl_nc_language
        'email_sender_name'    => ['participant_name'],
        'email_sender_address' => ['participant_email'],
        'recipients'           => ['instructor_email'],
        'email_recipient_cc'   => ['participant_email'],
        'email_replyTo'        => ['participant_email'],
        'email_subject'        => ['event_name', 'event_type', 'event_course_id', 'participant_name', 'sac_member_id', 'instructor_name', 'event_link_detail'],
        'email_text'           => ['event_name', 'event_type', 'event_course_id', 'state_of_subscription', 'participant_name', 'participant_email', 'sac_member_id', 'instructor_name', 'instructor_email', 'event_link_detail'],
    ],
    // Type
    'notify_on_new_event_story'      => [
        // Field in tl_nc_language
        'email_recipient_cc' => ['author_email', 'instructor_email', 'webmaster_email'],
        'email_subject'      => ['hostname', 'story_title', 'story_text', 'story_link_backend', 'story_link_frontend', 'event_title', 'author_name', 'author_name', 'author_email', 'author_sac_member_id', 'instructor_name', 'instructor_email', 'webmaster_email'],
        'email_text'         => ['hostname', 'story_title', 'story_text', 'story_link_backend', 'story_link_frontend', 'event_title', 'author_name', 'author_name', 'author_email', 'author_sac_member_id', 'instructor_name', 'instructor_email', 'webmaster_email'],
    ],

    // Type
    'activate_member_account'        => [
        // Field in tl_nc_language
        'recipients' => ['email'],
        'email_text' => ['firstname', 'lastname', 'street', 'postal', 'city', 'phone', 'activation', 'username', 'sac_member_id', 'email'],
    ],

];
