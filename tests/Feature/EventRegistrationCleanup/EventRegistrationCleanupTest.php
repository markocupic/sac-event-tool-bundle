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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventRegistrationCleanup;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationCleanup\EventRegistrationCleanup;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\EventRegistrationAnonymizer;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\EventRegistrationRemover;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EventRegistrationCleanupTest extends TestCase
{
    public function testDryRunChangesNothing(): void
    {
        $remover = $this->createMock(EventRegistrationRemover::class);
        $remover
            ->expects($this->never())
            ->method('remove')
        ;

        $anonymizer = $this->createMock(EventRegistrationAnonymizer::class);
        $anonymizer
            ->expects($this->never())
            ->method('anonymize')
        ;

        $result = (new EventRegistrationCleanup($this->createConnection([['id' => '1']], [['id' => '2']]), $anonymizer, $remover))->run(true);

        $this->assertSame([['id' => '1']], $result['anonymized']);
        $this->assertSame([['id' => '2']], $result['deleted']);
    }

    public function testDeletesRegistrationsOfDeletedEventsAndAnonymizesRegistrationsOfDeletedMembers(): void
    {
        $remover = $this->createMock(EventRegistrationRemover::class);
        $remover
            ->expects($this->once())
            ->method('remove')
            ->with([2, 4])
        ;

        $anonymized = [];

        $anonymizer = $this->createMock(EventRegistrationAnonymizer::class);
        $anonymizer
            ->method('anonymize')
            ->willReturnCallback(
                static function (int $id) use (&$anonymized): bool {
                    $anonymized[] = $id;

                    return true;
                }
            )
        ;

        $connection = $this->createConnection([['id' => '1'], ['id' => '3']], [['id' => '2'], ['id' => '4']]);

        (new EventRegistrationCleanup($connection, $anonymizer, $remover))->run();

        $this->assertSame([1, 3], $anonymized);
    }

    public function testMemberMustBeMissingByContaoMemberIdAndBySacMemberId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->logicalAnd(
                $this->stringContains('r.anonymized = 0'),
                // Guests (no Contao member ID and no SAC member ID) are not included
                $this->stringContains("(r.contaoMemberId > 0 OR (r.sacMemberId <> '' AND r.sacMemberId <> '0'))"),
                $this->stringContains('NOT EXISTS (SELECT 1 FROM tl_member AS m WHERE m.id = r.contaoMemberId)'),
                $this->stringContains("NOT (r.sacMemberId <> '' AND r.sacMemberId <> '0' AND EXISTS (SELECT 1 FROM tl_member AS m WHERE m.sacMemberId = r.sacMemberId))"),
                $this->stringContains('AND EXISTS (SELECT 1 FROM tl_calendar_events AS e WHERE e.id = r.eventId)'),
            ))
            ->willReturn([])
        ;

        $this->createCleanup($connection)->findRegistrationsOfDeletedMembers();
    }

    public function testAllRegistrationsOfDeletedEventsAreDeleted(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->logicalAnd(
                $this->logicalNot($this->stringContains("r.sacMemberId = ''")),
                $this->stringContains('NOT EXISTS (SELECT 1 FROM tl_calendar_events AS e WHERE e.id = r.eventId)'),
            ))
            ->willReturn([])
        ;

        $this->createCleanup($connection)->findRegistrationsOfDeletedEvents();
    }

    private function createCleanup(Connection $connection): EventRegistrationCleanup
    {
        return new EventRegistrationCleanup($connection, $this->createMock(EventRegistrationAnonymizer::class), $this->createMock(EventRegistrationRemover::class));
    }

    /**
     * @param list<array<string, mixed>> $ofDeletedMembers
     * @param list<array<string, mixed>> $ofDeletedEvents
     */
    private function createConnection(array $ofDeletedMembers, array $ofDeletedEvents): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(static fn (string $sql): array => str_contains($sql, 'tl_member') ? $ofDeletedMembers : $ofDeletedEvents)
        ;

        return $connection;
    }
}
