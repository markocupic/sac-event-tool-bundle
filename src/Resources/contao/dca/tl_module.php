<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */


/**
 * Table tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolFrontendUserDashboard'] = '{title_legend},name,headline,type;{member_dashboard_upcoming_events_legend},unregisterFromEventNotificationId;{events_story_legend},timeSpanForCreatingNewEventStory,notifyOnEventStoryPublishedNotificationId,eventStoryJumpTo;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolEventRegistrationForm'] = '{title_legend},name,headline,type;{jumpTo_legend},jumpTo;{notification_legend},receiptEventRegistrationNotificationId;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventStoryList'] = '{title_legend},name,headline,type;{config_legend},story_eventOrganizers;{jumpTo_legend},jumpTo;{pagination_legend},story_limit,perPage;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventStoryReader'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventlist'] = $GLOBALS['TL_DCA']['tl_module']['palettes']['eventlist'];
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventTourDifficultyExplanationList'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventPreviewReader'] = '{title_legend},name,headline,type;{template_legend:hide},cal_template,customTpl;{image_legend},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolEventStoryList'] = '{title_legend},name,headline,type;{config_legend},numberOfItems,skipFirst,perPage;{template_legend:hide},eventStoryListTemplate;{image_legend:hide},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';


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

$GLOBALS['TL_DCA']['tl_module']['fields']['notifyOnEventStoryPublishedNotificationId'] = array(
    'label'      => &$GLOBALS['TL_LANG']['tl_module']['notifyOnEventStoryPublishedNotificationId'],
    'exclude'    => true,
    'search'     => true,
    'inputType'  => 'select',
    'foreignKey' => 'tl_nc_notification.title',
    'eval'       => array('mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'),
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryJumpTo'] = array(

    'label'      => &$GLOBALS['TL_LANG']['tl_module']['eventStoryJumpTo'],
    'exclude'    => true,
    'inputType'  => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval'       => array('mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'),
    'sql'        => "int(10) unsigned NOT NULL default '0'",
    'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
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

$GLOBALS['TL_DCA']['tl_module']['fields']['story_eventOrganizers'] = array(
    'label'      => &$GLOBALS['TL_LANG']['tl_module']['story_eventOrganizers'],
    'exclude'    => true,
    'search'     => true,
    'filter'     => true,
    'sorting'    => true,
    'inputType'  => 'checkbox',
    'foreignKey' => 'tl_event_organizer.title',
    'relation'   => array('type' => 'hasMany', 'load' => 'lazy'),
    'eval'       => array('multiple' => true, 'mandatory' => false, 'tl_class' => 'clr m12'),
    'sql'        => "blob NULL",
);



