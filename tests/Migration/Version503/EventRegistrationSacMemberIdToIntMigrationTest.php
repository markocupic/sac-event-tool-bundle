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
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Markocupic\SacEventToolBundle\Migration\Version503\EventRegistrationSacMemberIdToIntMigration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EventRegistrationSacMemberIdToIntMigrationTest extends TestCase
{
    /**
     * @dataProvider sacMemberIdProvider
     */
    public function testToSacMemberId(string $value, int $expected): void
    {
        $this->assertSame($expected, EventRegistrationSacMemberIdToIntMigration::toSacMemberId($value));
    }

    public static function sacMemberIdProvider(): iterable
    {
        yield 'plain number' => ['123456', 123456];
        yield 'leading zeros' => ['00167400', 167400];
        yield 'text after the number' => ['370883 SAC Pilatus', 370883];
        yield 'dot after the number' => ['320052.', 320052];
        yield 'spaces' => [' 123456 ', 123456];
        yield 'no number' => ['keine', 0];
        yield 'empty' => ['', 0];
    }

    public function testRunsOnlyWhileTheColumnIsNoInteger(): void
    {
        $this->assertTrue((new EventRegistrationSacMemberIdToIntMigration($this->createConnection(new StringType())))->shouldRun());
        $this->assertFalse((new EventRegistrationSacMemberIdToIntMigration($this->createConnection(new IntegerType())))->shouldRun());
    }

    public function testDoesNotRunWithoutTable(): void
    {
        $this->assertFalse((new EventRegistrationSacMemberIdToIntMigration($this->createConnection(new StringType(), tableExists: false)))->shouldRun());
    }

    public function testCorrectsValuesReplacesEmptyValuesAndConvertsTheColumn(): void
    {
        $connection = $this->createConnection(new StringType());
        $connection
            ->method('fetchAllAssociative')
            ->with($this->stringContains("NOT REGEXP '^[0-9]+$'"))
            ->willReturn([
                ['id' => '36066', 'sacMemberId' => '00167400'],
                ['id' => '47941', 'sacMemberId' => '370883 SAC Pilatus'],
            ])
        ;

        $updates = [];

        $connection
            ->method('update')
            ->willReturnCallback(
                static function (string $table, array $data, array $criteria) use (&$updates): int {
                    $updates[] = [$table, $data, $criteria];

                    return 1;
                },
            )
        ;

        $statements = [];

        $connection
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql) use (&$statements): int {
                    $statements[] = $sql;

                    return 0;
                },
            )
        ;

        $result = (new EventRegistrationSacMemberIdToIntMigration($connection))->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(
            [
                ['tl_calendar_events_member', ['sacMemberId' => '167400'], ['id' => 36066]],
                ['tl_calendar_events_member', ['sacMemberId' => '370883'], ['id' => 47941]],
            ],
            $updates,
        );

        // First the empty values, then the column type
        $this->assertSame("UPDATE tl_calendar_events_member SET sacMemberId = '0' WHERE sacMemberId = ''", $statements[0]);
        $this->assertSame('ALTER TABLE tl_calendar_events_member MODIFY sacMemberId INT(10) UNSIGNED NOT NULL DEFAULT 0', $statements[1]);

        $this->assertStringContainsString('ID 36066: "00167400" → 167400', $result->getMessage());
        $this->assertStringContainsString('ID 47941: "370883 SAC Pilatus" → 370883', $result->getMessage());
    }

    private function createConnection(Type $type, bool $tableExists = true): Connection&MockObject
    {
        $column = $this->createMock(Column::class);
        $column
            ->method('getType')
            ->willReturn($type)
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
