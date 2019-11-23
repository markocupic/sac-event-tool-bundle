<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\BackendUser;
use Contao\Database;
use Contao\System;
use Contao\User;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\MaintainBackendUsersHomeDirectory;

/**
 * Class PostLogin
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class PostLogin
{

    /**
     * @param User $user
     */
    public function prepareBeUserAccount(User $user)
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        if ($user instanceof BackendUser)
        {
            // Check all users
            $objUser = Database::getInstance()->execute('SELECT * FROM tl_user');

            // Create user directories if they does not exist
            while ($objUser->next())
            {
                $userModel = UserModel::findByPk($objUser->id);
                if ($userModel !== null)
                {
                    MaintainBackendUsersHomeDirectory::createBackendUsersHomeDirectory($userModel);
                }
            }

            // Scan for unused old directories and remove them
            MaintainBackendUsersHomeDirectory::removeUnusedBackendUsersHomeDirectories();
        }
    }
}
