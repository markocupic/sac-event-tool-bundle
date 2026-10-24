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

namespace Markocupic\SacEventToolBundle\Tests\Feature\AboSurvey;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyEligibility;
use PHPUnit\Framework\TestCase;

final class AboSurveyEligibilityTest extends TestCase
{
    public function testMemberWithoutSacMemberIdIsNotEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchAllAssociative')
        ;

        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertSame(AboSurveyEligibility::REASON_NO_SAC_MEMBER_ID, $eligibility->check(0, 2027));
        $this->assertSame(AboSurveyEligibility::REASON_NO_SAC_MEMBER_ID, $eligibility->check(-1, 2027, [1], [1]));
    }

    public function testMemberWithoutBackendUserIsNotEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('FROM tl_user WHERE disable = ? AND sacMemberId = ?'),
                    $this->stringContains("(start = '' OR start < ?) AND (stop = '' OR stop > ?)"),
                ),
                $this->callback(static fn (array $params): bool => 4 === \count($params) && 0 === $params[0] && 123456 === $params[1] && \is_int($params[2]) && $params[2] === $params[3]),
            )
            ->willReturn([])
        ;

        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertSame(AboSurveyEligibility::REASON_NO_BACKEND_USER, $eligibility->check(123456, 2027, [5], [42]));
    }

    public function testExplicitlyAllowedUserIsEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([['id' => '42', 'userRole' => null]])
        ;

        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertNull($eligibility->check(123456, 2027, [], ['7', '42']));
    }

    public function testUserWithAllowedRoleIsEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([['id' => '42', 'userRole' => serialize(['3', '5'])]])
        ;

        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertNull($eligibility->check(123456, 2027, ['5'], []));
    }

    public function testUserWithExecutiveBoardRoleIsEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([['id' => '42', 'userRole' => serialize(['3', '5'])]])
        ;

        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('belongsToExecutiveBoard = 1'),
                ['roleIds' => [3, 5]],
            )
            ->willReturn(5)
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertNull($eligibility->check(123456, 2027, ['9'], ['7']));
    }

    public function testUserWithoutRolesAndWithoutReleasedMainInstructorEventIsNotEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([['id' => '42', 'userRole' => null]])
        ;

        // No role: only the main instructor query is executed
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('i.isMainInstructor = 1'))
            ->willReturn(false)
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertSame(AboSurveyEligibility::REASON_NO_MAIN_INSTRUCTOR_EVENT, $eligibility->check(123456, 2027));
    }

    public function testUserWithRolesButWithoutAnyQualificationIsNotEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([['id' => '42', 'userRole' => serialize(['3'])]])
        ;

        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturn(false)
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertSame(AboSurveyEligibility::REASON_NO_MAIN_INSTRUCTOR_EVENT, $eligibility->check(123456, 2027, ['5'], ['7']));
    }

    public function testMainInstructorOfReleasedEventIsEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
            ['id' => '42', 'userRole' => null],
            ['id' => '43', 'userRole' => ''],
        ])
        ;

        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('i.isMainInstructor = 1'),
                    $this->stringContains('r.level >= :minLevel'),
                    $this->stringContains('e.eventType IN (:eventTypes)'),
                    $this->stringContains('e.startDate BETWEEN :start AND :end'),
                ),
                $this->callback(
                    static fn (array $params): bool => [42, 43] === $params['userIds']
                        && ['tour', 'course'] === $params['eventTypes']
                        && 3 === $params['minLevel']
                        && mktime(0, 0, 0, 1, 1, 2027) === $params['start']
                        && mktime(23, 59, 59, 12, 31, 2027) === $params['end'],
                ),
            )
            ->willReturn(1001)
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertNull($eligibility->check(123456, 2027));
    }
}
