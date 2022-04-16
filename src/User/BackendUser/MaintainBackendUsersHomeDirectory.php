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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Psr\Log\LogLevel;

class MaintainBackendUsersHomeDirectory
{
    private ContaoFramework $framework;

    private string $projectDir;

    private string $backendUserHomeDir;

    /**
     * MaintainBackendUsersHomeDirectory constructor.
     */
    public function __construct(ContaoFramework $framework, string $projectDir, string $backendUserHomeDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;
        $this->backendUserHomeDir = $backendUserHomeDir;

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
        /** @var Files $filesAdapter */
        $filesAdapter = $this->framework->getAdapter(Files::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        new Folder($this->backendUserHomeDir.'/'.$objUser->id);
        new Folder($this->backendUserHomeDir.'/'.$objUser->id.'/avatar');
        new Folder($this->backendUserHomeDir.'/'.$objUser->id.'/documents');
        new Folder($this->backendUserHomeDir.'/'.$objUser->id.'/images');

        // Copy default avatar
        if (!is_file($this->projectDir.'/'.$this->backendUserHomeDir.'/'.$objUser->id.'/avatar/default.jpg')) {
            $filesAdapter->getInstance()->copy($this->backendUserHomeDir.'/new/avatar/default.jpg', $this->backendUserHomeDir.'/'.$objUser->id.'/avatar/default.jpg');
            $logger = System::getContainer()->get('monolog.logger.contao');
            $strText = sprintf('Created new homedirectory (and added filemount) for User with ID %s in "%s".', $objUser->id, $this->backendUserHomeDir.'/'.$objUser->id);
            $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, 'NEW BACKEND USER HOME DIRECTORY')]);
        }

        // Add filemount for the user directory
        $strFolder = $this->backendUserHomeDir.'/'.$objUser->id;
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
        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        // Scan for unused old directories
        $scanDir = scan($this->backendUserHomeDir, true);

        if (!empty($scanDir) && \is_array($scanDir)) {
            foreach ($scanDir as $userDir) {
                if ('new' === $userDir || false !== strpos($userDir, 'old__')) {
                    continue;
                }

                if (is_dir($this->projectDir.'/'.$this->backendUserHomeDir.'/'.$userDir)) {
                    if (!$userModelAdapter->findByPk($userDir)) {
                        $objFolder = new Folder($this->backendUserHomeDir.'/'.$userDir);

                        if ($objFolder) {
                            $objFolder->renameTo($this->backendUserHomeDir.'/old__'.$userDir);
                            $objFileModel = $filesModelAdapter->findByPath($this->backendUserHomeDir.'/'.$userDir);

                            if (null !== $objFileModel) {
                                $objFileModel->path = $this->backendUserHomeDir.'/old__'.$userDir;
                                $objFileModel->save();
                            }
                        }
                    }
                }
            }
        }
    }
}
