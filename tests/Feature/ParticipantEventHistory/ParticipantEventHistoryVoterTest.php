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

namespace Markocupic\SacEventToolBundle\Tests\Feature\ParticipantEventHistory;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\Security\ParticipantEventHistoryVoter;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ParticipantEventHistoryVoterTest extends ContaoTestCase
{
    private const ATTRIBUTE = ParticipantEventHistoryVoter::CAN_VIEW_PARTICIPANT_EVENT_HISTORY_OF_REGISTRATION;

    public function testAbstainsOnOtherAttributes(): void
    {
        $voter = $this->createVoter(isAdmin: true, hasPermission: true);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->createToken(), 1, ['sacevt_can_write_event']));
    }

    public function testGrantsAccessToAdminsAfterTheAccessPeriod(): void
    {
        $voter = $this->createVoter(isAdmin: true, hasPermission: false, eventEndDate: strtotime('-1 year'));

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->createToken(), 1, [self::ATTRIBUTE]));
    }

    public function testGrantsAccessWithPermissionWithinTheAccessPeriod(): void
    {
        $voter = $this->createVoter(isAdmin: false, hasPermission: true, eventEndDate: strtotime('-1 day'));

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->createToken(), 1, [self::ATTRIBUTE]));
    }

    public function testDeniesAccessWithoutPermission(): void
    {
        $voter = $this->createVoter(isAdmin: false, hasPermission: false, eventEndDate: strtotime('-1 day'));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->createToken(), 1, [self::ATTRIBUTE]));
    }

    public function testDeniesAccessAfterTheAccessPeriod(): void
    {
        $voter = $this->createVoter(isAdmin: false, hasPermission: true, eventEndDate: strtotime('-60 days'));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->createToken(), 1, [self::ATTRIBUTE]));
    }

    public function testDeniesAccessWithoutSacMemberId(): void
    {
        $voter = $this->createVoter(isAdmin: true, hasPermission: true, sacMemberId: 0);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->createToken(), 1, [self::ATTRIBUTE]));
    }

    public function testDeniesAccessToUnknownRegistrations(): void
    {
        $voter = $this->createVoter(isAdmin: true, hasPermission: true, registrationExists: false);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->createToken(), 1, [self::ATTRIBUTE]));
    }

    public function testDeniesAccessWithoutBackendUser(): void
    {
        $voter = $this->createVoter(isAdmin: true, hasPermission: true);
        $token = $this->createMock(TokenInterface::class);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, 1, [self::ATTRIBUTE]));
    }

    private function createVoter(bool $isAdmin, bool $hasPermission, int|null $eventEndDate = null, int $sacMemberId = 123456, bool $registrationExists = true): ParticipantEventHistoryVoter
    {
        $eventEndDate ??= time();

        $registration = $this->mockClassWithProperties(CalendarEventsMemberModel::class, [
            'id' => 1,
            'eventId' => 5,
            'sacMemberId' => $sacMemberId,
        ]);

        $event = $this->mockClassWithProperties(CalendarEventsModel::class, [
            'id' => 5,
            'startDate' => $eventEndDate - 3600,
            'endDate' => $eventEndDate,
            'eventState' => '',
            'rescheduledEventDate' => null,
        ]);

        $framework = $this->mockContaoFramework([
            CalendarEventsMemberModel::class => $this->mockConfiguredAdapter(['findById' => $registrationExists ? $registration : null]),
            CalendarEventsModel::class => $this->mockConfiguredAdapter(['findById' => $event]),
        ]);

        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager
            ->method('decide')
            ->willReturnCallback(
                static fn (TokenInterface $token, array $attributes): bool => match ($attributes[0]) {
                    'ROLE_ADMIN' => $isAdmin,
                    CalendarEventsVoter::CAN_VIEW_PARTICIPANT_EVENT_HISTORY => $hasPermission,
                    default => false,
                },
            )
        ;

        return new ParticipantEventHistoryVoter($accessDecisionManager, $framework);
    }

    private function createToken(): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($this->createMock(BackendUser::class))
        ;

        return $token;
    }
}
