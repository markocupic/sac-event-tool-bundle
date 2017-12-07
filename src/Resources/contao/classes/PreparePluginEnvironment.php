<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\Folder;
use Contao\Dbafs;

class PreparePluginEnvironment
{
    /**
     * Prepare the plugin environment
     * Create directories
     */
    public static function createPluginDirectories()
    {
        // Create FE-USER-HOME-DIR
        new Folder(SAC_EVT_FE_USER_DIRECTORY_ROOT);
        Dbafs::addResource(SAC_EVT_FE_USER_DIRECTORY_ROOT);

        // Create FE-USER-AVATAR-DIR
        new Folder(SAC_EVT_FE_USER_AVATAR_DIRECTORY);
        Dbafs::addResource(SAC_EVT_FE_USER_AVATAR_DIRECTORY);

        // Create BE-USER-HOME-DIR
        new Folder(SAC_EVT_BE_USER_DIRECTORY_ROOT);
        Dbafs::addResource(SAC_EVT_BE_USER_DIRECTORY_ROOT);

        // Create tmp folder
        new Folder(SAC_EVT_TEMP_PATH);
        Dbafs::addResource(SAC_EVT_TEMP_PATH);
    }
}