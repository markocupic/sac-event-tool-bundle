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

namespace Markocupic\SacEventToolBundle\Tests\Feature\MemberProfileDeletion;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\MemberModel;
use Contao\Message;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\EventRegistrationAnonymizer;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\EventRegistrationRemover;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\MemberProfileDeletion;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use PHPUnit\Framework\MockObject\MockObject;

final class MemberProfileDeletionTest extends ContaoTestCase
{
    private MemberModel&MockObject $member;

    private EventRegistrationAnonymizer&MockObject $anonymizer;

    private EventRegistrationRemover&MockObject $remover;

    /**
     * @var list<string>
     */
    private array $errors = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->mockClassWithProperties(MemberModel::class, ['id' => 5, 'sacMemberId' => '123456', 'firstname' => 'Anna', 'lastname' => 'Muster']);
        $this->anonymizer = $this->createMock(EventRegistrationAnonymizer::class);
        $this->remover = $this->createMock(EventRegistrationRemover::class);
        $this->errors = [];
    }

    public function testUnknownMember(): void
    {
        $this->anonymizer
            ->expects($this->never())
            ->method('anonymize')
        ;

        $this->assertFalse($this->createDeletion([], [], [], memberExists: false)->clearMemberProfile(5));
    }

    public function testRegistrationsOfExistingEventsAreAnonymizedAndOfDeletedEventsDeleted(): void
    {
        $this->remover
            ->expects($this->once())
            ->method('remove')
            ->with([31, 32])
        ;

        $anonymized = [];

        $this->anonymizer
            ->method('anonymize')
            ->willReturnCallback(
                static function (int $id) use (&$anonymized): bool {
                    $anonymized[] = $id;

                    return true;
                },
            )
        ;

        $this->assertTrue($this->createDeletion([], [11, 12], [31, 32])->clearMemberProfile(5));
        $this->assertSame([11, 12], $anonymized);
    }

    public function testRegistrationsOfExistingEventsAreFoundByContaoMemberIdOrSacMemberId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('(r.contaoMemberId = ? OR (? > 0 AND r.sacMemberId = ?))'),
                    $this->stringContains('r.anonymized = 0'),
                    $this->stringContains('AND EXISTS (SELECT 1 FROM tl_calendar_events AS e WHERE e.id = r.eventId)'),
                ),
                [5, 123456, 123456],
            )
            ->willReturn(['11', '12'])
        ;

        $this->assertSame([11, 12], $this->createDeletionWithConnection($connection)->findRegistrationIdsOfExistingEvents(5, 123456));
    }

    public function testRegistrationsOfDeletedEventsAreFoundByContaoMemberIdOrSacMemberId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('(r.contaoMemberId = ? OR (? > 0 AND r.sacMemberId = ?))'),
                    $this->stringContains('NOT EXISTS (SELECT 1 FROM tl_calendar_events AS e WHERE e.id = r.eventId)'),
                ),
                [5, 123456, 123456],
            )
            ->willReturn(['31'])
        ;

        $this->assertSame([31], $this->createDeletionWithConnection($connection)->findRegistrationIdsOfDeletedEvents(5, 123456));
    }

    public function testNothingIsAnonymizedOrDeletedIfTheDeletionIsRefused(): void
    {
        $this->anonymizer
            ->expects($this->never())
            ->method('anonymize')
        ;

        $this->remover
            ->expects($this->never())
            ->method('remove')
        ;

        $deletion = $this->createDeletion([$this->upcoming(21, EventSubscriptionState::SUBSCRIPTION_ACCEPTED)], [11], [31]);

        $this->assertFalse($deletion->clearMemberProfile(5));
    }

    public function testRefusedIfOnTheBookingListOfAnUpcomingEvent(): void
    {
        $this->anonymizer
            ->expects($this->never())
            ->method('anonymize')
        ;

        $deletion = $this->createDeletion([$this->upcoming(21, EventSubscriptionState::SUBSCRIPTION_ACCEPTED)], [11]);

        $this->assertFalse($deletion->clearMemberProfile(5));
        $this->assertCount(1, $this->errors);
        $this->assertStringContainsString('Skitour Pilatus', $this->errors[0]);
    }

    public function testRefusedRegistrationOfAnUpcomingEventDoesNotBlock(): void
    {
        $deletion = $this->createDeletion([$this->upcoming(21, EventSubscriptionState::SUBSCRIPTION_REFUSED)], []);

        $this->assertTrue($deletion->clearMemberProfile(5));
        $this->assertSame([], $this->errors);
    }

    public function testForceIgnoresUpcomingEvents(): void
    {
        $this->anonymizer
            ->expects($this->once())
            ->method('anonymize')
            ->with(11)
        ;

        $deletion = $this->createDeletion([$this->upcoming(21, EventSubscriptionState::SUBSCRIPTION_ACCEPTED)], [11]);

        $this->assertTrue($deletion->clearMemberProfile(5, true));
        $this->assertSame([], $this->errors);
    }

    public function testDeleteMemberDeletesTheMemberAfterClearingTheProfile(): void
    {
        $this->member
            ->expects($this->once())
            ->method('delete')
        ;

        $this->assertTrue($this->createDeletion([], [])->deleteMember(5));
    }

    public function testDeleteMemberKeepsTheMemberIfTheProfileCannotBeCleared(): void
    {
        $this->member
            ->expects($this->never())
            ->method('delete')
        ;

        $deletion = $this->createDeletion([$this->upcoming(21, EventSubscriptionState::SUBSCRIPTION_ACCEPTED)], []);

        $this->assertFalse($deletion->deleteMember(5));
    }

    /**
     * @return array{registrationId: string, eventModel: CalendarEventsModel, state: string}
     */
    private function upcoming(int $registrationId, string $state): array
    {
        return [
            'registrationId' => (string) $registrationId,
            'eventModel' => $this->mockClassWithProperties(CalendarEventsModel::class, ['title' => 'Skitour Pilatus', 'startDate' => 1800000000]),
            'state' => $state,
        ];
    }

    /**
     * @param list<array<string, mixed>> $upcomingEvents
     * @param list<int>                  $registrationIdsOfExistingEvents
     * @param list<int>                  $registrationIdsOfDeletedEvents
     */
    private function createDeletion(array $upcomingEvents, array $registrationIdsOfExistingEvents, array $registrationIdsOfDeletedEvents = [], bool $memberExists = true): MemberProfileDeletion
    {
        $memberAdapter = $this->mockAdapter(['findById']);
        $memberAdapter
            ->method('findById')
            ->willReturn($memberExists ? $this->member : null)
        ;

        $registrations = [];

        foreach ($upcomingEvents as $upcomingEvent) {
            $registrations[(int) $upcomingEvent['registrationId']] = $this->mockClassWithProperties(CalendarEventsMemberModel::class, ['stateOfSubscription' => $upcomingEvent['state']]);
        }

        $registrationAdapter = $this->mockAdapter(['findUpcomingEventsByMemberId', 'findById']);
        $registrationAdapter
            ->method('findUpcomingEventsByMemberId')
            ->willReturn($upcomingEvents)
        ;

        $registrationAdapter
            ->method('findById')
            ->willReturnCallback(static fn ($id) => $registrations[(int) $id] ?? null)
        ;

        $messageAdapter = $this->mockAdapter(['addError']);
        $messageAdapter
            ->method('addError')
            ->willReturnCallback(
                function (string $error): void {
                    $this->errors[] = $error;
                },
            )
        ;

        $dateAdapter = $this->mockAdapter(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturn('15.01.2027')
        ;

        $configAdapter = $this->mockAdapter(['get']);
        $configAdapter
            ->method('get')
            ->willReturn('d.m.Y')
        ;

        $framework = $this->mockContaoFramework([
            MemberModel::class => $memberAdapter,
            CalendarEventsMemberModel::class => $registrationAdapter,
            Message::class => $messageAdapter,
            Date::class => $dateAdapter,
            Config::class => $configAdapter,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturnCallback(static fn (string $sql): array => array_map(
                strval(...),
                str_contains($sql, 'NOT EXISTS') ? $registrationIdsOfDeletedEvents : $registrationIdsOfExistingEvents,
            ))
        ;

        return $this->createDeletionWithConnection($connection, $framework);
    }

    private function createDeletionWithConnection(Connection $connection, ContaoFramework|null $framework = null): MemberProfileDeletion
    {
        // The avatar directory does not exist: Contao\Folder is not used
        return new MemberProfileDeletion(
            $framework ?? $this->mockContaoFramework(),
            $connection,
            $this->anonymizer,
            $this->remover,
            sys_get_temp_dir().'/'.uniqid('no_project_', true),
            'files/avatars',
        );
    }
}
