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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;

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
        $configAdapter = $this->framework->getAdapter(Config::class);

        // Check for other system vars in system/localconfig.php
        $arrConfig = [
            'SAC_EVT_ASSETS_DIR',
            'SAC_EVT_WORKSHOP_FLYER_SRC',
            'SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN',
            'SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC',
            'SAC_EVT_EVENT_DEFAULT_PREVIEW_IMAGE_SRC',
            'SAC_EVT_DEFAULT_BACKEND_PASSWORD',
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
