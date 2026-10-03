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

namespace Markocupic\SacEventToolBundle\Tests\Feature\MemberToUserSync;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\MemberToUserSync\MemberToUserSync;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MemberToUserSyncTest extends TestCase
{
    /**
     * @var list<array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    private array $updates = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->updates = [];
    }

    public function testNothingChangedMeansNoUpdate(): void
    {
        $this->assertSame([], $this->createSync([])->getChangedFields($this->row()));
    }

    public function testOnlyChangedFieldsAreUpdated(): void
    {
        $row = $this->row(['member_street' => 'Neue Strasse 2', 'member_lastname' => 'Neu']);

        $this->assertSame(
            ['lastname' => 'Neu', 'street' => 'Neue Strasse 2', 'name' => 'Neu Anna'],
            $this->createSync([])->getChangedFields($row),
        );
    }

    public function testMissingEmailIsReplacedByAPlaceholder(): void
    {
        $row = $this->row(['member_email' => '']);

        $this->assertSame(['email' => 'invalid_amuster_123456@noemail.ch'], $this->createSync([])->getChangedFields($row));
    }

    public function testRunUpdatesOnlyChangedUsersAndDisablesFormerMembers(): void
    {
        $sync = $this->createSync([
            $this->row(['id' => '1']),
            $this->row(['id' => '2', 'member_city' => 'Kriens']),
            // Second member with the same sacMemberId: ignored
            $this->row(['id' => '2', 'member_id' => '99', 'member_city' => 'Horw']),
            // No member found (LEFT JOIN)
            $this->row(['id' => '3', 'name' => 'Ex Mitglied', 'member_id' => null]),
        ]);

        $sync->run();
        $log = $sync->getSyncLog();

        $this->assertSame(3, $log['processed']);
        $this->assertSame(1, $log['updates']);
        $this->assertSame(1, $log['disabled']);
        $this->assertFalse($log['with_error']);

        $this->assertSame(['tl_user', ['city' => 'Kriens'], ['id' => 2]], $this->updates[0]);
        $this->assertSame(0, $this->updates[1][1]['sacMemberId']);
        $this->assertSame(['id' => 3], $this->updates[1][2]);
        $this->assertCount(2, $log['log']);
    }

    public function testEveryRunStartsWithAnEmptyLog(): void
    {
        $sync = $this->createSync([$this->row(['member_city' => 'Kriens'])]);

        $sync->run();
        $sync->run();

        $this->assertSame(1, $sync->getSyncLog()['processed']);
        $this->assertSame(1, $sync->getSyncLog()['updates']);
    }

    public function testErrorRollsBackAndIsLogged(): void
    {
        $connection = $this->createConnection([$this->row(['member_city' => 'Kriens'])]);
        $connection
            ->method('update')
            ->willThrowException(new \RuntimeException('Deadlock found'))
        ;

        $connection
            ->method('isTransactionActive')
            ->willReturn(true)
        ;

        $connection
            ->expects($this->once())
            ->method('rollBack')
        ;

        $connection
            ->expects($this->never())
            ->method('commit')
        ;

        $sync = new MemberToUserSync($connection);
        $sync->run();

        $this->assertTrue($sync->getSyncLog()['with_error']);
        $this->assertSame('Deadlock found', $sync->getSyncLog()['exception']);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function createSync(array $rows): MemberToUserSync
    {
        $connection = $this->createConnection($rows);
        $connection
            ->method('update')
            ->willReturnCallback(
                function (string $table, array $data, array $criteria): int {
                    $this->updates[] = [$table, $data, $criteria];

                    return 1;
                },
            )
        ;

        return new MemberToUserSync($connection);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function createConnection(array $rows): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('iterateAssociative')
            ->willReturnCallback(
                function (string $sql) use ($rows): \Generator {
                    $this->assertStringContainsString('LEFT JOIN tl_member m ON m.sacMemberId = u.sacMemberId', $sql);
                    $this->assertStringContainsString('u.sacMemberId > 0', $sql);

                    yield from $rows;
                },
            )
        ;

        return $connection;
    }

    /**
     * A user whose data equals the member data, as returned by the database (strings).
     *
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function row(array $override = []): array
    {
        $data = [
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'sectionId' => 'a:1:{i:0;s:4:"4250";}',
            'dateOfBirth' => '315529200',
            'email' => 'anna@example.org',
            'street' => 'Bahnhofstrasse 1',
            'postal' => '6000',
            'city' => 'Luzern',
            'country' => 'ch',
            'gender' => 'female',
            'phone' => '041 123 45 67',
            'mobile' => '079 123 45 67',
        ];

        $row = ['id' => '1', 'username' => 'amuster', 'name' => 'Muster Anna', 'sacMemberId' => '123456', 'member_id' => '7'];

        foreach ($data as $field => $value) {
            $row[$field] = $value;
            $row['member_'.$field] = $value;
        }

        return array_merge($row, $override);
    }
}
