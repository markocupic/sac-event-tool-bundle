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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\IntegerType;
use Markocupic\SacEventToolBundle\Database\SyncMember\TempMemberTableManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TempMemberTableManagerTest extends TestCase
{
    private Connection&MockObject $connection;

    private AbstractSchemaManager&MockObject $schemaManager;

    protected function setUp(): void
    {
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->connection = $this->createMock(Connection::class);
        $this->connection
            ->method('createSchemaManager')
            ->willReturn($this->schemaManager)
        ;
    }

    public function testDropRemovesTableWhenItExists(): void
    {
        $this->schemaManager
            ->method('tablesExist')
            ->willReturn(true)
        ;

        $this->schemaManager
            ->expects($this->once())
            ->method('dropTable')
            ->with(TempMemberTableManager::TABLE_NAME)
        ;

        (new TempMemberTableManager($this->connection))->drop();
    }

    public function testDropDoesNothingWhenTableIsMissing(): void
    {
        $this->schemaManager
            ->method('tablesExist')
            ->willReturn(false)
        ;

        $this->schemaManager
            ->expects($this->never())
            ->method('dropTable')
        ;

        (new TempMemberTableManager($this->connection))->drop();
    }

    public function testCreateDropsExistingTableFirst(): void
    {
        $this->schemaManager
            ->method('tablesExist')
            ->willReturn(true)
        ;

        $this->schemaManager
            ->expects($this->once())
            ->method('dropTable')
            ->with(TempMemberTableManager::TABLE_NAME)
        ;

        $this->schemaManager
            ->expects($this->once())
            ->method('createTable')
        ;

        (new TempMemberTableManager($this->connection))->create();
    }

    public function testCreateBuildsTheExpectedTableDefinition(): void
    {
        $this->schemaManager
            ->method('tablesExist')
            ->willReturn(false)
        ;

        $capturedTable = null;
        $this->schemaManager
            ->expects($this->once())
            ->method('createTable')
            ->with($this->callback(
                static function (Table $table) use (&$capturedTable): bool {
                    $capturedTable = $table;

                    return true;
                },
            ))
        ;

        (new TempMemberTableManager($this->connection))->create();

        $this->assertInstanceOf(Table::class, $capturedTable);
        $this->assertSame(TempMemberTableManager::TABLE_NAME, $capturedTable->getName());

        // id + 25 member fields.
        $this->assertCount(26, $capturedTable->getColumns());
        $this->assertTrue($capturedTable->hasColumn('sacMemberId'));
        $this->assertTrue($capturedTable->hasColumn('username'));
        $this->assertTrue($capturedTable->hasColumn('sectionId'));

        $this->assertInstanceOf(IntegerType::class, $capturedTable->getColumn('sacMemberId')->getType());
        $this->assertInstanceOf(BlobType::class, $capturedTable->getColumn('sectionId')->getType());

        $primaryIndexes = array_values(array_filter(
            $capturedTable->getIndexes(),
            static fn ($index): bool => $index->isPrimary(),
        ));
        $this->assertCount(1, $primaryIndexes);
        $this->assertSame(['id'], $primaryIndexes[0]->getColumns());

        $uniqueColumns = [];

        foreach ($capturedTable->getIndexes() as $index) {
            if ($index->isUnique() && !$index->isPrimary()) {
                $uniqueColumns[] = $index->getColumns();
            }
        }

        $this->assertContains(['username'], $uniqueColumns);
        $this->assertContains(['sacMemberId'], $uniqueColumns);
    }
}
