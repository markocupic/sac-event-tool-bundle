<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
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
                new Folder(SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $objUser->id);
                new Folder(SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $objUser->id . '/avatar');
                new Folder(SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $objUser->id . '/documents');
                new Folder(SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $objUser->id . '/images');

                // Copy default avatar
                if (!is_file($rootDir . '/' . SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $objUser->id . '/avatar/default.jpg'))
                {
                    Files::getInstance()->copy(SAC_EVT_BE_USER_DIRECTORY_ROOT . '/new/avatar/default.jpg', SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $objUser->id . '/avatar/default.jpg');
                }

                // Add filemount for the user directory
                $strFolder = SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $objUser->id;
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
            $scanDir = scan(SAC_EVT_BE_USER_DIRECTORY_ROOT, true);
            if (!empty($scanDir) && is_array($scanDir))
            {
                foreach ($scanDir as $userDir)
                {
                    if ($userDir === 'new' || strpos($userDir, 'old__') !== false)
                    {
                        continue;
                    }

                    if (is_dir($rootDir . '/' . SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $userDir))
                    {
                        if (!UserModel::findByPk($userDir))
                        {
                            $objFolder = new Folder(SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $userDir);
                            if ($objFolder)
                            {
                                $objFolder->renameTo(SAC_EVT_BE_USER_DIRECTORY_ROOT . '/old__' . $userDir);
                                $objFileModel = FilesModel::findByPath(SAC_EVT_BE_USER_DIRECTORY_ROOT . '/' . $userDir);
                                if ($objFileModel !== null)
                                {
                                    $objFileModel->path = SAC_EVT_BE_USER_DIRECTORY_ROOT . '/old__' . $userDir;
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