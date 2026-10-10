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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventCompletionReminder\Task;

use Contao\CalendarEventsModel;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Task\ParticipationConfirmationTask;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ParticipationConfirmationTaskTest extends ContaoTestCase
{
    /**
     * @dataProvider supportsProvider
     */
    #[DataProvider('supportsProvider')]
    public function testSupports(string $eventType, bool $expected): void
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['eventType' => $eventType]);

        $this->assertSame($expected, $this->createTask(0, 0)->supports($event));
    }

    public static function supportsProvider(): iterable
    {
        yield 'tour' => ['tour', true];
        yield 'last minute tour' => ['lastMinuteTour', true];
        yield 'course' => ['course', true];
        yield 'general event' => ['generalEvent', true];
    }

    /**
     * "registrations": number of accepted or waiting-list registrations,
     * "confirmed": how many of them have hasParticipated = 1.
     *
     * @dataProvider isOpenProvider
     */
    #[DataProvider('isOpenProvider')]
    public function testIsOpen(int $registrations, int $confirmed, bool $expectedOpen): void
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 42, 'eventType' => 'course']);

        $this->assertSame($expectedOpen, $this->createTask($registrations, $confirmed)->isOpen($event));
    }

    public static function isOpenProvider(): iterable
    {
        yield 'no accepted or waiting-list registrations' => [0, 0, false];
        yield 'registrations, none confirmed' => [5, 0, true];
        yield 'one of several confirmed' => [5, 1, false];
        yield 'all confirmed' => [5, 5, false];
    }

    public function testCountsConfirmationsOfAcceptedAndWaitingListRegistrations(): void
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 42, 'eventType' => 'tour']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->stringContains('stateOfSubscription IN (?)'),
                [42, [EventSubscriptionState::SUBSCRIPTION_ACCEPTED, EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST]],
                [ParameterType::INTEGER, ArrayParameterType::STRING],
            )
            ->willReturn(['registrations' => 2, 'confirmed' => 0])
        ;

        $task = new ParticipationConfirmationTask($connection, $this->createMock(RouterInterface::class), $this->createMock(TranslatorInterface::class));

        $this->assertTrue($task->isOpen($event));
    }

    private function createTask(int $registrations, int $confirmed): ParticipationConfirmationTask
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['registrations' => (string) $registrations, 'confirmed' => (string) $confirmed])
        ;

        return new ParticipationConfirmationTask($connection, $this->createMock(RouterInterface::class), $this->createMock(TranslatorInterface::class));
    }
}
