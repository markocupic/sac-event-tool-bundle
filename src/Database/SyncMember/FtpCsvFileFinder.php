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

use Symfony\Component\Finder\Finder;

/**
 * Retrieves the member CSV dumps from the remote FTP server.
 */
final class FtpCsvFileFinder implements RemoteCsvFileFinderInterface
{
    private readonly string $ftpHostname;

    private readonly string $ftpUsername;

    private readonly string $ftpPassword;

    /**
     * @var \Closure(string): iterable<\SplFileInfo>
     */
    private readonly \Closure $fileLocator;

    /**
     * @param array{hostname: string, username: string, password: string} $sacevtMemberSyncCredentials
     * @param (\Closure(string): iterable<\SplFileInfo>)|null             $fileLocator                 Resolves the CSV files for a given FTP URL. Defaults to a Symfony Finder based FTP scan; inject a stub in tests.
     */
    public function __construct(#[\SensitiveParameter] array $sacevtMemberSyncCredentials, \Closure|null $fileLocator = null)
    {
        $this->ftpHostname = (string) $sacevtMemberSyncCredentials['hostname'];
        $this->ftpUsername = (string) $sacevtMemberSyncCredentials['username'];
        $this->ftpPassword = (string) $sacevtMemberSyncCredentials['password'];

        $this->fileLocator = $fileLocator ?? static fn (string $ftpUrl): iterable => (new Finder())->files()->in($ftpUrl)->name('*.csv');
    }

    public function findCsvFiles(): array
    {
        $fileMap = [];

        foreach (($this->fileLocator)($this->getFtpUrl()) as $file) {
            $sectionId = (int) filter_var($file->getBasename(), FILTER_SANITIZE_NUMBER_INT);
            $fileMap[$sectionId] = $file;
        }

        return $fileMap;
    }

    public function getSourceName(): string
    {
        return $this->ftpHostname;
    }

    private function getFtpUrl(): string
    {
        return \sprintf('ftp://%s:%s@%s/', $this->ftpUsername, $this->ftpPassword, $this->ftpHostname);
    }
}
