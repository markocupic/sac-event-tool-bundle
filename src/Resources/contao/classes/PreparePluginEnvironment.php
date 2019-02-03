<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Config;
use Contao\Folder;
use Contao\Dbafs;
use Contao\System;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PreparePluginEnvironment
{
    private $framework;


    /**
     * PreparePluginEnvironment constructor.
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
        $this->framework->initialize();
    }


    /**
     * Prepare the plugin environment
     * Create directories
     * Check vars in system/localconfig.php
     */
    public function preparePluginEnvironment()
    {
        // No more used, because all params are set in system/localconfig.php
        /**
         * // Store all params in $GLOBALS['TL_CONFIG']
         * $container = new ContainerBuilder();
         * $loader = new YamlFileLoader(
         * $container,
         * new FileLocator(__DIR__ . '/../../config')
         * );
         *
         * $loader->load('listener.yml');
         * $loader->load('parameters.yml');
         * $loader->load('services.yml');
         *
         * $parameters = $container->getParameterBag()->all();
         * foreach ($parameters as $key => $value)
         * {
         * echo $key . '<br>';
         * if (strpos($key, 'SAC_EVT_') !== false)
         * {
         * if (is_array(json_decode($value)))
         * {
         * $value = json_decode($value);
         * }
         * $GLOBALS['TL_CONFIG'][$key] = $value;
         * }
         * }
         */

        $dbafs = $this->framework->getAdapter(Dbafs::class);

        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');


        // Check for the this directories
        $arrDirectories = array(
            'SAC_EVT_FE_USER_DIRECTORY_ROOT',
            'SAC_EVT_FE_USER_AVATAR_DIRECTORY',
            'SAC_EVT_BE_USER_DIRECTORY_ROOT',
            'SAC_EVT_TEMP_PATH',
            'SAC_EVT_EVENT_STORIES_UPLOAD_PATH',
        );

        foreach ($arrDirectories as $strDir)
        {
            // Check if directory path was set in system/localconfig.php
            if (Config::get($strDir) == '')
            {
                throw new \Exception(sprintf('%s is not set in system/localconfig.php. Please log into the Contao Backend and set the missing values in the backend-settings. Error in %s on Line: %s', $strDir, __METHOD__, __LINE__));
            }

            // Create directory
            $this->framework->createInstance(Folder::class, array(Config::get($strDir)));
            $dbafs->addResource(Config::get($strDir));
        }


        // Check for other system vars in system/localconfig.php
        $arrConfig = array(
            'SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME',
            'SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME',
            'SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD',
            'SAC_EVT_SAC_SECTION_IDS',
            'SAC_EVT_SECTION_NAME',
            'SAC_EVT_ASSETS_DIR',
            'SAC_EVT_TOUREN_UND_KURS_ADMIN_EMAIL',
            'SAC_EVT_TOUREN_UND_KURS_ADMIN_NAME',
            'SAC_EVT_AVATAR_MALE',
            'SAC_EVT_AVATAR_FEMALE',
            'SAC_EVT_WORKSHOP_FLYER_SRC',
            'SAC_EVT_CLOUDCONVERT_API_KEY',
            'SAC_EVT_COURSE_CONFIRMATION_TEMPLATE_SRC',
            'SAC_EVT_COURSE_CONFIRMATION_FILE_NAME_PATTERN',
            'SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN',
            'SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC',
            'SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC',
            'SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN',
            'SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC',
            'SAC_EVT_LOG_ADD_NEW_MEMBER',
            'SAC_EVT_LOG_DISABLE_MEMBER',
            'SAC_EVT_LOG_EVENT_CONFIRMATION_DOWNLOAD',
            'SAC_EVT_LOG_EVENT_UNSUBSCRIPTION',
            'SAC_EVT_LOG_EVENT_SUBSCRIPTION',
            'SAC_EVT_LOG_EVENT_SUBSCRIPTION_ERROR',
            'SAC_EVT_LOG_COURSE_BOOKLET_DOWNLOAD',
            'SAC_EVT_EVENT_DEFAULT_PREVIEW_IMAGE_SRC',
            'SAC_EVT_DEFAULT_BACKEND_PASSWORD',
            'SAC_WORKSHOP_FLYER_YEAR',
            'SAC_WORKSHOP_FLYER_CALENDAR_ID',
            'SAC_EVT_WORKSHOP_FLYER_YEAR',
            'SAC_EVT_ACCEPT_REGISTRATION_EMAIL_TEXT',
        );

        foreach ($arrConfig as $strConfig)
        {
            // Check if directory path was set in system/localconfig.php
            if (Config::get($strConfig) == '')
            {
                throw new \Exception(sprintf('%s is not set in system/localconfig.php. Please log into the Contao Backend and set the missing values in the backend-settings. Error in %s on Line: %s', $strConfig, __METHOD__, __LINE__));
            }
        }
    }

}