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

namespace Markocupic\SacEventToolBundle\Tests\Migration\Version503;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Markocupic\SacEventToolBundle\Migration\Version503\SacMemberIdNullableMigration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SacMemberIdNullableMigrationTest extends TestCase
{
    public function testRunsWhileTheColumnIsNotNullable(): void
    {
        $connection = $this->createConnection(notNull: true);

        $this->assertTrue((new SacMemberIdNullableMigration($connection))->shouldRun());
    }

    public function testRunsWhileTheColumnStillContainsZero(): void
    {
        $connection = $this->createConnection(notNull: false);
        $connection->method('fetchOne')->willReturn(42);

        $this->assertTrue((new SacMemberIdNullableMigration($connection))->shouldRun());
    }

    public function testDoesNotRunWhenNullableAndWithoutZero(): void
    {
        $connection = $this->createConnection(notNull: false);
        $connection->method('fetchOne')->willReturn(false);

        $this->assertFalse((new SacMemberIdNullableMigration($connection))->shouldRun());
    }

    public function testDoesNotRunWithoutTable(): void
    {
        $this->assertFalse((new SacMemberIdNullableMigration($this->createConnection(notNull: true, tableExists: false)))->shouldRun());
    }

    public function testConvertsBothTablesAndReplacesZeroByNull(): void
    {
        $connection = $this->createConnection(notNull: true);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $statements = [];

        $connection
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql) use (&$statements): int {
                    $statements[] = $sql;

                    return 3;
                },
            )
        ;

        $result = (new SacMemberIdNullableMigration($connection))->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(
            [
                'ALTER TABLE tl_member MODIFY sacMemberId INT(10) UNSIGNED NULL DEFAULT NULL',
                'UPDATE tl_member SET sacMemberId = NULL WHERE sacMemberId = 0',
                'ALTER TABLE tl_user MODIFY sacMemberId INT(10) UNSIGNED NULL DEFAULT NULL',
                'UPDATE tl_user SET sacMemberId = NULL WHERE sacMemberId = 0',
            ],
            $statements,
        );
    }

    public function testListsDuplicatesAndFails(): void
    {
        $connection = $this->createConnection(notNull: true);
        $connection->method('executeStatement')->willReturn(0);
        $connection
            ->method('fetchAllAssociative')
            ->with($this->stringContains('HAVING COUNT(id) > 1'))
            ->willReturnOnConsecutiveCalls(
                [['sacMemberId' => '185155', 'ids' => '12,98']],
                [],
            )
        ;

        $result = (new SacMemberIdNullableMigration($connection))->run();

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('tl_member.sacMemberId 185155 (IDs 12,98)', $result->getMessage());
    }

    private function createConnection(bool $notNull, bool $tableExists = true): Connection&MockObject
    {
        $column = $this->createMock(Column::class);
        $column
            ->method('getNotnull')
            ->willReturn($notNull)
        ;

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->method('tablesExist')
            ->willReturn($tableExists)
        ;

        $schemaManager
            ->method('listTableColumns')
            ->willReturn(['sacmemberid' => $column])
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        return $connection;
    }
}
