<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */


// notification_center_config.php
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['sac_event_tool'] = array
(
    // Type
    'onchange_state_of_subscription' => array
    (
        // Field in tl_nc_language
        //'email_sender_name' => array(),
        //'email_sender_address' => array(),
        'recipients'    => array('participant_email'),
        //'email_replyTo' => array(),
        //'email_recipient_cc' => array(),
        'email_subject' => array('event_name'),
        'email_text'    => array('event_name', 'participant_state_of_subscription', 'participant_name', 'event_link_detail'),
        'email_html'    => array('event_name', 'participant_state_of_subscription', 'participant_name', 'event_link_detail'),
    ),

    // Type
    'receipt_event_registration'     => array
    (
        // Field in tl_nc_language
        'email_sender_name'    => array('instructor_name'),
        'email_sender_address' => array('instructor_email'),
        'recipients'           => array('participant_email', 'instructor_email'),
        'email_replyTo'        => array('instructor_email'),
        'email_subject'        => array('event_name'),
        'email_text'           => array('event_name', 'event_type', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name', 'participant_email', 'participant_street', 'participant_postal', 'participant_city', 'participant_date_of_birth', 'participant_sac_member_id', 'participant_contao_member_id', 'participant_mobile', 'participant_emergency_phone', 'participant_emergency_phone_name', 'participant_vegetarian', 'participant_notes', 'event_link_detail', 'event_state'),
        'email_html'           => array('event_name', 'event_type', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name', 'participant_email', 'participant_street', 'participant_postal', 'participant_city', 'participant_date_of_birth', 'participant_sac_member_id', 'participant_contao_member_id', 'participant_mobile', 'participant_emergency_phone', 'participant_emergency_phone_name', 'participant_vegetarian', 'participant_notes', 'event_link_detail', 'event_state'),
    ),

    // Type
    'accept_event_participation'     => array
    (
        // Field in tl_nc_language
        'email_sender_name'    => array('instructor_name'),
        'email_sender_address' => array('instructor_email'),
        'recipients'           => array('participant_email'),
        'email_replyTo'        => array('instructor_email'),
        'email_subject'        => array('event_name'),
        'email_text'           => array('event_name', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name'),
        'email_html'           => array('event_name', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name'),
    ),
    // Type
    'sign_out_from_event'            => array
    (
        // Field in tl_nc_language
        'email_sender_name'    => array('participant_name'),
        'email_sender_address' => array('participant_email'),
        'recipients'           => array('instructor_email'),
        'email_recipient_cc'   => array('participant_email'),
        'email_replyTo'        => array('participant_email'),
        'email_subject'        => array('event_name', 'event_type', 'event_course_id', 'participant_name', 'sac_member_id', 'instructor_name', 'event_link_detail'),
        'email_text'           => array('event_name', 'event_type', 'event_course_id', 'participant_name', 'participant_email', 'sac_member_id', 'instructor_name', 'instructor_email', 'event_link_detail'),
    ),
    // Type
    'notify_on_new_event_story'      => array
    (
        // Field in tl_nc_language
        'email_recipient_cc' => array('author_email', 'instructor_email'),
        'email_subject'      => array('hostname', 'story_title', 'story_text', 'story_link_backend', 'story_link_frontend', 'event_title', 'author_name', 'author_name', 'author_email', 'author_sac_member_id', 'instructor_name', 'instructor_email'),
        'email_text'         => array('hostname', 'story_title', 'story_text', 'story_link_backend', 'story_link_frontend', 'event_title', 'author_name', 'author_name', 'author_email', 'author_sac_member_id', 'instructor_name', 'instructor_email'),
    ),

    // Type
    'activate_member_account'        => array
    (
        // Field in tl_nc_language
        'recipients' => array('email'),
        'email_text' => array('firstname', 'lastname', 'street', 'postal', 'city', 'phone', 'activation', 'activation_url', 'username', 'sac_member_id', 'email'),
    ),

);
