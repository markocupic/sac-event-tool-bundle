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
use Markocupic\SacEventToolBundle\Security\Voter\CalendarAccessVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class CalendarAccessVoterTest extends TestCase
{
    private const ATTRIBUTE = 'contao_dc.tl_calendar';

    /**
     * The "children" operation of tl_calendar_container checks a ReadAction
     * without an ID. Access to any calendar must be sufficient (Contao 5.7).
     */
    public function testReadWithoutIdChecksAccessToAnyCalendar(): void
    {
        $calls = [];

        $vote = $this->vote(new ReadAction('tl_calendar', ['pid' => 7]), $calls, ['calendars' => [3]]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $vote);
        $this->assertContains([SacEventToolPermissions::USER_CAN_EDIT_CALENDAR_CONTAINERS, 7], $calls);
        $this->assertContains([ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR, null], $calls);
    }

    public function testReadWithoutIdIsDeniedWithoutAnyCalendar(): void
    {
        $calls = [];

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new ReadAction('tl_calendar', ['pid' => 7]), $calls, ['calendars' => []]));
    }

    public function testReadWithIdChecksTheCalendar(): void
    {
        $calls = [];

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new ReadAction('tl_calendar', ['id' => 3, 'pid' => 7]), $calls, ['calendars' => [3]]));
        $this->assertContains([ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR, 3], $calls);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new ReadAction('tl_calendar', ['id' => 4, 'pid' => 7]), $calls, ['calendars' => [3]]));
    }

    public function testAccessIsDeniedWithoutAccessToTheCalendarContainer(): void
    {
        $calls = [];

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new ReadAction('tl_calendar', ['pid' => 8]), $calls, ['calendars' => [3]]));
    }

    public function testUpdateAndDeleteCheckTheCalendar(): void
    {
        $calls = [];

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new UpdateAction('tl_calendar', ['id' => 3, 'pid' => 7]), $calls, ['calendars' => [3]]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new UpdateAction('tl_calendar', ['id' => 4, 'pid' => 7]), $calls, ['calendars' => [3]]));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new DeleteAction('tl_calendar', ['id' => 3, 'pid' => 7]), $calls, ['calendars' => [3]]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new DeleteAction('tl_calendar', ['id' => 3, 'pid' => 7]), $calls, ['calendars' => [3], 'canDelete' => false]));
    }

    public function testCreateChecksTheCreatePermission(): void
    {
        $calls = [];

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new CreateAction('tl_calendar', ['pid' => 7]), $calls, ['calendars' => [3]]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new CreateAction('tl_calendar', ['pid' => 7]), $calls, ['calendars' => [3], 'canCreate' => false]));
    }

    /**
     * The user has access to the module and to calendar container 7.
     *
     * @param array<int, array{0: string, 1: mixed}>                          $calls
     * @param array{calendars: list<int>, canCreate?: bool, canDelete?: bool} $user
     */
    private function vote(CreateAction|DeleteAction|ReadAction|UpdateAction $action, array &$calls, array $user): int
    {
        $accessDecisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $accessDecisionManager
            ->method('decide')
            ->willReturnCallback(
                static function (TokenInterface $token, array $attributes, mixed $object = null) use (&$calls, $user): bool {
                    // Contao 5.3 passes the IDs as strings, Contao 5.7 as integers
                    $attribute = $attributes[0];
                    $object = null === $object ? null : (int) $object;
                    $calls[] = [$attribute, $object];

                    return match ($attribute) {
                        ContaoCalendarPermissions::USER_CAN_ACCESS_MODULE => true,
                        SacEventToolPermissions::USER_CAN_EDIT_CALENDAR_CONTAINERS => 7 === $object,
                        ContaoCalendarPermissions::USER_CAN_CREATE_CALENDARS => $user['canCreate'] ?? true,
                        ContaoCalendarPermissions::USER_CAN_DELETE_CALENDARS => $user['canDelete'] ?? true,
                        ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR => null === $object ? [] !== $user['calendars'] : \in_array($object, $user['calendars'], true),
                        default => false,
                    };
                },
            )
        ;

        return (new CalendarAccessVoter($accessDecisionManager))->vote($this->createMock(TokenInterface::class), $action, [self::ATTRIBUTE]);
    }
}
