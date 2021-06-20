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

use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationCheckoutLinkController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationController;
use Markocupic\SacEventToolBundle\Dca\TlModule;

/**
 * Table tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventStoryList'] = '{title_legend},name,headline,type;{config_legend},story_eventOrganizers;{jumpTo_legend},jumpTo;{pagination_legend},story_limit,perPage;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventPreviewReader'] = '{title_legend},name,headline,type;{template_legend:hide},cal_template,customTpl;{image_legend},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

// Contao 5 ready
$GLOBALS['TL_DCA']['tl_module']['palettes']['tour_difficulty_list'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['csv_event_member_export'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][EventRegistrationController::TYPE] = '{title_legend},name,headline,type;{notification_legend},receiptEventRegistrationNotificationId;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][EventRegistrationCheckoutLinkController::TYPE] = '{title_legend},name,headline,type;{jumpTo_legend},eventRegCheckoutLinkPage,eventRegCheckoutLinkLabel;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['member_dashboard_upcoming_events'] = '{title_legend},name,headline,type;{member_dashboard_upcoming_events_legend},unregisterFromEventNotificationId;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['member_dashboard_past_events'] = '{title_legend},name,headline,type;{member_dashboard_event_type_filter_legend},eventType;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['member_dashboard_event_report_list'] = '{title_legend},name,headline,type;{events_story_legend},timeSpanForCreatingNewEventStory,eventStoryFormJumpTo;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['member_dashboard_write_event_report'] = '{title_legend},name,headline,type;{events_story_legend},eventStoryMaxImageWidth,eventStoryMaxImageHeight,timeSpanForCreatingNewEventStory,notifyOnEventStoryPublishedNotificationId,eventStoryUploadFolder;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['member_dashboard_edit_profile'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['member_dashboard_avatar'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{image_legend:hide},imgSize,imageClass;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['member_dashboard_delete_profile'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['member_dashboard_avatar_upload'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['csv_export'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['event_filter_form'] = '{title_legend},name,headline,type;{config_legend},eventFilterBoardFields;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['pilatus_export'] = '{title_legend},name,headline,type,print_export_allowedEventTypes;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['pilatus_export_2021'] = '{title_legend},name,headline,type,print_export_allowedEventTypes;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['jahresprogramm_export'] = '{title_legend},name,headline,type,print_export_allowedEventTypes;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['event_story_list'] = '{title_legend},name,headline,type;{config_legend},jumpTo,numberOfItems,skipFirst,perPage;{template_legend:hide},eventStoryListTemplate;{image_legend:hide},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['event_story_reader'] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['event_list'] = '{title_legend},name,headline,type;{config_legend},cal_calendar,eventType,cal_readerModule,eventListLimitPerRequest;{template_legend:hide},eventListPartialTpl;{image_legend:hide},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

// Fields
$GLOBALS['TL_DCA']['tl_module']['fields']['eventType'] = array(
	'exclude'   => true,
	'search'    => true,
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'],
	'eval'      => array('mandatory' => true, 'multiple' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'),
	'sql'       => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['unregisterFromEventNotificationId'] = array(
	'exclude'    => true,
	'search'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_nc_notification.title',
	'eval'       => array('mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'),
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
);

$GLOBALS['TL_DCA']['tl_module']['fields']['receiptEventRegistrationNotificationId'] = array(
	'exclude'    => true,
	'search'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_nc_notification.title',
	'eval'       => array('mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'),
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
);

$GLOBALS['TL_DCA']['tl_module']['fields']['notifyOnEventStoryPublishedNotificationId'] = array(
	'exclude'    => true,
	'search'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_nc_notification.title',
	'eval'       => array('mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'),
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryFormJumpTo'] = array(
	'exclude'    => true,
	'inputType'  => 'pageTree',
	'foreignKey' => 'tl_page.title',
	'eval'       => array('mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'),
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryJumpTo'] = array(
	'exclude'    => true,
	'inputType'  => 'pageTree',
	'foreignKey' => 'tl_page.title',
	'eval'       => array('mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'),
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryMaxImageWidth'] = array(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryMaxImageHeight'] = array(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryUploadFolder'] = array(
	'exclude'   => true,
	'inputType' => 'fileTree',
	'eval'      => array('fieldType' => 'radio', 'filesOnly' => false, 'mandatory' => true, 'tl_class' => 'clr'),
	'sql'       => "binary(16) NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['timeSpanForCreatingNewEventStory'] = array(
	'inputType' => 'select',
	'options'   => range(5, 365),
	'eval'      => array('mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr', 'rgxp' => 'natural'),
	'sql'       => "int(10) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['imageClass'] = array
(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('tl_class' => 'w50'),
	'sql'              => "varchar(512) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['story_limit'] = array
(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['story_eventOrganizers'] = array(
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

$GLOBALS['TL_DCA']['tl_module']['fields']['print_export_allowedEventTypes'] = array(
	'inputType' => 'select',
	'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'],
	'eval'      => array('mandatory' => false, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'),
	'sql'       => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventFilterBoardFields'] = array(
	'inputType'        => 'checkboxWizard',
	'options_callback' => array(TlModule::class, 'getEventFilterBoardFields'),
	'eval'             => array('mandatory' => false, 'multiple' => true, 'ooorderField' => 'orderSRC', 'tl_class' => 'clr'),
	'sql'              => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventListPartialTpl'] = array
(
	'exclude'          => true,
	'inputType'        => 'select',
	'options_callback' => array(TlModule::class, 'getEventListTemplates'),
	'eval'             => array('tl_class' => 'w50'),
	'sql'              => "varchar(64) NOT NULL default 'event_list_partial_tour'"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventListLimitPerRequest'] = array
(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'natural', 'tl_class' => 'w50'),
	'sql'       => "smallint(5) unsigned NOT NULL default 0"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventRegCheckoutLinkLabel'] = array
(
	'exclude'                 => true,
	'inputType'               => 'text',
	'eval'                    => array('mandatory' => true, 'maxlength'=>64, 'rgxp'=>'extnd', 'tl_class'=>'w50'),
	'sql'                     => "varchar(64) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['eventRegCheckoutLinkPage'] = array
(
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'foreignKey'              => 'tl_page.title',
	'eval'                    => array('mandatory' => true, 'fieldType'=>'radio'),
	'sql'                     => "int(10) unsigned NOT NULL default 0",
	'relation'                => array('type'=>'hasOne', 'load'=>'lazy')
);
