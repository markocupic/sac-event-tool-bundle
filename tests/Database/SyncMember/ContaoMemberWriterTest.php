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

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Result;
use Markocupic\SacEventToolBundle\Database\SyncMember\ContaoMemberWriter;
use Markocupic\SacEventToolBundle\Database\SyncMember\SyncLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;

class ContaoMemberWriterTest extends TestCase
{
    public function testSyncFromTempTableInsertsNewMember(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('executeQuery')
            ->willReturn($this->streamResult([
                ['id' => 1, 'sacMemberId' => 123, 'firstname' => 'Hans', 'lastname' => 'Muster'],
            ]))
        ;

        // Member does not exist in tl_member yet.
        $connection
            ->method('fetchOne')
            ->willReturn(false)
        ;

        $captured = null;
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                'tl_member',
                $this->callback(
                    static function (array $record) use (&$captured): bool {
                        $captured = $record;

                        return true;
                    },
                ),
            )
            ->willReturn(1)
        ;

        $logger = new SyncLogger();

        $this->writer($connection)->syncFromTempTable($logger);

        $this->assertSame(1, $logger->toArray()['countProcessed']);
        $this->assertCount(1, $logger->getInsertMessages());
        $this->assertStringContainsString('Hans Muster', $logger->getInsertMessages()[0]);
        $this->assertStringContainsString('123', $logger->getInsertMessages()[0]);

        // The primary key of the temp table must not be carried over.
        $this->assertArrayNotHasKey('id', $captured);
        // Insert enriches the record with account defaults.
        $this->assertSame(1, $captured['isSacMember']);
        $this->assertSame(1, $captured['login']);
        $this->assertSame(0, $captured['disable']);
        $this->assertArrayHasKey('dateAdded', $captured);
        $this->assertNotEmpty($captured['password']);
    }

    public function testSyncFromTempTableUpdatesExistingMember(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('executeQuery')
            ->willReturn($this->streamResult([
                ['id' => 1, 'sacMemberId' => 123, 'firstname' => 'Hans', 'lastname' => 'Muster'],
            ]))
        ;

        // Member already exists -> update path.
        $connection
            ->method('fetchOne')
            ->willReturn(55)
        ;

        // Main update + tstamp update.
        $connection
            ->expects($this->exactly(2))
            ->method('update')
            ->willReturn(1)
        ;

        $logger = new SyncLogger();

        $this->writer($connection)->syncFromTempTable($logger);

        $this->assertSame(1, $logger->toArray()['countProcessed']);
        $this->assertCount(1, $logger->getUpdateMessages());
        $this->assertStringContainsString('Hans Muster', $logger->getUpdateMessages()[0]);
    }

    public function testDisableNonMembersThrowsWhenThresholdExceeded(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(100) // total members
        ;

        $connection
            ->method('fetchFirstColumn')
            ->willReturn(range(1, 8)) // 8 % > 7 %
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aborting sync process');

        $this->writer($connection)->disableNonMembers(new SyncLogger());
    }

    public function testDisableNonMembersDisablesAndLogs(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(100) // total members
        ;

        $connection
            ->method('fetchFirstColumn')
            ->willReturn([10, 20]) // 2 % < 7 %
        ;

        $connection
            ->method('update')
            ->willReturn(1)
        ;

        $connection
            ->method('fetchAssociative')
            ->willReturnCallback(
                static fn (string $sql, array $params): array => [
                    'firstname' => 'First'.$params[0],
                    'lastname' => 'Last'.$params[0],
                    'sacMemberId' => $params[0] * 1000,
                ],
            )
        ;

        $logger = new SyncLogger();

        $this->writer($connection)->disableNonMembers($logger);

        $this->assertCount(2, $logger->getDisabledMessages());
        $this->assertStringContainsString('First10 Last10', $logger->getDisabledMessages()[0]);
    }

    public function testDisableNonMembersReturnsEarlyWhenNoMembers(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(0)
        ;

        $connection
            ->expects($this->never())
            ->method('fetchFirstColumn')
        ;

        $this->writer($connection)->disableNonMembers(new SyncLogger());
    }

    public function testPopulateMissingPasswordsUpdatesEveryMemberWithoutPassword(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn([3, 7])
        ;

        $updates = [];
        $connection
            ->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(
                static function (string $table, array $set, array $criteria) use (&$updates): int {
                    $updates[] = ['set' => $set, 'criteria' => $criteria];

                    return 1;
                },
            )
        ;

        $this->writer($connection)->populateMissingPasswords(20);

        $this->assertCount(2, $updates);
        $this->assertNotEmpty($updates[0]['set']['password']);
        $this->assertArrayHasKey('tstamp', $updates[0]['set']);
        $this->assertSame(3, $updates[0]['criteria']['id']);
        $this->assertSame(7, $updates[1]['criteria']['id']);
    }

    private function writer(Connection $connection): ContaoMemberWriter
    {
        $hasherFactory = new PasswordHasherFactory([
            FrontendUser::class => new PlaintextPasswordHasher(),
        ]);

        return new ContaoMemberWriter($connection, $hasherFactory);
    }

    /**
     * Builds a real DBAL Result backed by a stubbed driver result, so iterateAssociative() works.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function streamResult(array $rows): Result
    {
        $driverResult = $this->createMock(DriverResult::class);
        $driverResult
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(...array_merge($rows, [false]))
        ;

        return new Result($driverResult, $this->createMock(Connection::class));
    }
}
