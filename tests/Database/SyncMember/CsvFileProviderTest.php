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

use Markocupic\SacEventToolBundle\Database\SyncMember\CsvFileProvider;
use Markocupic\SacEventToolBundle\Database\SyncMember\RemoteCsvFileFinderInterface;
use Markocupic\SacEventToolBundle\Database\SyncMember\SyncLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class CsvFileProviderTest extends TestCase
{
    private Filesystem $fs;

    private string $tmpDir;

    private string $sourceDir;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->tmpDir = sys_get_temp_dir().'/csv_file_provider_'.uniqid('', true);
        $this->sourceDir = $this->tmpDir.'/source';
        $this->projectDir = $this->tmpDir.'/project';
        $this->fs->mkdir([$this->sourceDir, $this->projectDir]);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tmpDir);
    }

    public function testProvideCopiesAndReturnsRequestedFiles(): void
    {
        $finder = $this->createFinder([
            4250 => $this->createValidCsv(4250),
            3000 => $this->createValidCsv(3000),
        ]);

        $provider = new CsvFileProvider($this->projectDir, $finder);
        $logger = new SyncLogger();

        $files = $provider->provide([4250], $logger);

        $this->assertCount(1, $files);
        $this->assertSame([], $logger->getErrors());

        $expectedTarget = \sprintf('%s/system/tmp/Adressen_00004250.csv', $this->projectDir);
        $this->assertFileExists($expectedTarget);
        $this->assertSame($expectedTarget, $files[0]->getPathname());
    }

    public function testProvideThrowsWhenNoFilesAreAvailable(): void
    {
        $finder = $this->createFinder([]);

        $provider = new CsvFileProvider($this->projectDir, $finder);
        $logger = new SyncLogger();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not find any CSV files at "ftp.example.com"');

        $provider->provide([4250], $logger);
    }

    public function testProvideLogsAndThrowsWhenSectionFileIsMissing(): void
    {
        $finder = $this->createFinder([4250 => $this->createValidCsv(4250)]);

        $provider = new CsvFileProvider($this->projectDir, $finder);
        $logger = new SyncLogger();

        try {
            $provider->provide([9999], $logger);
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Adressen_00009999.csv', $e->getMessage());
        }

        $this->assertNotEmpty($logger->getErrors());
        $this->assertStringContainsString('Adressen_00009999.csv', $logger->getErrors()[0]);
    }

    public function testProvideLogsAndThrowsWhenFileIsIncomplete(): void
    {
        $finder = $this->createFinder([4250 => $this->createIncompleteCsv(4250)]);

        $provider = new CsvFileProvider($this->projectDir, $finder);
        $logger = new SyncLogger();

        try {
            $provider->provide([4250], $logger);
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('empty or incomplete', $e->getMessage());
        }

        $this->assertNotEmpty($logger->getErrors());
    }

    /**
     * @param array<int, \SplFileInfo> $fileMap
     */
    private function createFinder(array $fileMap): RemoteCsvFileFinderInterface
    {
        $finder = $this->createMock(RemoteCsvFileFinderInterface::class);
        $finder
            ->method('findCsvFiles')
            ->willReturn($fileMap)
        ;

        $finder
            ->method('getSourceName')
            ->willReturn('ftp.example.com')
        ;

        return $finder;
    }

    private function createValidCsv(int $sectionId): \SplFileInfo
    {
        $path = \sprintf('%s/%08d.csv', $this->sourceDir, $sectionId);

        // > 1000 bytes and containing the required end-of-file marker.
        $content = str_repeat("firstname;lastname;email;section\n", 100).CsvFileProvider::FTP_DB_DUMP_END_OF_FILE_STRING."\n";
        file_put_contents($path, $content);

        return new \SplFileInfo($path);
    }

    private function createIncompleteCsv(int $sectionId): \SplFileInfo
    {
        $path = \sprintf('%s/%08d.csv', $this->sourceDir, $sectionId);

        // Missing the end-of-file marker and too small.
        file_put_contents($path, "firstname;lastname\n");

        return new \SplFileInfo($path);
    }
}
