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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventRegistrationDatabaseSync;

use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationDatabaseSync\SyncEventRegistrationDatabase;
use PHPUnit\Framework\MockObject\MockObject;

final class SyncEventRegistrationDatabaseTest extends ContaoTestCase
{
    private const UPCOMING_EVENT = 100;

    private const PAST_EVENT = 50;

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
        $this->assertSame([], $this->createSync([])->getChangedFields($this->row(), true));
    }

    public function testOnlyChangedFieldsAreUpdated(): void
    {
        $row = $this->row(['member_street' => 'Neue Strasse 2', 'member_phone' => '041 000 00 00']);

        $this->assertSame(
            ['street' => 'Neue Strasse 2', 'phone' => '041 000 00 00'],
            $this->createSync([])->getChangedFields($row, false),
        );
    }

    public function testEmptyEmailAndMobileOfTheMemberDoNotOverwriteTheRegistration(): void
    {
        $row = $this->row(['member_email' => '', 'member_mobile' => '']);

        $this->assertSame([], $this->createSync([])->getChangedFields($row, true));
    }

    public function testEmergencyContactAndFoodHabitsOnlyForUpcomingEvents(): void
    {
        $row = $this->row(['member_emergencyPhone' => '079 111 11 11', 'member_emergencyPhoneName' => 'Beat', 'member_foodHabits' => 'vegetarisch']);
        $sync = $this->createSync([]);

        $this->assertSame([], $sync->getChangedFields($row, false));
        $this->assertSame(
            ['emergencyPhone' => '079 111 11 11', 'emergencyPhoneName' => 'Beat', 'foodHabits' => 'vegetarisch'],
            $sync->getChangedFields($row, true),
        );
    }

    public function testSacMemberIdIsCorrected(): void
    {
        $sync = $this->createSync([]);

        $this->assertSame(['sacMemberId' => '167400'], $sync->getChangedFields($this->row(['sacMemberId' => '00167400', 'member_sacMemberId' => '167400']), false));
        $this->assertSame(['sacMemberId' => '370883'], $sync->getChangedFields($this->row(['sacMemberId' => '370883 SAC Pilatus', 'member_sacMemberId' => '370883']), false));
    }

    public function testSacMemberIdIsNotOverwrittenIfTheMemberHasNone(): void
    {
        $this->assertSame([], $this->createSync([])->getChangedFields($this->row(['member_sacMemberId' => '0']), false));
    }

    public function testEmergencyContactNeedsPhoneAndName(): void
    {
        $row = $this->row(['member_emergencyPhone' => '079 111 11 11', 'member_emergencyPhoneName' => '']);

        $this->assertSame([], $this->createSync([])->getChangedFields($row, true));
    }

    public function testRunUpdatesOnlyChangedRegistrationsAndReturnsTheLog(): void
    {
        $sync = $this->createSync([
            $this->row(['id' => 1, 'member_id' => 7]),
            $this->row(['id' => 2, 'member_id' => 7, 'member_lastname' => 'Neu']),
            $this->row(['id' => 3, 'member_id' => 8, 'eventId' => self::PAST_EVENT, 'member_foodHabits' => 'vegan']),
        ]);

        $log = $sync->run();

        $this->assertSame(3, $log['processed_registrations']);
        $this->assertSame(2, $log['processed_members']);
        $this->assertSame(1, $log['updates']);
        $this->assertFalse($log['with_error']);
        $this->assertSame([['tl_calendar_events_member', ['lastname' => 'Neu'], ['id' => 2]]], $this->updates);
        $this->assertStringContainsString('registration ID 2', $log['log'][0]);
    }

    public function testEveryRunStartsWithAnEmptyLog(): void
    {
        $sync = $this->createSync([$this->row(['id' => 1, 'member_lastname' => 'Neu'])]);

        $sync->run();
        $log = $sync->run();

        $this->assertSame(1, $log['processed_registrations']);
        $this->assertSame(1, $log['updates']);
    }

    public function testSyncMemberReturnsTheNumberOfUpdatesAndFiltersByMember(): void
    {
        $sync = $this->createSync([$this->row(['id' => 1, 'member_city' => 'Kriens'])], expectedMemberId: 7);

        $this->assertSame(1, $sync->syncMember(7));
    }

    public function testErrorRollsBackAndIsLogged(): void
    {
        $connection = $this->createConnection([$this->row(['member_lastname' => 'Neu'])]);
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

        $log = (new SyncEventRegistrationDatabase($this->mockContaoFramework(), $connection))->run();

        $this->assertTrue($log['with_error']);
        $this->assertSame(['Deadlock found'], $log['exceptions']);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function createSync(array $rows, int|null $expectedMemberId = null): SyncEventRegistrationDatabase
    {
        $connection = $this->createConnection($rows, $expectedMemberId);
        $connection
            ->method('update')
            ->willReturnCallback(
                function (string $table, array $data, array $criteria): int {
                    $this->updates[] = [$table, $data, $criteria];

                    return 1;
                },
            )
        ;

        return new SyncEventRegistrationDatabase($this->mockContaoFramework(), $connection);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function createConnection(array $rows, int|null $expectedMemberId = null): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn([(string) self::UPCOMING_EVENT])
        ;

        $connection
            ->method('iterateAssociative')
            ->willReturnCallback(
                function (string $sql, array $params) use ($rows, $expectedMemberId): \Generator {
                    $this->assertStringContainsString('r.anonymized = 0', $sql);
                    $this->assertSame(null === $expectedMemberId ? [] : [$expectedMemberId], $params);

                    yield from $rows;
                },
            )
        ;

        return $connection;
    }

    /**
     * A registration whose data equals the member data, as returned by the database (strings).
     *
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function row(array $override = []): array
    {
        $data = [
            'gender' => 'female',
            'firstname' => 'Anna',
            'lastname' => 'Muster',
            'street' => 'Bahnhofstrasse 1',
            'postal' => '6000',
            'city' => 'Luzern',
            'dateOfBirth' => '315529200',
            'phone' => '041 123 45 67',
            'sacMemberId' => '123456',
            'email' => 'anna@example.org',
            'mobile' => '079 123 45 67',
            'emergencyPhone' => '',
            'emergencyPhoneName' => '',
            'foodHabits' => '',
        ];

        $row = ['id' => '1', 'eventId' => (string) self::UPCOMING_EVENT, 'member_id' => '7'];

        foreach ($data as $field => $value) {
            $row[$field] = $value;
            $row['member_'.$field] = $value;
        }

        return array_merge($row, $override);
    }
}
