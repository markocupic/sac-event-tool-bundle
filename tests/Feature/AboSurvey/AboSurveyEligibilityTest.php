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
use Markocupic\SacEventToolBundle\Config\TourguideQualification;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyEligibility;
use PHPUnit\Framework\TestCase;

final class AboSurveyEligibilityTest extends TestCase
{
    public function testMemberWithoutSacMemberIdIsNotEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchAssociative')
        ;

        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertSame(AboSurveyEligibility::REASON_NO_SAC_MEMBER_ID, $eligibility->check(0, self::periodStart(), self::periodEnd()));
        $this->assertSame(AboSurveyEligibility::REASON_NO_SAC_MEMBER_ID, $eligibility->check(-1, self::periodStart(), self::periodEnd(), [1], [1]));
    }

    public function testMemberWithoutBackendUserIsNotEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('SELECT id, userRole, leiterQualifikation FROM tl_user WHERE disable = ? AND sacMemberId = ?'),
                    $this->stringContains("(start = '' OR start < ?) AND (stop = '' OR stop > ?)"),
                ),
                $this->callback(static fn (array $params): bool => 4 === \count($params) && 0 === $params[0] && 123456 === $params[1] && \is_int($params[2]) && $params[2] === $params[3]),
            )
            ->willReturn(false)
        ;

        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertSame(AboSurveyEligibility::REASON_NO_BACKEND_USER, $eligibility->check(123456, self::periodStart(), self::periodEnd(), [5], [42]));
    }

    public function testMountainGuideIsNeverEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => '42', 'userRole' => serialize(['5']), 'leiterQualifikation' => serialize(['1', (string) TourguideQualification::MOUNTAIN_GUIDE])])
        ;

        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $eligibility = new AboSurveyEligibility($connection);

        // Even if the user and his role are explicitly allowed
        $this->assertSame(AboSurveyEligibility::REASON_MOUNTAIN_GUIDE, $eligibility->check(123456, self::periodStart(), self::periodEnd(), ['5'], ['42']));
    }

    public function testOtherQualificationsDoNotExclude(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => '42', 'userRole' => null, 'leiterQualifikation' => serialize([(string) TourguideQualification::TOURGUIDE_SAC])])
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertNull($eligibility->check(123456, self::periodStart(), self::periodEnd(), [], ['42']));
    }

    public function testExplicitlyAllowedUserIsEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => '42', 'userRole' => null])
        ;

        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertNull($eligibility->check(123456, self::periodStart(), self::periodEnd(), [], ['7', '42']));
    }

    public function testUserWithAllowedRoleIsEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => '42', 'userRole' => serialize(['3', '5'])])
        ;

        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertNull($eligibility->check(123456, self::periodStart(), self::periodEnd(), ['5'], []));
    }

    public function testUserWithExecutiveBoardRoleIsEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => '42', 'userRole' => serialize(['3', '5'])])
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

        $this->assertNull($eligibility->check(123456, self::periodStart(), self::periodEnd(), ['9'], ['7']));
    }

    public function testUserWithoutRolesAndWithoutReleasedInstructorEventIsNotEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => '42', 'userRole' => null])
        ;

        // No role: only the instructor query is executed
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('INNER JOIN tl_calendar_events_instructor i'))
            ->willReturn(false)
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertSame(AboSurveyEligibility::REASON_NO_INSTRUCTOR_EVENT, $eligibility->check(123456, self::periodStart(), self::periodEnd()));
    }

    public function testUserWithRolesButWithoutAnyQualificationIsNotEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => '42', 'userRole' => serialize(['3'])])
        ;

        $connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturn(false)
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertSame(AboSurveyEligibility::REASON_NO_INSTRUCTOR_EVENT, $eligibility->check(123456, self::periodStart(), self::periodEnd(), ['5'], ['7']));
    }

    public function testInstructorOfReleasedEventIsEligible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => '42', 'userRole' => null])
        ;

        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('WHERE i.userId = :userId'),
                    $this->logicalNot($this->stringContains('isMainInstructor')),
                    $this->stringContains('r.level >= :minLevel'),
                    $this->stringContains('e.eventType IN (:eventTypes)'),
                    $this->stringContains('e.startDate BETWEEN :start AND :end'),
                ),
                $this->callback(
                    static fn (array $params): bool => 42 === $params['userId']
                        && ['tour', 'course'] === $params['eventTypes']
                        && 3 === $params['minLevel']
                        && self::periodStart() === $params['start']
                        && self::periodEnd() === $params['end'],
                ),
            )
            ->willReturn(1001)
        ;

        $eligibility = new AboSurveyEligibility($connection);

        $this->assertNull($eligibility->check(123456, self::periodStart(), self::periodEnd()));
    }

    /**
     * Event period of the 2027 programme incl. December 2026.
     */
    private static function periodStart(): int
    {
        return mktime(0, 0, 0, 12, 1, 2026);
    }

    private static function periodEnd(): int
    {
        return mktime(23, 59, 59, 12, 31, 2027);
    }
}
