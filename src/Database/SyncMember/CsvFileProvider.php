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

namespace Markocupic\SacEventToolBundle\Database\SyncMember;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

final class CsvFileProvider
{
    public const string FTP_DB_DUMP_TARGET_PATH = '%s/system/tmp/Adressen_%s.csv';

    private const string FTP_DB_DUMP_END_OF_FILE_STRING = '* * * Dateiende * * *';

    private string $ftpHostname;

    private string $ftpUsername;

    private string $ftpPassword;

    public function __construct(
        private readonly string $projectDir,
        #[\SensitiveParameter]
        array $sacevtMemberSyncCredentials,
    ) {
        $this->ftpHostname = (string) $sacevtMemberSyncCredentials['hostname'];
        $this->ftpUsername = (string) $sacevtMemberSyncCredentials['username'];
        $this->ftpPassword = (string) $sacevtMemberSyncCredentials['password'];
    }

    /**
     * @return array<\SplFileInfo>
     *
     * @throws \Throwable
     */
    public function provide(array $sectionIds, SyncLogger $syncLogger): array
    {
        $fs = new Filesystem();
        $ftpUrl = \sprintf('ftp://%s:%s@%s/', $this->ftpUsername, $this->ftpPassword, $this->ftpHostname);

        $fileMap = [];

        foreach (new Finder()->files()->in($ftpUrl)->name('*.csv') as $file) {
            $sectionId = (int) filter_var($file->getBasename(), FILTER_SANITIZE_NUMBER_INT);
            $fileMap[$sectionId] = $file;
        }

        if (empty($fileMap)) {
            throw new \RuntimeException(\sprintf('Could not find any CSV files at "%s". Database sync failed.', $this->ftpHostname));
        }

        $files = [];

        foreach ($sectionIds as $sectionId) {
            $targetPath = \sprintf(self::FTP_DB_DUMP_TARGET_PATH, $this->projectDir, str_pad((string) $sectionId, 8, '0', STR_PAD_LEFT));

            try {
                if (!isset($fileMap[$sectionId])) {
                    $error = \sprintf('Could not find the CSV file "%s" at "%s".', basename($targetPath), $this->ftpHostname);

                    throw new \RuntimeException($error);
                }

                $fs->copy($fileMap[$sectionId]->getPathname(), $targetPath, true);

                $file = new \SplFileInfo($targetPath);

                $this->validateFile($file);

                $files[] = $file;
            } catch (\Throwable $e) {
                $error = \sprintf('Could not find the CSV file "%s" at "%s".', basename($targetPath), $this->ftpHostname);
                $syncLogger->addError($error);

                throw $e;
            }
        }

        return $files;
    }

    private function validateFile(\SplFileInfo $file): void
    {
        if (!$file->isReadable()) {
            throw new \RuntimeException(\sprintf('Could not read the CSV file "%s".', Path::makeRelative($file->getRealPath(), $this->projectDir)));
        }

        if (!str_contains(file_get_contents($file->getRealPath()), self::FTP_DB_DUMP_END_OF_FILE_STRING) || $file->getSize() < 1000) {
            throw new \RuntimeException(\sprintf('The CSV file "%s" seems to be empty or incomplete.', Path::makeRelative($file->getRealPath(), $this->projectDir)));
        }
    }
}
