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
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\System;
use Contao\User;
use Contao\UserModel;


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

            // Create user directories
            while ($objUser->next())
            {
                new Folder(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $objUser->id);
                new Folder(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $objUser->id . '/avatar');
                new Folder(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $objUser->id . '/documents');
                new Folder(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $objUser->id . '/images');

                // Copy default avatar
                if (!is_file($rootDir . '/' . Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $objUser->id . '/avatar/default.jpg'))
                {
                    Files::getInstance()->copy(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/new/avatar/default.jpg', Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $objUser->id . '/avatar/default.jpg');
                }

                // Add filemount for the user directory
                $strFolder = Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $objUser->id;
                $objFile = FilesModel::findByPath($strFolder);
                $arrFileMounts = unserialize($objUser->filemounts);
                $arrFileMounts[] = $objFile->uuid;
                $userModel = UserModel::findByPk($objUser->id);
                if ($userModel !== null)
                {
                    $userModel->filemounts = serialize(array_unique($arrFileMounts));
                    $userModel->inherit = 'extend';
                    $userModel->save();
                }
            }

            // Scan for unused old directories
            $scanDir = scan(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT'), true);
            if (!empty($scanDir) && is_array($scanDir))
            {
                foreach ($scanDir as $userDir)
                {
                    if ($userDir === 'new' || strpos($userDir, 'old__') !== false)
                    {
                        continue;
                    }

                    if (is_dir($rootDir . '/' . Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $userDir))
                    {
                        if (!UserModel::findByPk($userDir))
                        {
                            $objFolder = new Folder(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $userDir);
                            if ($objFolder)
                            {
                                $objFolder->renameTo(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/old__' . $userDir);
                                $objFileModel = FilesModel::findByPath(Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/' . $userDir);
                                if ($objFileModel !== null)
                                {
                                    $objFileModel->path = Config::get('SAC_EVT_BE_USER_DIRECTORY_ROOT') . '/old__' . $userDir;
                                    $objFileModel->save();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}