<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Config\Log;
use Psr\Log\LoggerInterface;

class MaintainBackendUsersHomeDirectory
{
    private ContaoFramework $framework;
    private string $projectDir;
    private string $sacevtUserBackendHomeDir;
    private LoggerInterface|null $contaoGeneralLogger;

    public function __construct(ContaoFramework $framework, string $projectDir, string $sacevtUserBackendHomeDir, LoggerInterface $contaoGeneralLogger = null)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;
        $this->sacevtUserBackendHomeDir = $sacevtUserBackendHomeDir;
        $this->contaoGeneralLogger = $contaoGeneralLogger;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Create backend users home directories.
     *
     * @throws \Exception
     */
    public function createBackendUsersHomeDirectory(UserModel $objUser): void
    {
        /** @var Files $filesAdapter */
        $filesAdapter = $this->framework->getAdapter(Files::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        new Folder($this->sacevtUserBackendHomeDir.'/'.$objUser->id);
        new Folder($this->sacevtUserBackendHomeDir.'/'.$objUser->id.'/avatar');
        new Folder($this->sacevtUserBackendHomeDir.'/'.$objUser->id.'/documents');
        new Folder($this->sacevtUserBackendHomeDir.'/'.$objUser->id.'/images');

        // Copy default avatar
        if (!is_file($this->projectDir.'/'.$this->sacevtUserBackendHomeDir.'/'.$objUser->id.'/avatar/default.jpg')) {
            $filesAdapter->getInstance()->copy($this->sacevtUserBackendHomeDir.'/new/avatar/default.jpg', $this->sacevtUserBackendHomeDir.'/'.$objUser->id.'/avatar/default.jpg');

            $this->contaoGeneralLogger?->info(
                sprintf(
                    'Created a new home directory (and added file mounts) for user with ID %s in "%s".',
                    $objUser->id,
                    $this->sacevtUserBackendHomeDir.'/'.$objUser->id,
                ),
                [
                    'contao' => new ContaoContext(__METHOD__, Log::CREATE_USER_HOME_DIRECTORY),
                ]
            );
        }

        // Add file mount for the user directory
        $strFolder = $this->sacevtUserBackendHomeDir.'/'.$objUser->id;
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
    public function removeUnusedBackendUserHomeDirectories(): void
    {
        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        /** @var Folder $folderAdapter */
        $folderAdapter = $this->framework->getAdapter(Folder::class);

        // Scan for no more used "old" directories and add the prefix "old_"
        $scanDir = $folderAdapter->scan($this->sacevtUserBackendHomeDir, true);

        if (!empty($scanDir)) {
            foreach ($scanDir as $userDir) {
                if ('new' === $userDir || str_contains($userDir, 'old__')) {
                    continue;
                }

                if (is_dir($this->projectDir.'/'.$this->sacevtUserBackendHomeDir.'/'.$userDir)) {
                    if (!$userModelAdapter->findByPk($userDir)) {
                        $objFolder = new Folder($this->sacevtUserBackendHomeDir.'/'.$userDir);

                        $objFolder->renameTo($this->sacevtUserBackendHomeDir.'/old__'.$userDir);
                        $objFileModel = $filesModelAdapter->findByPath($this->sacevtUserBackendHomeDir.'/'.$userDir);

                        if (null !== $objFileModel) {
                            $objFileModel->path = $this->sacevtUserBackendHomeDir.'/old__'.$userDir;
                            $objFileModel->save();
                        }
                    }
                }
            }
        }
    }
}
