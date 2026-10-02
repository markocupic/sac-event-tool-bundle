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

namespace Markocupic\SacEventToolBundle\Tests\Feature\InstructorPostEventTaskReminder\Task;

use Contao\CalendarEventsModel;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\Task\ParticipationConfirmationTask;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ParticipationConfirmationTaskTest extends ContaoTestCase
{
    /**
     * @dataProvider supportsProvider
     */
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
        yield 'general event' => ['generalEvent', false];
    }

    /**
     * The query only counts ACCEPTED registrations, so a hasParticipated = 1
     * on a waiting-list registration results in "confirmed = 0" here.
     *
     * @dataProvider isOpenProvider
     */
    public function testIsOpen(int $accepted, int $confirmed, bool $expectedOpen): void
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 42, 'eventType' => 'course']);

        $this->assertSame($expectedOpen, $this->createTask($accepted, $confirmed)->isOpen($event));
    }

    public static function isOpenProvider(): iterable
    {
        yield 'no accepted participants' => [0, 0, false];
        yield 'accepted participants, none confirmed' => [5, 0, true];
        yield 'one of several confirmed' => [5, 1, false];
        yield 'all confirmed' => [5, 5, false];
    }

    public function testQueriesOnlyAcceptedRegistrationsOfTheEvent(): void
    {
        $event = $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 42, 'eventType' => 'tour']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->stringContains('stateOfSubscription = ?'),
                [42, EventSubscriptionState::SUBSCRIPTION_ACCEPTED],
            )
            ->willReturn(['accepted' => 2, 'confirmed' => 0])
        ;

        $task = new ParticipationConfirmationTask($connection, $this->createMock(RouterInterface::class), $this->createMock(TranslatorInterface::class));

        $this->assertTrue($task->isOpen($event));
    }

    private function createTask(int $accepted, int $confirmed): ParticipationConfirmationTask
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['accepted' => (string) $accepted, 'confirmed' => (string) $confirmed])
        ;

        return new ParticipationConfirmationTask($connection, $this->createMock(RouterInterface::class), $this->createMock(TranslatorInterface::class));
    }
}
