<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Feature\BackendUserHomeDirectory;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FilesModel;
use Contao\Folder;
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\Log;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * Every backend user gets a home directory <home_dir>/<user id> with the sub folders
 * avatar, documents and images. The home directory is added to the file mounts of the user.
 *
 * Home directories of deleted users are renamed to <home_dir>/old__<user id>.
 *
 * See docs/features/backend-user-home-directory.md
 */
class BackendUserHomeDirectory
{
    private const array SUB_DIRECTORIES = ['avatar', 'documents', 'images'];

    // Template folder with the default avatar (new/avatar/default.jpg)
    private const string TEMPLATE_DIRECTORY = 'new';

    private const string ARCHIVE_PREFIX = 'old__';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly string $projectDir,
        private readonly string $sacevtUserBackendHomeDir,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * Creates the home directory of a user (if it does not exist) and adds it to his file mounts.
     */
    public function create(UserModel $user): void
    {
        $this->framework->initialize();

        $userDirectory = $this->sacevtUserBackendHomeDir.'/'.$user->id;

        // Contao\Folder also adds the folder to the file manager (DBAFS)
        $folder = new Folder($userDirectory);

        foreach (self::SUB_DIRECTORIES as $subDirectory) {
            new Folder($userDirectory.'/'.$subDirectory);
        }

        $this->copyDefaultAvatar($userDirectory, (int) $user->id);
        $this->addFileMount($user, $folder->getModel());
    }

    /**
     * @return int number of users
     */
    public function createForAllUsers(): int
    {
        $this->framework->initialize();

        $users = $this->framework->getAdapter(UserModel::class)->findAll();

        if (null === $users) {
            return 0;
        }

        foreach ($users as $user) {
            $this->create($user);
        }

        return \count($users);
    }

    /**
     * Home directories (<home_dir>/<user id>) of users that no longer exist.
     *
     * @return list<string> directory names (user ids)
     */
    public function findOrphanedDirectories(): array
    {
        $homeDirectory = Path::join($this->projectDir, $this->sacevtUserBackendHomeDir);

        if (!is_dir($homeDirectory)) {
            return [];
        }

        $userIds = array_flip(array_map(intval(...), $this->connection->fetchFirstColumn('SELECT id FROM tl_user')));

        // Only directories named by a user id: "new" and the archived "old__<id>" directories are ignored
        $directories = (new Finder())
            ->directories()
            ->depth(0)
            ->name('/^\d+$/')
            ->in($homeDirectory)
            ->sortByName(true)
        ;

        $orphaned = [];

        foreach ($directories as $directory) {
            if (!isset($userIds[(int) $directory->getFilename()])) {
                $orphaned[] = $directory->getFilename();
            }
        }

        return $orphaned;
    }

    /**
     * Renames the home directories of deleted users to old__<user id>.
     *
     * @return int number of archived directories
     */
    public function archiveOrphanedDirectories(): int
    {
        $this->framework->initialize();

        $orphaned = $this->findOrphanedDirectories();

        foreach ($orphaned as $directory) {
            $source = $this->sacevtUserBackendHomeDir.'/'.$directory;
            $target = $this->sacevtUserBackendHomeDir.'/'.self::ARCHIVE_PREFIX.$directory;

            // Contao\Folder also moves the record in the file manager (DBAFS)
            (new Folder($source))->renameTo($target);

            $this->log(\sprintf('Archived the home directory "%s" of a deleted backend user to "%s".', $source, $target));
        }

        return \count($orphaned);
    }

    private function copyDefaultAvatar(string $userDirectory, int $userId): void
    {
        $target = Path::join($this->projectDir, $userDirectory, 'avatar/default.jpg');
        $source = Path::join($this->projectDir, $this->sacevtUserBackendHomeDir, self::TEMPLATE_DIRECTORY, 'avatar/default.jpg');

        if (is_file($target) || !is_file($source)) {
            return;
        }

        (new Filesystem())->copy($source, $target);

        $this->log(\sprintf('Created a new home directory (and added file mounts) for user with ID %d in "%s".', $userId, $userDirectory));
    }

    /**
     * Saves the user only if something changed.
     */
    private function addFileMount(UserModel $user, FilesModel|null $folderModel): void
    {
        // The folder is not in the file manager, e.g. because the home directory is outside of the upload path
        if (null === $folderModel) {
            $this->contaoGeneralLogger?->warning(\sprintf('Could not add the home directory of the user with ID %d as file mount: the folder is not in the file manager.', $user->id));

            return;
        }

        $fileMounts = StringUtil::deserialize($user->filemounts, true);

        if (\in_array($folderModel->uuid, $fileMounts, true) && 'extend' === $user->inherit) {
            return;
        }

        $fileMounts[] = $folderModel->uuid;

        $user->filemounts = serialize(array_values(array_unique($fileMounts)));
        $user->inherit = 'extend';
        $user->save();
    }

    private function log(string $message): void
    {
        $this->contaoGeneralLogger?->info($message, ['contao' => new ContaoContext(__METHOD__, Log::CREATE_USER_HOME_DIRECTORY)]);
    }
}
