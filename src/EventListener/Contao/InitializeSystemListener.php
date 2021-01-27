<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\Folder;

/**
 * Class InitializeSystemListener.
 */
class InitializeSystemListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * InitializeSystemListener constructor.
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Prepare the SAC Event Tool plugin environment.
     */
    public function preparePluginEnvironment(): void
    {
        // Set adapters
        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);
        $configAdapter = $this->framework->getAdapter(Config::class);

        // Check for the this directories
        $arrDirectories = [
            'SAC_EVT_FE_USER_DIRECTORY_ROOT',
            'SAC_EVT_FE_USER_AVATAR_DIRECTORY',
            'SAC_EVT_BE_USER_DIRECTORY_ROOT',
            'SAC_EVT_TEMP_PATH',
            'SAC_EVT_EVENT_STORIES_UPLOAD_PATH',
        ];

        foreach ($arrDirectories as $strDir) {
            // Check if directory path was set in system/localconfig.php
            if (empty($configAdapter->get($strDir))) {
                throw new \Exception(sprintf('%s is not set in system/localconfig.php. Please log into the Contao Backend and set the missing values in the backend-settings. Error in %s on Line: %s', $strDir, __METHOD__, __LINE__));
            }

            // Create directory
            $this->framework->createInstance(Folder::class, [$configAdapter->get($strDir)]);
            $dbafsAdapter->addResource($configAdapter->get($strDir));
        }

        // Check for other system vars in system/localconfig.php
        $arrConfig = [
            'cloudconvertApiKey',
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
            'SAC_EVT_WORKSHOP_FLYER_YEAR',
            'SAC_EVT_WORKSHOP_FLYER_CALENDAR_ID',
            'SAC_EVT_WORKSHOP_FLYER_COVER_BACKGROUND_IMAGE',
            'SAC_EVT_ACCEPT_REGISTRATION_EMAIL_TEXT',
        ];

        foreach ($arrConfig as $strConfig) {
            // Check if directory path was set in system/localconfig.php
            if (empty($configAdapter->get($strConfig))) {
                throw new \Exception(sprintf('%s is not set in system/localconfig.php. Please log into the Contao Backend and set the missing values in the backend-settings. Error in %s on Line: %s', $strConfig, __METHOD__, __LINE__));
            }
        }
    }
}
