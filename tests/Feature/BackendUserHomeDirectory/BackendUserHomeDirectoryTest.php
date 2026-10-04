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

namespace Markocupic\SacEventToolBundle\Tests\Feature\BackendUserHomeDirectory;

use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\BackendUserHomeDirectory\BackendUserHomeDirectory;
use Symfony\Component\Filesystem\Filesystem;

final class BackendUserHomeDirectoryTest extends ContaoTestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = sys_get_temp_dir().'/'.uniqid('home_dir_test_', true);

        foreach (['1', '2', '7', '12', 'new', 'old__3', 'abc'] as $directory) {
            (new Filesystem())->mkdir($this->projectDir.'/files/home/'.$directory);
        }

        // A file named like a user id is not a home directory
        (new Filesystem())->touch($this->projectDir.'/files/home/9');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);

        parent::tearDown();
    }

    public function testFindsTheHomeDirectoriesOfDeletedUsers(): void
    {
        $this->assertSame(['7', '12'], $this->createHomeDirectory(['1', '2'])->findOrphanedDirectories());
    }

    public function testMissingHomeDirectoryHasNoOrphans(): void
    {
        $homeDirectory = new BackendUserHomeDirectory($this->mockContaoFramework(), $this->createMock(Connection::class), $this->projectDir, 'files/missing');

        $this->assertSame([], $homeDirectory->findOrphanedDirectories());
    }

    /**
     * @param list<string> $userIds
     */
    private function createHomeDirectory(array $userIds): BackendUserHomeDirectory
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->with('SELECT id FROM tl_user')
            ->willReturn($userIds)
        ;

        return new BackendUserHomeDirectory($this->mockContaoFramework(), $connection, $this->projectDir, 'files/home');
    }
}
