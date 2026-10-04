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
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\EventRegistrationCreator;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationDatabaseSync\SyncEventRegistrationDatabase;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

class EventRegistrationCreatorTest extends ContaoTestCase
{
    private const int NOW = 1_700_000_000;

    /**
     * @dataProvider subscriptionStateProvider
     *
     * @param array<string, mixed> $eventOverrides
     */
    public function testResolveSubscriptionState(array $eventOverrides, bool $fullyBooked, string $expectedState): void
    {
        $creator = $this->createCreator(fullyBooked: $fullyBooked);

        $this->assertSame($expectedState, $creator->resolveSubscriptionState($this->makeEvent($eventOverrides)));
    }

    public static function subscriptionStateProvider(): iterable
    {
        yield 'fully booked goes to the waiting list' => [['autoConfirm' => '1', 'addIban' => ''], true, EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST];
        yield 'no auto-confirm stays not confirmed' => [['autoConfirm' => '', 'addIban' => ''], false, EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED];
        yield 'auto-confirm with iban stays not confirmed' => [['autoConfirm' => '1', 'addIban' => '1'], false, EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED];
        yield 'auto-confirm without iban is accepted' => [['autoConfirm' => '1', 'addIban' => ''], false, EventSubscriptionState::SUBSCRIPTION_ACCEPTED];
    }

    public function testRegistrationData(): void
    {
        $member = $this->createMock(MemberModel::class);
        $member
            ->method('row')
            ->willReturn(['id' => 5, 'firstname' => 'Anna', 'sacMemberId' => 123456, 'ahvNumber' => '756.1234.5678.97'])
        ;

        $member
            ->method('__get')
            ->willReturnMap([['id', 5], ['sectionId', 'a:1:{i:0;s:4:"4250";}']])
        ;

        $data = $this->createCreator()->getRegistrationData($this->makeEvent(), $member, ['notes' => 'Hallo']);

        $this->assertArrayNotHasKey('id', $data);
        // The AHV number is only stored if it was asked for in the form
        $this->assertArrayNotHasKey('ahvNumber', $data);
        $this->assertSame('Anna', $data['firstname']);
        $this->assertSame(123456, $data['sacMemberId']);
        $this->assertSame('Hallo', $data['notes']);
        $this->assertSame(5, $data['contaoMemberId']);
        $this->assertSame(1, $data['eventId']);
        $this->assertSame('Testevent', $data['eventName']);
        $this->assertSame(self::NOW, $data['dateAdded']);
        $this->assertSame('uuid-1', $data['uuid']);
        $this->assertSame(BookingType::ONLINE_FORM, $data['bookingType']);
        $this->assertSame(EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED, $data['stateOfSubscription']);
    }

    public function testMemberProfileIsSavedOnlyOnce(): void
    {
        $member = $this->createMock(MemberModel::class);
        $member
            ->method('isModified')
            ->willReturn(true)
        ;

        $member
            ->expects($this->once())
            ->method('save')
        ;

        $this->createCreator()->updateMemberProfile($member, ['emergencyPhone' => '079 111 11 11', 'emergencyPhoneName' => 'Beat', 'ahvNumber' => '756.1234.5678.97', 'foodHabits' => 'vegan']);
    }

    public function testMemberProfileIsNotSavedWithoutChanges(): void
    {
        $member = $this->createMock(MemberModel::class);
        $member
            ->method('isModified')
            ->willReturn(false)
        ;

        $member
            ->expects($this->never())
            ->method('save')
        ;

        $this->createCreator()->updateMemberProfile($member, []);
    }

    public function testExistingRegistrationIsReturnedWithoutSavingAgain(): void
    {
        $existing = $this->createMock(CalendarEventsMemberModel::class);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('transactional')
        ;

        $lock = $this->createMock(SharedLockInterface::class);
        $lock
            ->expects($this->once())
            ->method('acquire')
            ->with(true)
        ;

        $lock
            ->expects($this->once())
            ->method('release')
        ;

        $creator = $this->createCreator(connection: $connection, lock: $lock, existingRegistration: $existing);

        $this->assertSame($existing, $creator->create($this->makeEvent(), $this->makeMember(), []));
    }

    public function testCreatesTheRegistrationWithinTheLock(): void
    {
        $registration = $this->createMock(CalendarEventsMemberModel::class);
        $registration
            ->method('__get')
            ->willReturnMap([['id', 77]])
        ;

        $registration
            ->expects($this->once())
            ->method('save')
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (\Closure $func) => $func())
        ;

        $lock = $this->createMock(SharedLockInterface::class);
        $lock
            ->expects($this->once())
            ->method('acquire')
            ->with(true)
        ;

        $lock
            ->expects($this->once())
            ->method('release')
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('New Registration from "Anna Muster [ID: 5]" for event with ID: 1 ("Testevent").')
        ;

        $creator = $this->createCreator(connection: $connection, lock: $lock, logger: $logger, newRegistration: $registration);

        $this->assertSame($registration, $creator->create($this->makeEvent(), $this->makeMember(), ['emergencyPhone' => '079', 'emergencyPhoneName' => 'Beat']));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeEvent(array $overrides = []): CalendarEventsModel
    {
        return $this->mockClassWithProperties(CalendarEventsModel::class, array_merge([
            'id' => 1,
            'title' => 'Testevent',
            'autoConfirm' => '',
            'addIban' => '',
        ], $overrides));
    }

    private function makeMember(): MemberModel&MockObject
    {
        $member = $this->createMock(MemberModel::class);
        $member
            ->method('__get')
            ->willReturnMap([['id', 5], ['sectionId', ''], ['firstname', 'Anna'], ['lastname', 'Muster']])
        ;

        $member
            ->method('row')
            ->willReturn(['id' => 5])
        ;

        return $member;
    }

    private function createCreator(bool $fullyBooked = false, Connection|null $connection = null, SharedLockInterface|null $lock = null, LoggerInterface|null $logger = null, CalendarEventsMemberModel|null $existingRegistration = null, CalendarEventsMemberModel|null $newRegistration = null): EventRegistrationCreator
    {
        $registrationAdapter = $this->mockAdapter(['findByMemberAndEvent']);
        $registrationAdapter
            ->method('findByMemberAndEvent')
            ->willReturn($existingRegistration)
        ;

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory
            ->method('createLock')
            ->willReturn($lock ?? $this->createMock(SharedLockInterface::class))
        ;

        $calendarEventsUtil = $this->createMock(CalendarEventsUtil::class);
        $calendarEventsUtil
            ->method('eventIsFullyBooked')
            ->willReturn($fullyBooked)
        ;

        $creator = $this->getMockBuilder(EventRegistrationCreator::class)
            ->setConstructorArgs([
                $calendarEventsUtil,
                $connection ?? $this->createMock(Connection::class),
                $this->mockContaoFramework([CalendarEventsMemberModel::class => $registrationAdapter]),
                $lockFactory,
                $this->createMock(Security::class),
                $this->createMock(SyncEventRegistrationDatabase::class),
                $logger,
            ])
            ->onlyMethods(['getCurrentTimestamp', 'generateUuid', 'createRegistrationModel'])
            ->getMock()
        ;

        $creator
            ->method('getCurrentTimestamp')
            ->willReturn(self::NOW)
        ;

        $creator
            ->method('generateUuid')
            ->willReturn('uuid-1')
        ;

        $creator
            ->method('createRegistrationModel')
            ->willReturn($newRegistration ?? $this->createMock(CalendarEventsMemberModel::class))
        ;

        return $creator;
    }
}
