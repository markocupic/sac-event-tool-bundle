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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventReminder\Cron;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Cron\EventReminderCron;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Messenger\Message\SendEventReminderMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The SQL query of findDueEventIds() needs a database and is not covered here.
 */
final class EventReminderCronTest extends TestCase
{
    public function testDispatchesOneMessagePerDueEvent(): void
    {
        $dispatched = [];

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->method('dispatch')
            ->willReturnCallback(
                static function (SendEventReminderMessage $message) use (&$dispatched): Envelope {
                    $dispatched[] = $message->getEventId();

                    return new Envelope($message);
                },
            )
        ;

        $cron = $this->getMockBuilder(EventReminderCron::class)
            ->setConstructorArgs([$this->createMock(Connection::class), $messageBus, null])
            ->onlyMethods(['findDueEventIds'])
            ->getMock()
        ;

        $cron
            ->method('findDueEventIds')
            ->willReturn([10, 11])
        ;

        $cron();

        $this->assertSame([10, 11], $dispatched);
    }
}
