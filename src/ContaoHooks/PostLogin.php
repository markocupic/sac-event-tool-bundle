<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;


use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Input;
use Contao\FilesModel;
use Contao\Files;
use Contao\Folder;
use Contao\Database;
use Contao\User;
use Contao\BackendUser;
use Contao\UserModel;
use Contao\System;
use Contao\Config;


/**
 * Class PostLogin
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class PostLogin
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;


    /**
     * Constructor.
     *
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
        $this->input = $this->framework->getAdapter(Input::class);
    }

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