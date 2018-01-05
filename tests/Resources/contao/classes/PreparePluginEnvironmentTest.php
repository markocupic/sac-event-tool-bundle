<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\TestCase\ContaoTestCase;


/**
 * Class PreparePluginEnvironmentTest
 * @package Markocupic\SacEventToolBundle
 */
class PreparePluginEnvironmentTest extends ContaoTestCase
{

    /**
     * Check if parameter file exists and parameters are laoded
     */
    public function testEventToolParametersFileIsLoaded()
    {
        $rootDir = __DIR__ . '/../../../../../../../';

        // Check for parameter file
        $this->assertTrue(\is_file($rootDir . '/sac_event_tool_parameters.php'));
        require_once $rootDir . '/sac_event_tool_parameters.php';

        // FTP Credentials SAC Switzerland link: Daniel Fernandez Daniel.Fernandez@sac-cas.ch
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_SAC_SECTION_IDS']));


        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_SECTION_NAME']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_TEMP_PATH']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_AVATAR_MALE']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_AVATAR_FEMALE']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_BE_USER_DIRECTORY_ROOT']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_FE_USER_DIRECTORY_ROOT']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_FE_USER_AVATAR_DIRECTORY']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_EVENT_STORIES_UPLOAD_PATH']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_EVENT_DEFALUT_PREVIEW_IMAGE_SRC']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_WORKSHOP_FLYER_SRC']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_CLOUDCONVERT_API_KEY']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_COURSE_CONFIRMATION_TEMPLATE_SRC']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_COURSE_CONFIRMATION_FILE_NAME_PATTERN']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_LOG_ADD_NEW_MEMBER']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_LOG_DISABLE_MEMBER']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_LOG_EVENT_CONFIRMATION_DOWNLOAD']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_LOG_EVENT_UNSUBSCRIPTION']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_LOG_EVENT_SUBSCRIPTION']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR']));
        $this->assertTrue(!empty($GLOBALS['TL_CONFIG']['SAC_EVT_LOG_COURSE_BOOKLET_DOWNLOAD']));
    }
}
