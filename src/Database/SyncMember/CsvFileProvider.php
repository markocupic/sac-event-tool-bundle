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

/**
 * Provides the member CSV dumps for the requested SAC sections.
 *
 * The remote lookup is delegated to a {@see RemoteCsvFileFinderInterface}; the
 * matching files are copied into the project's temp directory and validated.
 */
final class CsvFileProvider
{
    public const string FTP_DB_DUMP_TARGET_PATH = '%s/system/tmp/Adressen_%s.csv';

    public const string FTP_DB_DUMP_END_OF_FILE_STRING = '* * * Dateiende * * *';

    public function __construct(
        private readonly string $projectDir,
        private readonly RemoteCsvFileFinderInterface $csvFileFinder,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Copies and validates the CSV file of every requested section.
     *
     * @param array<int|string> $sectionIds The SAC section ids to fetch
     *
     * @return array<\SplFileInfo> The validated local CSV files, one per section id
     *
     * @throws \RuntimeException When no files are available or a section file is missing/incomplete
     * @throws \Throwable        Any error is logged to the SyncLogger and re-thrown
     */
    public function provide(array $sectionIds, SyncLogger $syncLogger): array
    {
        $fileMap = $this->csvFileFinder->findCsvFiles();

        if (empty($fileMap)) {
            throw new \RuntimeException(\sprintf('Could not find any CSV files at "%s". Database sync failed.', $this->csvFileFinder->getSourceName()));
        }

        $files = [];

        foreach ($sectionIds as $sectionId) {
            $targetPath = \sprintf(self::FTP_DB_DUMP_TARGET_PATH, $this->projectDir, str_pad((string) $sectionId, 8, '0', STR_PAD_LEFT));

            try {
                if (!isset($fileMap[$sectionId])) {
                    $error = \sprintf('Could not find the CSV file "%s" at "%s".', basename($targetPath), $this->csvFileFinder->getSourceName());

                    throw new \RuntimeException($error);
                }

                $this->filesystem->copy($fileMap[$sectionId]->getPathname(), $targetPath, true);

                $file = new \SplFileInfo($targetPath);

                $this->validateFile($file);

                $files[] = $file;
            } catch (\Throwable $e) {
                $error = \sprintf('Could not find the CSV file "%s" at "%s".', basename($targetPath), $this->csvFileFinder->getSourceName());
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
