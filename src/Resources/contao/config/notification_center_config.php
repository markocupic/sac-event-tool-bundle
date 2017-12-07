<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */


// notification_center_config.php
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['sac_event_tool'] = array
(
    // Type
    'state_of_subscription_state_changed' => array
    (
        // Field in tl_nc_language
        'email_sender_name' => array(),
        'email_sender_address' => array(),
        'recipients' => array('participant_email'),
        'email_replyTo' => array(),
        'email_recipient_cc' => array(),
        'email_subject' => array('event_name'),
        'email_text' => array('event_name', 'participant_state_of_subscription', 'participant_name', 'event_link_detail'),
        'email_html' => array('event_name', 'participant_state_of_subscription', 'participant_name', 'event_link_detail'),
    ),

    // Type
	'receipt_event_registration' => array
	(
		// Field in tl_nc_language
		'email_sender_name' => array('instructor_name'),
		'email_sender_address' => array('instructor_email'),
		'recipients' => array('participant_email', 'instructor_email'),
		'email_replyTo' => array('instructor_email'),
        'email_recipient_cc' => array('registration_goes_to'),
        'email_subject' => array('event_name'),
		'email_text' => array('event_name', 'instructor_name', 'participant_name', 'participant_email', 'participant_street', 'participant_postal', 'participant_city', 'participant_date_of_birth', 'participant_sac_member_id', 'participant_contao_member_id', 'participant_phone', 'participant_emergency_phone', 'participant_emergency_phone_name', 'participant_vegetarian', 'participant_notes', 'event_link_detail'),
		'email_html' => array('event_name', 'instructor_name', 'participant_name', 'participant_email', 'participant_street', 'participant_postal', 'participant_city', 'participant_date_of_birth', 'participant_sac_member_id', 'participant_contao_member_id', 'participant_phone', 'participant_emergency_phone', 'participant_emergency_phone_name', 'participant_vegetarian', 'participant_notes', 'event_link_detail'),
	),

	// Type
	'accept_event_participation' => array
	(
		// Field in tl_nc_language
		'email_sender_name' => array('instructor_name'),
		'email_sender_address' => array('instructor_email'),
		'recipients' => array('participant_email'),
		'email_replyTo' => array('instructor_email'),
		'email_recipient_cc' => array('registration_goes_to'),
		'email_subject' => array('event_name'),
		'email_text' => array('event_name', 'instructor_name', 'participant_name'),
		'email_html' => array('event_name', 'instructor_name', 'participant_name')
	),
	// Type
	'sign_out_from_event' => array
	(
		// Field in tl_nc_language
		'email_sender_name' => array('participant_name'),
		'email_sender_address' => array('participant_email'),
		'recipients' => array('instructor_email'),
		'email_recipient_cc' => array('participant_email'),
		'email_replyTo' => array('participant_email'),
		'email_subject' => array('event_name', 'participant_name', 'sac_member_id', 'instructor_name', 'event_link_detail'),
		'email_text' => array('event_name', 'participant_name', 'participant_email', 'sac_member_id', 'instructor_name', 'instructor_email', 'event_link_detail'),
	)
);
