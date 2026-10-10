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

namespace Markocupic\SacEventToolBundle\Tests\Controller\FrontendModule\EventRegistration;

use Contao\CalendarEventsModel;
use Contao\MemberModel;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\EventRegistrationEligibility;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\Exception\EventRegistrationException;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use PHPUnit\Framework\Attributes\DataProvider;

class EventRegistrationEligibilityTest extends ContaoTestCase
{
    private const int NOW = 1_700_000_000;

    private const int DAY = 86400;

    /**
     * @dataProvider ineligibleEventProvider
     *
     * @param array<string, mixed> $eventOverrides
     */
    #[DataProvider('ineligibleEventProvider')]
    public function testThrowsForIneligibleEvents(array $eventOverrides, string $expectedText, string $expectedLevel): void
    {
        $this->assertThrows($this->createEligibility(), $this->makeEvent($eventOverrides), $this->validMember(), $expectedText, $expectedLevel);
    }

    public static function ineligibleEventProvider(): iterable
    {
        yield 'not published' => [['published' => ''], 'ERR.evt_reg_eventNotPublishedYet', EventRegistrationException::LEVEL_ERROR];
        yield 'online registration disabled' => [['disableOnlineRegistration' => '1'], 'ERR.evt_reg_onlineRegDisabled', EventRegistrationException::LEVEL_INFO];
        yield 'event fully booked (state)' => [['eventState' => EventState::STATE_FULLY_BOOKED], 'ERR.evt_reg_eventFullyBooked', EventRegistrationException::LEVEL_INFO];
        yield 'event canceled' => [['eventState' => EventState::STATE_CANCELED], 'ERR.evt_reg_eventCanceled', EventRegistrationException::LEVEL_INFO];
        yield 'event rescheduled' => [['eventState' => EventState::STATE_RESCHEDULED], 'ERR.evt_reg_eventDeferred', EventRegistrationException::LEVEL_INFO];
        yield 'registration has not started yet' => [['setRegistrationPeriod' => '1', 'registrationStartDate' => self::NOW + 1000, 'registrationEndDate' => self::NOW + 100000], 'ERR.evt_reg_registrationPossibleOn', EventRegistrationException::LEVEL_INFO];
        yield 'registration deadline expired' => [['setRegistrationPeriod' => '1', 'registrationStartDate' => self::NOW - 100000, 'registrationEndDate' => self::NOW - 1000], 'ERR.evt_reg_registrationDeadlineExpired', EventRegistrationException::LEVEL_INFO];
        yield 'no registration period and less than 24h before start' => [['setRegistrationPeriod' => '', 'startDate' => self::NOW + 1000], 'ERR.evt_reg_registrationPossible24HoursBeforeEventStart', EventRegistrationException::LEVEL_INFO];
    }

    public function testThrowsWhenReleaseLevelPolicyIsMissing(): void
    {
        $this->assertThrows($this->createEligibility(policy: null), $this->makeEvent(), $this->validMember(), 'ERR.evt_reg_eventReleaseLevelPolicyDoesNotAllowRegistrations', EventRegistrationException::LEVEL_ERROR);
    }

    public function testThrowsWhenBookingDatesOverlap(): void
    {
        $this->assertThrows($this->createEligibility(bookingDatesOccupied: true), $this->makeEvent(), $this->validMember(), 'ERR.evt_reg_eventDateOverlapError', EventRegistrationException::LEVEL_INFO);
    }

    public function testThrowsWhenMainInstructorIsMissing(): void
    {
        $this->assertThrows($this->createEligibility(instructor: null), $this->makeEvent(), $this->validMember(), 'ERR.evt_reg_mainInstructorNotFound', EventRegistrationException::LEVEL_INFO);
    }

    public function testThrowsWhenMainInstructorEmailIsInvalid(): void
    {
        $instructor = $this->mockClassWithProperties(UserModel::class, ['id' => 5, 'email' => 'not-an-email']);

        $this->assertThrows($this->createEligibility(instructor: $instructor), $this->makeEvent(), $this->validMember(), 'ERR.evt_reg_mainInstructorsEmailAddrNotFound', EventRegistrationException::LEVEL_ERROR);
    }

    public function testThrowsWhenMemberEmailIsInvalid(): void
    {
        $member = $this->mockClassWithProperties(MemberModel::class, ['id' => 5, 'email' => 'invalid']);

        $this->assertThrows($this->createEligibility(), $this->makeEvent(), $member, 'ERR.evt_reg_membersEmailAddrNotFound', EventRegistrationException::LEVEL_INFO);
    }

    public function testRegistrationStartTimeOffsetIsAdded(): void
    {
        // Registration starts in 500 s, the offset delays it by another hour
        $event = $this->makeEvent(['setRegistrationPeriod' => '1', 'registrationStartDate' => self::NOW - 500, 'registrationEndDate' => self::NOW + 100000]);

        $this->assertThrows($this->createEligibility(regStartTimeOffset: 3600), $event, $this->validMember(), 'ERR.evt_reg_registrationPossibleOn', EventRegistrationException::LEVEL_INFO);
    }

    public function testPassesForAnEligibleEvent(): void
    {
        $this->createEligibility()->check($this->makeEvent(), $this->validMember());

        // No exception means the event is eligible for registration.
        $this->addToAssertionCount(1);
    }

    private function assertThrows(EventRegistrationEligibility $eligibility, CalendarEventsModel $event, MemberModel $member, string $expectedText, string $expectedLevel): void
    {
        try {
            $eligibility->check($event, $member);
            $this->fail(\sprintf('Expected an EventRegistrationException with text "%s".', $expectedText));
        } catch (EventRegistrationException $e) {
            $this->assertSame($expectedText, $e->getTranslatableText());
            $this->assertSame($expectedLevel, $e->getErrorLevel());
        }
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeEvent(array $overrides = []): CalendarEventsModel
    {
        return $this->mockClassWithProperties(CalendarEventsModel::class, array_merge([
            'id' => 1,
            'title' => 'Testevent',
            'published' => '1',
            'eventState' => '',
            'disableOnlineRegistration' => '',
            'setRegistrationPeriod' => '',
            'registrationStartDate' => 0,
            'registrationEndDate' => 0,
            'startDate' => self::NOW + 30 * self::DAY,
            'mainInstructor' => 5,
        ], $overrides));
    }

    private function validMember(): MemberModel
    {
        return $this->mockClassWithProperties(MemberModel::class, ['id' => 5, 'email' => 'member@example.com']);
    }

    /**
     * Builds an EventRegistrationEligibility whose time source is pinned to self::NOW.
     */
    private function createEligibility(EventReleaseLevelPolicyModel|false|null $policy = false, UserModel|false|null $instructor = false, bool $bookingDatesOccupied = false, int $regStartTimeOffset = 0): EventRegistrationEligibility
    {
        // false means "use a valid default"
        if (false === $policy) {
            $policy = $this->mockClassWithProperties(EventReleaseLevelPolicyModel::class, ['allowRegistration' => true]);
        }

        if (false === $instructor) {
            $instructor = $this->mockClassWithProperties(UserModel::class, ['id' => 5, 'email' => 'guide@example.com']);
        }

        $policyAdapter = $this->mockAdapter(['findOneByEventId']);
        $policyAdapter
            ->method('findOneByEventId')
            ->willReturn($policy)
        ;

        $userAdapter = $this->mockAdapter(['findById']);
        $userAdapter
            ->method('findById')
            ->willReturn($instructor)
        ;

        $framework = $this->mockContaoFramework([
            EventReleaseLevelPolicyModel::class => $policyAdapter,
            UserModel::class => $userAdapter,
        ]);

        $calendarEventsUtil = $this->createMock(CalendarEventsUtil::class);
        $calendarEventsUtil
            ->method('areBookingDatesOccupied')
            ->willReturn($bookingDatesOccupied)
        ;

        $eligibility = $this->getMockBuilder(EventRegistrationEligibility::class)
            ->setConstructorArgs([$calendarEventsUtil, $framework, $regStartTimeOffset])
            ->onlyMethods(['getCurrentTimestamp'])
            ->getMock()
        ;

        $eligibility
            ->method('getCurrentTimestamp')
            ->willReturn(self::NOW)
        ;

        return $eligibility;
    }
}
