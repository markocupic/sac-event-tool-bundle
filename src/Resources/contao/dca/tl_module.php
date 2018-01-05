<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */


/**
 * Table tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolFrontendUserDashboard'] = '{title_legend},name,headline,type;{member_dashboard_upcoming_events_legend},unregisterFromEventNotificationId;{events_story_legend},timeSpanForCreatingNewEventStory;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolEventRegistrationForm'] = '{title_legend},name,headline,type;{jumpTo_legend},jumpTo;{notification_legend},receiptEventRegistrationNotificationId;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventStoryList'] = '{title_legend},name,headline,type;{jumpTo_legend},jumpTo;{pagination_legend},story_limit,perPage;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventStoryReader'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventlist'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['eventlist'];

// Fields
$GLOBALS['TL_DCA']['tl_module']['fields']['unregisterFromEventNotificationId'] = array(
    'label'      => &$GLOBALS['TL_LANG']['tl_module']['unregisterFromEventNotificationId'],
    'exclude'    => true,
    'search'     => true,
    'inputType'  => 'select',
    'foreignKey' => 'tl_nc_notification.title',
    'eval'       => array('mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'),
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
);

$GLOBALS['TL_DCA']['tl_module']['fields']['receiptEventRegistrationNotificationId'] = array(
    'label'      => &$GLOBALS['TL_LANG']['tl_module']['receiptEventRegistrationNotificationId'],
    'exclude'    => true,
    'search'     => true,
    'inputType'  => 'select',
    'foreignKey' => 'tl_nc_notification.title',
    'eval'       => array('mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'),
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
);

$GLOBALS['TL_DCA']['tl_module']['fields']['timeSpanForCreatingNewEventStory'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['timeSpanForCreatingNewEventStory'],
    'inputType' => 'select',
    'options'   => range(5, 365),
    'eval'      => array('mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr', 'rgxp' => 'natural'),
    'sql'       => "int(10) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['story_limit'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['story_limit'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
    'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);
