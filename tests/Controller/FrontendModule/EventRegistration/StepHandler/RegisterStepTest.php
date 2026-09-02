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

namespace Markocupic\SacEventToolBundle\Tests\Controller\FrontendModule\EventRegistration\StepHandler;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\CarSeatInfo;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\TicketInfo;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler\RegisterStep;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\Exception\EventRegistrationException;
use Markocupic\SacEventToolBundle\Database\SyncEventRegistrationDatabase;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterStepTest extends ContaoTestCase
{
    private const int NOW = 1_700_000_000;

    private const int DAY = 86400;

    public function testStaticMetaData(): void
    {
        $this->assertSame('register', RegisterStep::getName());
        $this->assertSame(200, RegisterStep::getPriority());
        $this->assertStringContainsString('register.html.twig', $this->createStep()->getTemplateName());
    }

    public function testValidateReturnsFalseWhenNobodyIsLoggedIn(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn(null)
        ;

        $step = $this->createStep(security: $security);

        $this->assertFalse($step->validate($this->makeEvent(), $this->request(), $this->createMock(ModuleModel::class)));
    }

    public function testValidateReturnsFalseWhenTheMemberCannotBeResolved(): void
    {
        $memberAdapter = $this->mockAdapter(['findById']);
        $memberAdapter
            ->method('findById')
            ->willReturn(null)
        ;

        $step = $this->createStep(
            framework: $this->mockContaoFramework([MemberModel::class => $memberAdapter]),
            security: $this->mockSecurityWithFrontendUser(),
        );

        // Guards the null-pointer bug: an authenticated user without a member record must not fatal.
        $this->assertFalse($step->validate($this->makeEvent(), $this->request(), $this->createMock(ModuleModel::class)));
    }

    /**
     * @dataProvider ineligibleEventProvider
     *
     * @param array<string, mixed> $eventOverrides
     */
    public function testValidateEventRegistrationEligibilityThrowsForIneligibleEvents(array $eventOverrides, string $expectedText, string $expectedLevel): void
    {
        $step = $this->createStep();

        $this->assertEligibilityThrows(
            $step,
            $this->makeEvent($eventOverrides),
            $this->validMember(),
            $this->validInstructor(),
            $expectedText,
            $expectedLevel,
        );
    }

    public static function ineligibleEventProvider(): iterable
    {
        yield 'not published' => [
            ['published' => ''],
            'ERR.evt_reg_eventNotPublishedYet',
            EventRegistrationException::LEVEL_ERROR,
        ];

        yield 'online registration disabled' => [
            ['disableOnlineRegistration' => '1'],
            'ERR.evt_reg_onlineRegDisabled',
            EventRegistrationException::LEVEL_INFO,
        ];

        yield 'event fully booked (state)' => [
            ['eventState' => EventState::STATE_FULLY_BOOKED],
            'ERR.evt_reg_eventFullyBooked',
            EventRegistrationException::LEVEL_INFO,
        ];

        yield 'event canceled' => [
            ['eventState' => EventState::STATE_CANCELED],
            'ERR.evt_reg_eventCanceled',
            EventRegistrationException::LEVEL_INFO,
        ];

        yield 'event rescheduled' => [
            ['eventState' => EventState::STATE_RESCHEDULED],
            'ERR.evt_reg_eventDeferred',
            EventRegistrationException::LEVEL_INFO,
        ];

        yield 'registration has not started yet' => [
            ['setRegistrationPeriod' => '1', 'registrationStartDate' => self::NOW + 1000, 'registrationEndDate' => self::NOW + 100000],
            'ERR.evt_reg_registrationPossibleOn',
            EventRegistrationException::LEVEL_INFO,
        ];

        yield 'registration deadline expired' => [
            ['setRegistrationPeriod' => '1', 'registrationStartDate' => self::NOW - 100000, 'registrationEndDate' => self::NOW - 1000],
            'ERR.evt_reg_registrationDeadlineExpired',
            EventRegistrationException::LEVEL_INFO,
        ];

        yield 'no registration period and less than 24h before start' => [
            ['setRegistrationPeriod' => '', 'startDate' => self::NOW + 1000],
            'ERR.evt_reg_registrationPossible24HoursBeforeEventStart',
            EventRegistrationException::LEVEL_INFO,
        ];
    }

    public function testValidateEventRegistrationEligibilityThrowsWhenReleaseLevelPolicyIsMissing(): void
    {
        $step = $this->createStep(policy: null);

        $this->assertEligibilityThrows(
            $step,
            $this->makeEvent(),
            $this->validMember(),
            $this->validInstructor(),
            'ERR.evt_reg_eventReleaseLevelPolicyDoesNotAllowRegistrations',
            EventRegistrationException::LEVEL_ERROR,
        );
    }

    public function testValidateEventRegistrationEligibilityThrowsWhenBookingDatesOverlap(): void
    {
        $util = $this->mockUtil(areBookingDatesOccupied: true);

        $step = $this->createStep(util: $util);

        $this->assertEligibilityThrows(
            $step,
            $this->makeEvent(),
            $this->validMember(),
            $this->validInstructor(),
            'ERR.evt_reg_eventDateOverlapError',
            EventRegistrationException::LEVEL_INFO,
        );
    }

    public function testValidateEventRegistrationEligibilityThrowsWhenMainInstructorIsMissing(): void
    {
        $step = $this->createStep();

        $this->assertEligibilityThrows(
            $step,
            $this->makeEvent(),
            $this->validMember(),
            null,
            'ERR.evt_reg_mainInstructorNotFound',
            EventRegistrationException::LEVEL_INFO,
        );
    }

    public function testValidateEventRegistrationEligibilityThrowsWhenMainInstructorEmailIsInvalid(): void
    {
        $step = $this->createStep();

        $this->assertEligibilityThrows(
            $step,
            $this->makeEvent(),
            $this->validMember(),
            $this->mockClassWithProperties(UserModel::class, ['id' => 5, 'email' => 'not-an-email']),
            'ERR.evt_reg_mainInstructorsEmailAddrNotFound',
            EventRegistrationException::LEVEL_ERROR,
        );
    }

    public function testValidateEventRegistrationEligibilityThrowsWhenMemberEmailIsInvalid(): void
    {
        $step = $this->createStep();

        $this->assertEligibilityThrows(
            $step,
            $this->makeEvent(),
            $this->mockClassWithProperties(MemberModel::class, ['id' => 5, 'email' => 'invalid']),
            $this->validInstructor(),
            'ERR.evt_reg_membersEmailAddrNotFound',
            EventRegistrationException::LEVEL_INFO,
        );
    }

    public function testValidateEventRegistrationEligibilityPassesForAnEligibleEvent(): void
    {
        $step = $this->createStep();

        $this->invokeEligibility($step, $this->makeEvent(), $this->validMember(), $this->validInstructor());

        // No exception means the event is eligible for registration.
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider subscriptionStateProvider
     *
     * @param array<string, mixed> $eventOverrides
     */
    public function testResolveSubscriptionsState(array $eventOverrides, bool $fullyBooked, string $expectedState): void
    {
        $step = $this->createStep(util: $this->mockUtil(eventIsFullyBooked: $fullyBooked));

        $method = new \ReflectionMethod(RegisterStep::class, 'resolveSubscriptionsState');
        $method->setAccessible(true);

        $this->assertSame($expectedState, $method->invoke($step, $this->makeEvent($eventOverrides)));
    }

    public static function subscriptionStateProvider(): iterable
    {
        yield 'fully booked goes to the waiting list' => [
            ['autoConfirm' => '1', 'addIban' => ''],
            true,
            EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST,
        ];

        yield 'no auto-confirm stays not confirmed' => [
            ['autoConfirm' => '', 'addIban' => ''],
            false,
            EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED,
        ];

        yield 'auto-confirm with iban stays not confirmed' => [
            ['autoConfirm' => '1', 'addIban' => '1'],
            false,
            EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED,
        ];

        yield 'auto-confirm without iban is accepted' => [
            ['autoConfirm' => '1', 'addIban' => ''],
            false,
            EventSubscriptionState::SUBSCRIPTION_ACCEPTED,
        ];
    }

    private function assertEligibilityThrows(RegisterStep $step, CalendarEventsModel $event, MemberModel $member, UserModel|null $instructor, string $expectedText, string $expectedLevel): void
    {
        try {
            $this->invokeEligibility($step, $event, $member, $instructor);
            $this->fail(\sprintf('Expected an EventRegistrationException with text "%s".', $expectedText));
        } catch (EventRegistrationException $e) {
            $this->assertSame($expectedText, $e->getTranslatableText());
            $this->assertSame($expectedLevel, $e->getErrorLevel());
        }
    }

    private function invokeEligibility(RegisterStep $step, CalendarEventsModel $event, MemberModel $member, UserModel|null $instructor): void
    {
        $method = new \ReflectionMethod(RegisterStep::class, 'validateEventRegistrationEligibility');
        $method->setAccessible(true);
        $method->invoke($step, $event, $member, $instructor, ['regStartTimeOffset' => 0]);
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
            'journey' => 0,
            'autoConfirm' => '',
            'addIban' => '',
        ], $overrides));
    }

    private function validMember(): MemberModel
    {
        return $this->mockClassWithProperties(MemberModel::class, ['id' => 5, 'email' => 'member@example.com']);
    }

    private function validInstructor(): UserModel
    {
        return $this->mockClassWithProperties(UserModel::class, ['id' => 5, 'email' => 'guide@example.com']);
    }

    private function mockSecurityWithFrontendUser(): Security&MockObject
    {
        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn($this->mockClassWithProperties(FrontendUser::class, ['id' => 5]))
        ;

        return $security;
    }

    private function mockUtil(bool $areBookingDatesOccupied = false, bool $eventIsFullyBooked = false): CalendarEventsUtil
    {
        // CalendarEventsUtil is readonly and cannot be mocked by PHPUnit, so we use
        // a readonly test double returning canned values (see FakeCalendarEventsUtil).
        return new FakeCalendarEventsUtil(
            $this->mockContaoFramework(),
            $areBookingDatesOccupied,
            $eventIsFullyBooked,
        );
    }

    private function request(): Request
    {
        return new Request();
    }

    /**
     * Builds a RegisterStep whose time source is pinned to self::NOW.
     */
    private function createStep(CalendarEventsUtil|null $util = null, ContaoFramework|null $framework = null, Security|null $security = null, EventReleaseLevelPolicyModel|false|null $policy = false): RegisterStep
    {
        $util ??= $this->mockUtil();

        // $policy === false means "use a valid default policy".
        if (false === $policy) {
            $policy = $this->mockClassWithProperties(EventReleaseLevelPolicyModel::class, ['allowRegistration' => true]);
        }

        if (null === $framework) {
            $policyAdapter = $this->mockAdapter(['findOneByEventId']);
            $policyAdapter
                ->method('findOneByEventId')
                ->willReturn($policy)
            ;
            $framework = $this->mockContaoFramework([EventReleaseLevelPolicyModel::class => $policyAdapter]);
        }

        $constructorArgs = [
            $util,
            $this->createMock(CarSeatInfo::class),
            $this->createMock(Connection::class),
            $framework,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InsertTagParser::class),
            $this->createMock(LockFactory::class),
            $security ?? $this->createMock(Security::class),
            $this->createMock(SyncEventRegistrationDatabase::class),
            $this->createMock(TicketInfo::class),
            $this->createMock(TranslatorInterface::class),
            0,
            null,
            null,
        ];

        $step = $this->getMockBuilder(RegisterStep::class)
            ->setConstructorArgs($constructorArgs)
            ->onlyMethods(['getCurrentTimestamp'])
            ->getMock()
        ;

        $step
            ->method('getCurrentTimestamp')
            ->willReturn(self::NOW)
        ;

        return $step;
    }
}

/**
 * Test double for the readonly CalendarEventsUtil.
 *
 * PHPUnit cannot mock a readonly class (the generated subclass would not be
 * readonly), so this readonly subclass returns canned values for the two
 * methods RegisterStep relies on.
 */
readonly class FakeCalendarEventsUtil extends CalendarEventsUtil
{
    public function __construct(
        ContaoFramework $framework,
        private bool $bookingDatesOccupied = false,
        private bool $fullyBooked = false,
    ) {
        parent::__construct($framework);
    }

    public function areBookingDatesOccupied(CalendarEventsModel $objEvent, MemberModel $objMember): bool
    {
        return $this->bookingDatesOccupied;
    }

    public function eventIsFullyBooked(CalendarEventsModel $objEvent): bool
    {
        return $this->fullyBooked;
    }
}
