<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;


PaletteManipulator::create()
    ->addLegend('sacEventTool_legend', 'global_legend')
    ->addLegend('sacWorkshopFlyer_legend', 'global_legend')
    ->addLegend('sacPrivacyPolicyLegend', 'global_legend')
    ->addField(array('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_SAC_SECTION_IDS'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_SECTION_NAME'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_DEFAULT_BACKEND_PASSWORD'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME', 'SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_TEMP_PATH'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_AVATAR_MALE'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_AVATAR_FEMALE'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_BE_USER_DIRECTORY_ROOT'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_FE_USER_DIRECTORY_ROOT'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_FE_USER_AVATAR_DIRECTORY'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_EVENT_STORIES_UPLOAD_PATH'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_EVENT_DEFAULT_PREVIEW_IMAGE_SRC'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_WORKSHOP_FLYER_SRC'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_ASSETS_DIR'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_CLOUDCONVERT_API_KEY'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_COURSE_CONFIRMATION_TEMPLATE_SRC'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_COURSE_CONFIRMATION_FILE_NAME_PATTERN'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_LOG_ADD_NEW_MEMBER'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_LOG_DISABLE_MEMBER'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_LOG_EVENT_CONFIRMATION_DOWNLOAD'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_LOG_EVENT_UNSUBSCRIPTION'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_LOG_EVENT_SUBSCRIPTION'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_EVT_LOG_COURSE_BOOKLET_DOWNLOAD'), 'sacEventTool_legend', PaletteManipulator::POSITION_APPEND)
    // Privacy policy
    ->addField(array('SAC_PRIVACY_POLICY'), 'sacPrivacyPolicyLegend', PaletteManipulator::POSITION_APPEND)
    //
    ->addField(array('SAC_WORKSHOP_FLYER_YEAR'), 'sacWorkshopFlyer_legend', PaletteManipulator::POSITION_APPEND)
    ->addField(array('SAC_WORKSHOP_FLYER_CALENDAR_ID'), 'sacWorkshopFlyer_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings');


// Member Database Bern
$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_SAC_SECTION_IDS'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_SAC_SECTION_IDS'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_SECTION_NAME'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_SECTION_NAME'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_DEFAULT_BACKEND_PASSWORD'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_DEFAULT_BACKEND_PASSWORD'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'rgxp' => 'friendly', 'decodeEntities' => true, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_TEMP_PATH'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_TEMP_PATH'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_AVATAR_MALE'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_AVATAR_MALE'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_AVATAR_FEMALE'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_AVATAR_FEMALE'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_BE_USER_DIRECTORY_ROOT'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_BE_USER_DIRECTORY_ROOT'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_FE_USER_DIRECTORY_ROOT'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_FE_USER_DIRECTORY_ROOT'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_FE_USER_AVATAR_DIRECTORY'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_FE_USER_AVATAR_DIRECTORY'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_EVENT_STORIES_UPLOAD_PATH'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_EVENT_STORIES_UPLOAD_PATH'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_EVENT_DEFAULT_PREVIEW_IMAGE_SRC'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_EVENT_DEFAULT_PREVIEW_IMAGE_SRC'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_WORKSHOP_FLYER_SRC'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_WORKSHOP_FLYER_SRC'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_ASSETS_DIR'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_ASSETS_DIR'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_CLOUDCONVERT_API_KEY'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_CLOUDCONVERT_API_KEY'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_COURSE_CONFIRMATION_TEMPLATE_SRC'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_COURSE_CONFIRMATION_TEMPLATE_SRC'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_COURSE_CONFIRMATION_FILE_NAME_PATTERN'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_COURSE_CONFIRMATION_FILE_NAME_PATTERN'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_LOG_ADD_NEW_MEMBER'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_LOG_ADD_NEW_MEMBER'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_LOG_DISABLE_MEMBER'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_LOG_DISABLE_MEMBER'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_LOG_EVENT_CONFIRMATION_DOWNLOAD'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_LOG_EVENT_CONFIRMATION_DOWNLOAD'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_LOG_EVENT_UNSUBSCRIPTION'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_LOG_EVENT_UNSUBSCRIPTION'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_LOG_EVENT_SUBSCRIPTION'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_LOG_EVENT_SUBSCRIPTION'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_LOG_COURSE_BOOKLET_DOWNLOAD'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_EVT_LOG_COURSE_BOOKLET_DOWNLOAD'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_WORKSHOP_FLYER_CALENDAR_ID'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_WORKSHOP_FLYER_CALENDAR_ID'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_WORKSHOP_FLYER_YEAR'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_WORKSHOP_FLYER_YEAR'],
    'inputType' => 'text',
    'eval'      => array('mandatory' => true, 'decodeEntities' => false, 'tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_PRIVACY_POLICY'] = array(

    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['SAC_PRIVACY_POLICY'],
    'inputType' => 'textarea',
    'eval'      => array('mandatory' => true, 'rte' => 'tinyMCE', 'helpwizard' => true),

);

