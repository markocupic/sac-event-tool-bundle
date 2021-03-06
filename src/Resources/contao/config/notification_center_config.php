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
		'email_subject'        => array('event_name', 'participant_state_of_subscription'),
		'email_text'           => array('event_name', 'event_type', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name', 'participant_email', 'participant_street', 'participant_postal', 'participant_city', 'participant_date_of_birth', 'participant_sac_member_id', 'participant_ahv_number', 'participant_contao_member_id', 'participant_section_membership', 'participant_mobile', 'participant_emergency_phone', 'participant_emergency_phone_name', 'participant_food_habits', 'participant_notes', 'participant_state_of_subscription', 'event_id', 'event_link_detail', 'event_state'),
		'email_html'           => array('event_name', 'event_type', 'event_course_id', 'instructor_name', 'instructor_email', 'participant_name', 'participant_email', 'participant_street', 'participant_postal', 'participant_city', 'participant_date_of_birth', 'participant_sac_member_id', 'participant_ahv_number', 'participant_contao_member_id', 'participant_section_membership', 'participant_mobile', 'participant_emergency_phone', 'participant_emergency_phone_name', 'participant_food_habits', 'participant_notes', 'participant_state_of_subscription', 'event_id', 'event_link_detail', 'event_state'),
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
		'email_text'           => array('event_name', 'event_type', 'event_course_id', 'state_of_subscription', 'participant_name', 'participant_email', 'sac_member_id', 'instructor_name', 'instructor_email', 'event_link_detail'),
	),
	// Type
	'notify_on_new_event_story'      => array
	(
		// Field in tl_nc_language
		'recipients' => array('author_email', 'instructor_email', 'webmaster_email'),
		'email_recipient_cc' => array('author_email', 'instructor_email', 'webmaster_email'),
		'email_subject'      => array('hostname', 'story_title', 'story_text', 'story_link_backend', 'story_link_frontend', 'event_title', 'author_name', 'author_name', 'author_email', 'author_sac_member_id', 'instructor_name', 'instructor_email', 'webmaster_email'),
		'email_text'         => array('hostname', 'story_title', 'story_text', 'story_link_backend', 'story_link_frontend', 'event_title', 'author_name', 'author_name', 'author_email', 'author_sac_member_id', 'instructor_name', 'instructor_email', 'webmaster_email'),
	),
);
