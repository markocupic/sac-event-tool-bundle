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

namespace Markocupic\SacEventToolBundle\Tests\Database\SyncMember;

use Markocupic\SacEventToolBundle\Database\SyncMember\FtpCsvFileFinder;
use PHPUnit\Framework\TestCase;

class FtpCsvFileFinderTest extends TestCase
{
    private const array CREDENTIALS = [
        'hostname' => 'ftp.example.com',
        'username' => 'myuser',
        'password' => 's3cr3t',
    ];

    public function testFindCsvFilesMapsFilesBySectionId(): void
    {
        $finder = new FtpCsvFileFinder(
            self::CREDENTIALS,
            static fn (): iterable => [
                new \SplFileInfo('/remote/Adressen_00004250.csv'),
                new \SplFileInfo('/remote/Adressen_00003000.csv'),
            ],
        );

        $fileMap = $finder->findCsvFiles();

        $this->assertSame([4250, 3000], array_keys($fileMap));
        $this->assertSame('Adressen_00004250.csv', $fileMap[4250]->getBasename());
        $this->assertSame('Adressen_00003000.csv', $fileMap[3000]->getBasename());
    }

    public function testFindCsvFilesBuildsTheFtpUrlFromCredentials(): void
    {
        $capturedUrl = null;

        $finder = new FtpCsvFileFinder(
            self::CREDENTIALS,
            static function (string $ftpUrl) use (&$capturedUrl): iterable {
                $capturedUrl = $ftpUrl;

                return [];
            },
        );

        $finder->findCsvFiles();

        $this->assertSame('ftp://myuser:s3cr3t@ftp.example.com/', $capturedUrl);
    }

    public function testFindCsvFilesReturnsEmptyMapWhenNoFilesAreFound(): void
    {
        $finder = new FtpCsvFileFinder(
            self::CREDENTIALS,
            static fn (): iterable => [],
        );

        $this->assertSame([], $finder->findCsvFiles());
    }

    public function testLastFileWinsWhenSectionIdsCollide(): void
    {
        $first = new \SplFileInfo('/remote/a_4250.csv');
        $second = new \SplFileInfo('/remote/b_4250.csv');

        $finder = new FtpCsvFileFinder(
            self::CREDENTIALS,
            static fn (): iterable => [$first, $second],
        );

        $fileMap = $finder->findCsvFiles();

        $this->assertCount(1, $fileMap);
        $this->assertSame($second, $fileMap[4250]);
    }

    public function testGetSourceNameReturnsTheHostname(): void
    {
        $finder = new FtpCsvFileFinder(self::CREDENTIALS);

        $this->assertSame('ftp.example.com', $finder->getSourceName());
    }
}
