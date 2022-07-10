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

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\CsvEventMemberExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\CsvExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventFilterFormController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventListController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationCheckoutLinkController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistrationController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventStoryListController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventStoryReaderController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\JahresprogrammExportController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardAvatarController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardAvatarUploadController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardDeleteProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardEditProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardEventReportListController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardPastEventsController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardProfileController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardUpcomingEventsController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\MemberDashboardWriteEventArticleController;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\PilatusExport2021Controller;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\TourDifficultyListController;

/*
 * Table tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventStoryList'] = '{title_legend},name,headline,type;{config_legend},story_eventOrganizers;{jumpTo_legend},jumpTo;{pagination_legend},story_limit,perPage;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes']['eventToolCalendarEventPreviewReader'] = '{title_legend},name,headline,type;{template_legend:hide},cal_template,customTpl;{image_legend},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

// Contao 5 ready
$GLOBALS['TL_DCA']['tl_module']['palettes'][CsvEventMemberExportController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][CsvExportController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][EventFilterFormController::TYPE] = '{title_legend},name,headline,type;{config_legend},eventFilterBoardFields;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][EventListController::TYPE] = '{title_legend},name,headline,type;{config_legend},cal_calendar,eventType,cal_readerModule,eventListLimitPerRequest;{template_legend:hide},eventListPartialTpl;{image_legend:hide},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][EventRegistrationCheckoutLinkController::TYPE] = '{title_legend},name,headline,type;{jumpTo_legend},eventRegCheckoutLinkPage,eventRegCheckoutLinkLabel;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][EventRegistrationController::TYPE] = '{title_legend},name,headline,type;{eventRegloginModule_legend},eventRegLoginModule;{notification_legend},receiptEventRegistrationNotificationId;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][EventStoryListController::TYPE] = '{title_legend},name,headline,type;{config_legend},jumpTo,numberOfItems,skipFirst,perPage;{template_legend:hide},eventStoryListTemplate;{image_legend:hide},imgSize;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][EventStoryReaderController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][JahresprogrammExportController::TYPE] = '{title_legend},name,headline,type,print_export_allowedEventTypes;{template_legend},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberDashboardAvatarController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{image_legend:hide},imgSize,imageClass;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberDashboardAvatarUploadController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberDashboardDeleteProfileController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberDashboardEditProfileController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberDashboardEventReportListController::TYPE] = '{title_legend},name,headline,type;{events_story_legend},timeSpanForCreatingNewEventStory,eventStoryFormJumpTo;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberDashboardPastEventsController::TYPE] = '{title_legend},name,headline,type;{member_dashboard_event_type_filter_legend},eventType;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberDashboardProfileController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberDashboardUpcomingEventsController::TYPE] = '{title_legend},name,headline,type;{member_dashboard_upcoming_events_legend},unregisterFromEventNotificationId;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][MemberDashboardWriteEventArticleController::TYPE] = '{title_legend},name,headline,type;{events_story_legend},eventStoryMaxImageWidth,eventStoryMaxImageHeight,timeSpanForCreatingNewEventStory,notifyOnEventStoryPublishedNotificationId;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][PilatusExport2021Controller::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';
$GLOBALS['TL_DCA']['tl_module']['palettes'][TourDifficultyListController::TYPE] = '{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';

// Add fields to tl_module
PaletteManipulator::create()
    ->addLegend('jumpTo_legend', 'title_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('userPortraitJumpTo', 'jumpTo_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('eventreader', 'tl_module')
;

// Fields
$GLOBALS['TL_DCA']['tl_module']['fields']['eventType'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'select',
    'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'],
    'eval' => ['mandatory' => true, 'multiple' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['unregisterFromEventNotificationId'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'select',
    'foreignKey' => 'tl_nc_notification.title',
    'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['receiptEventRegistrationNotificationId'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'select',
    'foreignKey' => 'tl_nc_notification.title',
    'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['notifyOnEventStoryPublishedNotificationId'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'select',
    'foreignKey' => 'tl_nc_notification.title',
    'eval' => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryFormJumpTo'] = [
    'exclude' => true,
    'inputType' => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval' => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
    'relation' => ['type' => 'hasOne', 'load' => 'eager'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryJumpTo'] = [
    'exclude' => true,
    'inputType' => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval' => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
    'relation' => ['type' => 'hasOne', 'load' => 'eager'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryMaxImageWidth'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventStoryMaxImageHeight'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['timeSpanForCreatingNewEventStory'] = [
    'inputType' => 'select',
    'options' => range(5, 365),
    'eval' => ['mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'clr', 'rgxp' => 'natural'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['imageClass'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(512) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['story_limit'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => "smallint(5) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['story_eventOrganizers'] = [
    'exclude' => true,
    'search' => true,
    'filter' => true,
    'sorting' => true,
    'inputType' => 'checkbox',
    'foreignKey' => 'tl_event_organizer.title',
    'relation' => ['type' => 'hasMany', 'load' => 'lazy'],
    'eval' => ['multiple' => true, 'mandatory' => false, 'tl_class' => 'clr m12'],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['print_export_allowedEventTypes'] = [
    'inputType' => 'select',
    'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'],
    'eval' => ['mandatory' => false, 'multiple' => true, 'chosen' => true, 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventFilterBoardFields'] = [
    'inputType' => 'checkboxWizard',
    'eval' => ['mandatory' => false, 'multiple' => true, 'ooorderField' => 'orderSRC', 'tl_class' => 'clr'],
    'sql' => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventListPartialTpl'] = [
    'exclude' => true,
    'inputType' => 'select',
    'eval' => ['tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default 'event_list_partial_tour'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventListLimitPerRequest'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'natural', 'tl_class' => 'w50'],
    'sql' => 'smallint(5) unsigned NOT NULL default 0',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventRegLoginModule'] = [
    'exclude' => true,
    'inputType' => 'select',
    'foreignKey' => 'tl_module.name',
    'eval' => ['mandatory' => true],
    'sql' => 'int(10) unsigned NOT NULL default 0',
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventRegCheckoutLinkLabel'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'maxlength' => 64, 'rgxp' => 'extnd', 'tl_class' => 'w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['eventRegCheckoutLinkPage'] = [
    'exclude' => true,
    'inputType' => 'pageTree',
    'foreignKey' => 'tl_page.title',
    'eval' => ['mandatory' => true, 'fieldType' => 'radio'],
    'sql' => 'int(10) unsigned NOT NULL default 0',
    'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
];
