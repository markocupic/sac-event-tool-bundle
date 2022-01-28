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

namespace Markocupic\SacEventToolBundle\User\BackendUser;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Psr\Log\LogLevel;

/**
 * Class MaintainBackendUsersHomeDirectory.
 */
class MaintainBackendUsersHomeDirectory
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * MaintainBackendUsersHomeDirectory constructor.
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Create backend users home directories.
     *
     * @param \Markocupic\SacEventToolBundle\UserModel $objUser
     */
    public function createBackendUsersHomeDirectory(UserModel $objUser): void
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var Files $filesAdapter */
        $filesAdapter = $this->framework->getAdapter(Files::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        new Folder($configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$objUser->id);
        new Folder($configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$objUser->id.'/avatar');
        new Folder($configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$objUser->id.'/documents');
        new Folder($configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$objUser->id.'/images');

        // Copy default avatar
        if (!is_file($this->projectDir.'/'.$configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$objUser->id.'/avatar/default.jpg')) {
            $filesAdapter->getInstance()->copy($configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/new/avatar/default.jpg', $configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$objUser->id.'/avatar/default.jpg');
            $logger = System::getContainer()->get('monolog.logger.contao');
            $strText = sprintf('Created new homedirectory (and added filemount) for User with ID %s in "%s".', $objUser->id, $configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$objUser->id);
            $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, 'NEW BACKEND USER HOME DIRECTORY')]);
        }

        // Add filemount for the user directory
        $strFolder = $configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$objUser->id;
        $objFile = $filesModelAdapter->findByPath($strFolder);
        $arrFileMounts = $stringUtilAdapter->deserialize($objUser->filemounts, true);
        $arrFileMounts[] = $objFile->uuid;

        $objUser->filemounts = serialize(array_unique($arrFileMounts));
        $objUser->inherit = 'extend';
        $objUser->save();
    }

    /**
     * Remove no more user backend user home directories.
     */
    public function removeUnusedBackendUsersHomeDirectories(): void
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        // Scan for unused old directories
        $scanDir = scan($configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT'), true);

        if (!empty($scanDir) && \is_array($scanDir)) {
            foreach ($scanDir as $userDir) {
                if ('new' === $userDir || false !== strpos($userDir, 'old__')) {
                    continue;
                }

                if (is_dir($this->projectDir.'/'.$configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$userDir)) {
                    if (!$userModelAdapter->findByPk($userDir)) {
                        $objFolder = new Folder($configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$userDir);

                        if ($objFolder) {
                            $objFolder->renameTo($configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/old__'.$userDir);
                            $objFileModel = $filesModelAdapter->findByPath($configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/'.$userDir);

                            if (null !== $objFileModel) {
                                $objFileModel->path = $configAdapter->get('SAC_EVT_BE_USER_DIRECTORY_ROOT').'/old__'.$userDir;
                                $objFileModel->save();
                            }
                        }
                    }
                }
            }
        }
    }
}
