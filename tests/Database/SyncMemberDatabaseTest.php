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

namespace Markocupic\SacEventToolBundle\Tests\Database;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Database\SyncMember\ContaoMemberWriter;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvFileProvider;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvMemberDto;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvMemberReader;
use Markocupic\SacEventToolBundle\Database\SyncMember\RemoteCsvFileFinderInterface;
use Markocupic\SacEventToolBundle\Database\SyncMember\SyncLogger;
use Markocupic\SacEventToolBundle\Database\SyncMember\TempMemberTableManager;
use Markocupic\SacEventToolBundle\Database\SyncMemberDatabase;
use Markocupic\SacEventToolBundle\DataContainer\Util;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;

class SyncMemberDatabaseTest extends TestCase
{
    public function testGetSectionIdsReadsFromDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->with('SELECT sectionId FROM tl_sac_section')
            ->willReturn(['4250', '4252'])
        ;

        $sync = $this->createSync($connection);

        $this->assertSame(['4250', '4252'], $this->invoke($sync, 'getSectionIds'));
    }

    public function testFormatSectionIdKeepsOnlyKnownSectionsAndPreservesKeys(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllKeyValue')
            ->willReturn(['4250' => 'SAC Pilatus', '4252' => 'SAC Napf', '9999' => 'Other'])
        ;

        $sync = $this->createSync($connection);

        // '4250' (key 0) and '9999' (key 2) are kept; '4252' (key 1) is dropped -> the gap is intentional.
        $this->assertSame([0 => '4250', 2 => '9999'], $this->invoke($sync, 'formatSectionId', [['4250', '9999']]));
    }

    public function testUpsertTempMemberInsertsNewRecord(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllKeyValue')
            ->willReturn(['4250' => 'SAC Pilatus'])
        ;

        // Not yet present in the temp table.
        $connection
            ->method('fetchAssociative')
            ->willReturn(false)
        ;

        $captured = null;
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                TempMemberTableManager::TABLE_NAME,
                $this->callback(
                    static function (array $data) use (&$captured): bool {
                        $captured = $data;

                        return true;
                    },
                ),
            )
        ;

        $sync = $this->createSync($connection);

        $this->invoke($sync, 'upsertTempMember', [$this->dto('123', '4250')]);

        $this->assertSame(123, $captured['sacMemberId']);
        $this->assertSame(serialize(['4250']), $captured['sectionId']);
    }

    public function testUpsertTempMemberAppendsSectionToExistingRecord(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllKeyValue')
            ->willReturn(['4250' => 'SAC Pilatus', '4252' => 'SAC Napf'])
        ;

        // Already present, belonging to another section.
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 5, 'sectionId' => serialize(['4252'])])
        ;

        $capturedSet = null;
        $capturedCriteria = null;
        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                TempMemberTableManager::TABLE_NAME,
                $this->callback(
                    static function (array $set) use (&$capturedSet): bool {
                        $capturedSet = $set;

                        return true;
                    },
                ),
                $this->callback(
                    static function (array $criteria) use (&$capturedCriteria): bool {
                        $capturedCriteria = $criteria;

                        return true;
                    },
                ),
                $this->anything(),
            )
        ;

        $sync = $this->createSync($connection);

        $this->invoke($sync, 'upsertTempMember', [$this->dto('123', '4250')]);

        $this->assertSame(['id' => 5], $capturedCriteria);
        $this->assertSame(['4250', '4252'], array_values(unserialize($capturedSet['sectionId'])));
    }

    public function testUpsertTempMemberSkipsRecordWithoutMemberId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchAssociative')
        ;

        $connection
            ->expects($this->never())
            ->method('insert')
        ;

        $connection
            ->expects($this->never())
            ->method('update')
        ;

        $sync = $this->createSync($connection);

        $this->invoke($sync, 'upsertTempMember', [$this->dto('0', '4250')]);
    }

    public function testRunCapturesExceptionsAndReleasesTheLock(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willThrowException(new \RuntimeException('db down'))
        ;

        $lock = $this->createMock(SharedLockInterface::class);
        $lock
            ->expects($this->once())
            ->method('acquire')
            ->with(true)
        ;

        $lock
            ->expects($this->once())
            ->method('release')
        ;

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory
            ->method('createLock')
            ->willReturn($lock)
        ;

        $sync = $this->createSync($connection, $lockFactory);
        $logger = new SyncLogger();

        $sync->run($logger);

        $this->assertNotNull($logger->getException());
        $this->assertSame('db down', $logger->getException()->getMessage());
        $this->assertSame(['db down'], $logger->getErrors());
    }

    private function createSync(Connection $connection, LockFactory|null $lockFactory = null): SyncMemberDatabase
    {
        $hasherFactory = new PasswordHasherFactory([
            FrontendUser::class => new PlaintextPasswordHasher(),
        ]);

        return new SyncMemberDatabase(
            $connection,
            new CsvFileProvider('/tmp', $this->createMock(RemoteCsvFileFinderInterface::class)),
            new CsvMemberReader(),
            new ContaoMemberWriter($connection, $hasherFactory),
            $lockFactory ?? $this->createMock(LockFactory::class),
            new TempMemberTableManager($connection),
            new Util(new RequestStack(), $connection),
            'de',
        );
    }

    private function dto(string $sacMemberId, string $sectionId): CsvMemberDto
    {
        $fields = array_fill(0, 30, '');
        $fields[0] = $sacMemberId;
        $fields[1] = $sectionId;
        $fields[2] = 'Muster';
        $fields[3] = 'Hans';

        return CsvMemberDto::fromCsv($fields);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invoke(object $object, string $method, array $args = []): mixed
    {
        // Since PHP 8.1 protected members are reflection-accessible without setAccessible().
        return (new \ReflectionMethod($object, $method))->invoke($object, ...$args);
    }
}
