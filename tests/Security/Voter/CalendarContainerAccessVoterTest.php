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

namespace Markocupic\SacEventToolBundle\Tests\Security\Voter;

use Contao\CalendarBundle\Security\ContaoCalendarPermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\CoreBundle\Security\DataContainer\DeleteAction;
use Contao\CoreBundle\Security\DataContainer\ReadAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
use Markocupic\SacEventToolBundle\Security\SacEventToolPermissions;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarContainerAccessVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class CalendarContainerAccessVoterTest extends TestCase
{
    private const ATTRIBUTE = 'contao_dc.tl_calendar_container';

    public function testReadIsOnlyGrantedForEnabledCalendarContainers(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new ReadAction('tl_calendar_container', ['id' => 7]), ['containers' => [7]]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new ReadAction('tl_calendar_container', ['id' => 8]), ['containers' => [7]]));
    }

    public function testReadWithoutIdChecksAccessToAnyCalendarContainer(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new ReadAction('tl_calendar_container', []), ['containers' => [7]]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new ReadAction('tl_calendar_container', []), ['containers' => []]));
    }

    public function testUpdateAndDeleteCheckTheCalendarContainer(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new UpdateAction('tl_calendar_container', ['id' => 7]), ['containers' => [7]]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new UpdateAction('tl_calendar_container', ['id' => 8]), ['containers' => [7]]));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new DeleteAction('tl_calendar_container', ['id' => 7]), ['containers' => [7]]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new DeleteAction('tl_calendar_container', ['id' => 7]), ['containers' => [7], 'canDelete' => false]));
    }

    public function testCreateChecksTheCreatePermission(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new CreateAction('tl_calendar_container'), ['containers' => []]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new CreateAction('tl_calendar_container'), ['containers' => [7], 'canCreate' => false]));
    }

    public function testAccessIsDeniedWithoutModuleAccess(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new ReadAction('tl_calendar_container', ['id' => 7]), ['containers' => [7], 'canAccessModule' => false]));
    }

    /**
     * @param array{containers: list<int>, canAccessModule?: bool, canCreate?: bool, canDelete?: bool} $user
     */
    private function vote(CreateAction|DeleteAction|ReadAction|UpdateAction $action, array $user): int
    {
        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager
            ->method('decide')
            ->willReturnCallback(
                static fn (TokenInterface $token, array $attributes, mixed $object = null): bool => match ($attributes[0]) {
                    ContaoCalendarPermissions::USER_CAN_ACCESS_MODULE => $user['canAccessModule'] ?? true,
                    SacEventToolPermissions::USER_CAN_CREATE_CALENDAR_CONTAINERS => $user['canCreate'] ?? true,
                    SacEventToolPermissions::USER_CAN_DELETE_CALENDAR_CONTAINERS => $user['canDelete'] ?? true,
                    SacEventToolPermissions::USER_CAN_EDIT_CALENDAR_CONTAINERS => null === $object ? [] !== $user['containers'] : \in_array((int) $object, $user['containers'], true),
                    default => false,
                },
            )
        ;

        return (new CalendarContainerAccessVoter($accessDecisionManager))->vote($this->createMock(TokenInterface::class), $action, [self::ATTRIBUTE]);
    }
}
