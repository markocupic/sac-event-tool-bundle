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

namespace Markocupic\SacEventToolBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Markocupic\ZipBundle\Zip\Zip;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * This class handles file backup operations for different intervals including
 * daily, weekly, and monthly. It schedules cron jobs for creating backups and
 * manages old backups by removing files older than a specified time threshold.
 */
class FileBackupCron extends AbstractController
{
    private const string DATIM_FORMAT = 'Y_m_d_H_i_s';

    private const array BACKUP_DIRS = [
        'files/fileadmin',
        'files/sektion',
    ];

    public function __construct(
        #[Autowire('%router.request_context.host%')]
        private readonly string $hostname,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Every first day of the month at 1:45 AM.
     */
    #[AsCronJob('45 1 5 * *')]
    public function monthly(): void
    {
        $timeCut = strtotime('-4 month');
        $this->removeOutdatedBackups('monthly', $timeCut);
        $this->backupFiles('monthly');
    }

    /**
     * Every Sunday at 2:45 AM.
     */
    #[AsCronJob('45 2 * * 7')]
    public function weekly(): void
    {
        $timeCut = strtotime('-1 month');
        $this->removeOutdatedBackups('weekly', $timeCut);
        $this->backupFiles('weekly');
    }

    /**
     * Every day at 3:45 AM.
     */
    #[AsCronJob('45 3 * * *')]
    public function daily(): void
    {
        $timeCut = strtotime('-1 hour');
        $this->removeOutdatedBackups('daily', $timeCut);
        $this->backupFiles('daily');
    }

    public function backupFiles(string $interval): void
    {
        $arrDirs = array_filter(
            array_map(fn ($dir) => Path::join($this->projectDir, $dir), self::BACKUP_DIRS),
            static fn ($fullPath) => file_exists($fullPath),
        );

        if (empty($arrDirs)) {
            return;
        }

        $filename = \sprintf('files___%s.zip', date(self::DATIM_FORMAT));
        $targetDir = $this->buildBackupDir($interval);

        $destPath = Path::join($targetDir, $filename);

        (new Filesystem())->mkdir($targetDir);

        $zip = (new Zip())
            ->ignoreDotFiles(false)
            ->stripSourcePath($this->projectDir)
        ;

        foreach ($arrDirs as $dir) {
            $zip->addDirRecursive($dir);
        }

        $zip->run($destPath);
    }

    private function buildBackupDir(string $interval): string
    {
        $hostname = preg_replace('/[^a-zA-Z0-9]/', '_', $this->hostname);

        return Path::join($this->projectDir, \sprintf('/../backups/%s/%s', $hostname, $interval));
    }

    private function removeOutdatedBackups(string $interval, int $timeCut): void
    {
        $arrFiles = $this->fetchFiles($this->buildBackupDir($interval));

        foreach ($arrFiles as $splFileInfo) {
            $this->removeFileByTimestampCutoff($splFileInfo, $timeCut);
        }
    }

    /**
     * @return array<\SplFileInfo>
     */
    private function fetchFiles(string $dir): array
    {
        $finder = new Finder();

        return iterator_to_array($finder->files()->in($dir));
    }

    private function removeFileByTimestampCutoff(\SplFileInfo $splFileInfo, int $timeCut): void
    {
        $chunks = explode('___', pathinfo($splFileInfo->getFilename(), PATHINFO_FILENAME));
        $dateString = $chunks[1];
        $objDate = \DateTime::createFromFormat(self::DATIM_FORMAT, $dateString);

        if ($objDate->getTimestamp() < $timeCut) {
            (new Filesystem())->remove($splFileInfo->getPathname());
        }
    }
}
