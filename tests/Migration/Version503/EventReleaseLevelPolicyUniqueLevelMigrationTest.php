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
use Markocupic\SacEventToolBundle\Migration\Version503\EventReleaseLevelPolicyUniqueLevelMigration;
use PHPUnit\Framework\TestCase;

final class EventReleaseLevelPolicyUniqueLevelMigrationTest extends TestCase
{
    public function testRunsOnlyWhileTheColumnIsNotNullable(): void
    {
        $this->assertTrue((new EventReleaseLevelPolicyUniqueLevelMigration($this->createConnection(notNull: true)))->shouldRun());
        $this->assertFalse((new EventReleaseLevelPolicyUniqueLevelMigration($this->createConnection(notNull: false)))->shouldRun());
    }

    public function testDoesNotRunWithoutTable(): void
    {
        $this->assertFalse((new EventReleaseLevelPolicyUniqueLevelMigration($this->createConnection(notNull: true, tableExists: false)))->shouldRun());
    }

    public function testMakesTheColumnNullableAndReplacesLevelZero(): void
    {
        $statements = [];

        $connection = $this->createConnection(notNull: true);
        $connection
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql) use (&$statements): int {
                    $statements[] = $sql;

                    return 0;
                },
            )
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturn([])
        ;

        $result = (new EventReleaseLevelPolicyUniqueLevelMigration($connection))->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('DEFAULT NULL', $statements[0]);
        $this->assertStringContainsString('SET level = NULL WHERE level = 0', $statements[1]);
    }

    public function testReportsDuplicateLevels(): void
    {
        $connection = $this->createConnection(notNull: true);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([['pid' => '2', 'level' => '3', 'count' => '2']])
        ;

        $result = (new EventReleaseLevelPolicyUniqueLevelMigration($connection))->run();

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('release level system (pid) 2: level 3 (2x)', $result->getMessage());
    }

    private function createConnection(bool $notNull, bool $tableExists = true): Connection
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
            ->willReturn(['level' => $column])
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        return $connection;
    }
}
