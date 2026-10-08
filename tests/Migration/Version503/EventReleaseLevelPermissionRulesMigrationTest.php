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
use Markocupic\SacEventToolBundle\Migration\Version503\EventReleaseLevelPermissionRulesMigration;
use PHPUnit\Framework\TestCase;

final class EventReleaseLevelPermissionRulesMigrationTest extends TestCase
{
    public function testDoesNotRunWithoutTableOrColumn(): void
    {
        $this->assertFalse((new EventReleaseLevelPermissionRulesMigration($this->createConnection(tableExists: false)))->shouldRun());
        $this->assertFalse((new EventReleaseLevelPermissionRulesMigration($this->createConnection(columnExists: false)))->shouldRun());
        $this->assertFalse((new EventReleaseLevelPermissionRulesMigration($this->createConnection(oldColumnsExist: false)))->shouldRun());
    }

    public function testRunsOnlyIfThereAreReleaseLevelsWithoutRules(): void
    {
        $this->assertTrue((new EventReleaseLevelPermissionRulesMigration($this->createConnection(levelsWithoutRules: 2)))->shouldRun());
        $this->assertFalse((new EventReleaseLevelPermissionRulesMigration($this->createConnection(levelsWithoutRules: 0)))->shouldRun());
    }

    public function testCreatesRulesForTheEventPartiesAndOneRulePerGroup(): void
    {
        $rules = EventReleaseLevelPermissionRulesMigration::createRules([
            'allowWriteAccessToAuthor' => '1',
            'allowWriteAccessToInstructors' => '1',
            'allowDeleteAccessToAuthor' => '',
            'allowDeleteAccessToInstructors' => '',
            'allowCutAccessToAuthor' => '1',
            'allowCutAccessToInstructors' => '1',
            'allowAdministerEventRegistrationsToAuthors' => '1',
            'allowAdministerEventRegistrationsToInstructors' => '1',
            'allowSwitchingToNextLevel' => '1',
            'allowSwitchingToPrevLevel' => '',
            'groupEventPerm' => serialize([
                ['group' => '3', 'permissions' => ['canWriteEvent', 'canDeleteEvent']],
                ['group' => '', 'permissions' => ['canWriteEvent']],
            ]),
            'groupReleaseLevelPerm' => serialize([
                ['group' => '3', 'permissions' => ['canRelLevelDown']],
                ['group' => '5', 'permissions' => ['canRelLevelUp', 'canRelLevelDown']],
            ]),
        ]);

        $this->assertSame(
            [
                1 => [
                    'parties' => ['event_author', 'event_instructors'],
                    'groups' => [],
                    'flags' => ['can_write_event', 'can_cut_event', 'can_administer_event_registrations', 'can_upgrade_release_level'],
                ],
                2 => [
                    'parties' => ['registration_coordinator'],
                    'groups' => [],
                    'flags' => ['can_write_event'],
                ],
                3 => [
                    'parties' => [],
                    'groups' => ['3'],
                    'flags' => ['can_write_event', 'can_delete_event', 'can_downgrade_release_level'],
                ],
                4 => [
                    'parties' => [],
                    'groups' => ['5'],
                    'flags' => ['can_upgrade_release_level', 'can_downgrade_release_level'],
                ],
            ],
            $rules,
        );
    }

    public function testSwitchingTheLevelRequiresWriteAccess(): void
    {
        $rules = EventReleaseLevelPermissionRulesMigration::createRules([
            'allowCutAccessToAuthor' => '1',
            'allowCutAccessToInstructors' => '1',
            'allowSwitchingToNextLevel' => '1',
            'allowSwitchingToPrevLevel' => '1',
        ]);

        $this->assertSame(['can_cut_event'], $rules[1]['flags']);
    }

    public function testOnlyConvertsTheCommonRightsOfAuthorAndInstructors(): void
    {
        $rules = EventReleaseLevelPermissionRulesMigration::createRules([
            'allowWriteAccessToAuthor' => '1',
            'allowWriteAccessToInstructors' => '1',
            'allowDeleteAccessToInstructors' => '1',
        ]);

        $this->assertSame(['can_write_event'], $rules[1]['flags']);
    }

    public function testAlwaysCreatesTheRulesForTheEventParties(): void
    {
        $this->assertSame(
            [
                1 => ['parties' => ['event_author', 'event_instructors'], 'groups' => [], 'flags' => []],
                2 => ['parties' => ['registration_coordinator'], 'groups' => [], 'flags' => ['can_write_event']],
            ],
            EventReleaseLevelPermissionRulesMigration::createRules([]),
        );
    }

    public function testTheRegistrationCoordinatorAdministersTheRegistrationsOnTheHighestLevel(): void
    {
        $rules = EventReleaseLevelPermissionRulesMigration::createRules([], true);

        $this->assertSame(['registration_coordinator'], $rules[2]['parties']);
        $this->assertSame(['can_write_event', 'can_administer_event_registrations'], $rules[2]['flags']);
    }

    public function testConvertsTheGroupPermissionToAdministerTheRegistrations(): void
    {
        $rules = EventReleaseLevelPermissionRulesMigration::createRules([
            'groupEventPerm' => serialize([['group' => '3', 'permissions' => ['canAdministerEventRegistrations']]]),
        ]);

        $this->assertSame(['3'], $rules[3]['groups']);
        $this->assertSame(['can_administer_event_registrations'], $rules[3]['flags']);
    }

    public function testIgnoresPermissionsInTheWrongField(): void
    {
        $rules = EventReleaseLevelPermissionRulesMigration::createRules([
            'groupEventPerm' => serialize([['group' => '3', 'permissions' => ['canRelLevelUp', 'canWriteEvent']]]),
            'groupReleaseLevelPerm' => serialize([['group' => '3', 'permissions' => ['canDeleteEvent']]]),
        ]);

        $this->assertSame(['can_write_event'], $rules[3]['flags']);
    }

    public function testStoresTheRulesAndReportsDifferentRights(): void
    {
        $rows = [
            ['id' => '11', 'pid' => '2', 'level' => '1', 'allowWriteAccessToAuthor' => '1', 'allowWriteAccessToInstructors' => '1'],
            ['id' => '12', 'pid' => '2', 'level' => '2', 'allowWriteAccessToAuthor' => '1'],
        ];

        $updates = [];

        $connection = $this->createConnection(rows: $rows);
        $connection
            ->method('update')
            ->willReturnCallback(
                static function (string $table, array $data, array $criteria) use (&$updates): int {
                    $updates[$criteria['id']] = unserialize($data['permissionRules']);

                    return 1;
                },
            )
        ;

        $result = (new EventReleaseLevelPermissionRulesMigration($connection))->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertSame([11, 12], array_keys($updates));
        $this->assertSame(['can_write_event'], $updates[11][1]['flags']);
        $this->assertSame([], $updates[12][1]['flags']);
        $this->assertSame(['can_write_event'], $updates[11][2]['flags']);
        $this->assertSame(['can_write_event', 'can_administer_event_registrations'], $updates[12][2]['flags']);
        $this->assertStringContainsString('converted the permissions of 2 release level(s)', $result->getMessage());
        $this->assertStringContainsString('ID 12 (pid 2, level 2)', $result->getMessage());
        $this->assertStringNotContainsString('ID 11', $result->getMessage());
    }

    private function createConnection(bool $tableExists = true, bool $columnExists = true, int $levelsWithoutRules = 1, array $rows = [], bool $oldColumnsExist = true): Connection
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->method('tablesExist')
            ->willReturn($tableExists)
        ;

        $schemaManager
            ->method('listTableColumns')
            ->willReturn(array_filter([
                'permissionRules' => $columnExists ? $this->createMock(Column::class) : null,
                'allowWriteAccessToAuthor' => $oldColumnsExist ? $this->createMock(Column::class) : null,
            ]))
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        $connection
            ->method('fetchOne')
            ->willReturn((string) $levelsWithoutRules)
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturn($rows)
        ;

        // The highest level of every release level system: [pid => level]
        $connection
            ->method('fetchAllKeyValue')
            ->willReturn(['2' => '2'])
        ;

        return $connection;
    }
}
