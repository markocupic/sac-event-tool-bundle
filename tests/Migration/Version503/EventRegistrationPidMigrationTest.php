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
use Markocupic\SacEventToolBundle\Migration\Version503\EventRegistrationPidMigration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EventRegistrationPidMigrationTest extends TestCase
{
    public function testRunsWhilePidDiffersFromEventId(): void
    {
        $connection = $this->createConnection();
        $connection
            ->method('fetchOne')
            ->with($this->stringContains('WHERE pid <> eventId'))
            ->willReturn(42)
        ;

        $this->assertTrue((new EventRegistrationPidMigration($connection))->shouldRun());
    }

    public function testDoesNotRunWhenPidEqualsEventId(): void
    {
        $connection = $this->createConnection();
        $connection
            ->method('fetchOne')
            ->willReturn(false)
        ;

        $this->assertFalse((new EventRegistrationPidMigration($connection))->shouldRun());
    }

    public function testDoesNotRunWithoutPidColumn(): void
    {
        $connection = $this->createConnection(['eventid']);
        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $this->assertFalse((new EventRegistrationPidMigration($connection))->shouldRun());
    }

    public function testDoesNotRunWithoutTable(): void
    {
        $this->assertFalse((new EventRegistrationPidMigration($this->createConnection(tableExists: false)))->shouldRun());
    }

    public function testCopiesEventIdToPid(): void
    {
        $connection = $this->createConnection();
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('UPDATE tl_calendar_events_member SET pid = eventId WHERE pid <> eventId')
            ->willReturn(5)
        ;

        $result = (new EventRegistrationPidMigration($connection))->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('5 registration(s)', $result->getMessage());
    }

    /**
     * @param array<string> $columns
     */
    private function createConnection(array $columns = ['pid', 'eventid'], bool $tableExists = true): Connection&MockObject
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->method('tablesExist')
            ->willReturn($tableExists)
        ;

        $schemaManager
            ->method('listTableColumns')
            ->willReturn(array_fill_keys($columns, $this->createMock(Column::class)))
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        return $connection;
    }
}
