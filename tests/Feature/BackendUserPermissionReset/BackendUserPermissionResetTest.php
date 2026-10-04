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

namespace Markocupic\SacEventToolBundle\Tests\Feature\BackendUserPermissionReset;

use Contao\FilesModel;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Markocupic\SacEventToolBundle\Feature\BackendUserPermissionReset\BackendUserPermissionReset;
use PHPUnit\Framework\MockObject\MockObject;

final class BackendUserPermissionResetTest extends ContaoTestCase
{
    /**
     * @var list<array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    private array $updates = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->updates = [];
        $GLOBALS['TL_PERMISSIONS'] = ['calendars', 'calendarp', 'news'];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_PERMISSIONS']);

        parent::tearDown();
    }

    public function testUnknownUserIsNotReset(): void
    {
        $connection = $this->createConnection(false, []);
        $connection
            ->expects($this->never())
            ->method('update')
        ;

        $this->assertFalse($this->createReset($connection)->resetUser('nobody'));
    }

    public function testPermissionsAreReplacedByHomeDirectoryAndGroupPermissions(): void
    {
        $connection = $this->createConnection(
            ['id' => '5', 'groups' => serialize(['1', '2'])],
            [
                ['id' => '1', 'modules' => serialize(['calendar']), 'filemounts' => serialize(['GROUP_MOUNT']), 'calendars' => serialize(['3'])],
                ['id' => '2', 'modules' => serialize(['calendar', 'news']), 'filemounts' => '', 'calendars' => serialize(['4'])],
            ],
        );

        $this->assertTrue($this->createReset($connection, 'HOME_UUID')->resetUser('amuster'));

        [$table, $data, $criteria] = $this->updates[0];

        $this->assertSame('tl_user', $table);
        $this->assertSame(['id' => 5], $criteria);

        // Only fields that exist in tl_user (calendarp and news do not exist in this test)
        $this->assertSame(['modules', 'filemounts', 'calendars'], array_keys($data));
        $this->assertSame(['calendar', 'news'], unserialize($data['modules']));
        $this->assertSame(['HOME_UUID', 'GROUP_MOUNT'], unserialize($data['filemounts']));
        $this->assertSame(['3', '4'], unserialize($data['calendars']));
    }

    public function testUserWithoutGroupsAndHomeDirectoryGetsEmptyPermissions(): void
    {
        $connection = $this->createConnection(['id' => '5', 'groups' => ''], []);
        $connection
            ->expects($this->never())
            ->method('fetchAllAssociative')
        ;

        $this->createReset($connection)->resetUser('amuster');

        $this->assertSame([], unserialize($this->updates[0][1]['modules']));
        $this->assertSame([], unserialize($this->updates[0][1]['filemounts']));
    }

    public function testOnlyActiveGroupsAreQueriedWithParameters(): void
    {
        $connection = $this->createConnection(['id' => '5', 'groups' => serialize(['1', '2'])], []);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('id IN (?)'),
                    $this->stringContains('disable = 0'),
                    $this->stringContains("start = '' OR start <= ?"),
                    $this->stringContains("stop = '' OR stop > ?"),
                ),
                $this->callback(static fn (array $params): bool => [1, 2] === $params[0] && 0 === (int) $params[1] % 60),
                $this->callback(static fn (array $types): bool => ArrayParameterType::INTEGER === $types[0]),
            )
            ->willReturn([])
        ;

        $this->createReset($connection)->resetUser('amuster');
    }

    public function testResetAllResetsEveryResettableUser(): void
    {
        $connection = $this->createConnection(['id' => '5', 'groups' => ''], []);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with($this->logicalAnd($this->stringContains('admin = 0'), $this->stringContains("inherit = 'extend'")))
            ->willReturn(['amuster', 'bmuster'])
        ;

        $this->assertSame(2, $this->createReset($connection)->resetAll());
        $this->assertCount(2, $this->updates);
    }

    public function testIsResettable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->with($this->logicalAnd($this->stringContains('admin = 0'), $this->stringContains("inherit = 'extend'")), ['amuster'])
            ->willReturnOnConsecutiveCalls('5', false)
        ;

        $reset = $this->createReset($connection);

        $this->assertTrue($reset->isResettable('amuster'));
        $this->assertFalse($reset->isResettable('amuster'));
    }

    /**
     * @param array<string, mixed>|false $user
     * @param list<array<string, mixed>> $groups
     */
    private function createConnection(array|false $user, array $groups): Connection&MockObject
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->method('listTableColumns')
            ->willReturn([
                'modules' => $this->createMock(Column::class),
                'filemounts' => $this->createMock(Column::class),
                'calendars' => $this->createMock(Column::class),
            ])
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        $connection
            ->method('fetchAssociative')
            ->willReturn($user)
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturn($groups)
        ;
        $connection
            ->method('update')
            ->willReturnCallback(
                function (string $table, array $data, array $criteria): int {
                    $this->updates[] = [$table, $data, $criteria];

                    return 1;
                },
            )
        ;

        return $connection;
    }

    private function createReset(Connection $connection, string|null $homeDirectoryUuid = null): BackendUserPermissionReset
    {
        $folder = null === $homeDirectoryUuid ? null : $this->mockClassWithProperties(FilesModel::class, ['uuid' => $homeDirectoryUuid]);

        $filesModelAdapter = $this->mockAdapter(['findByPath']);
        $filesModelAdapter
            ->method('findByPath')
            ->with('files/home/5')
            ->willReturn($folder)
        ;

        return new BackendUserPermissionReset(
            $this->mockContaoFramework([FilesModel::class => $filesModelAdapter]),
            $connection,
            'files/home',
        );
    }
}
