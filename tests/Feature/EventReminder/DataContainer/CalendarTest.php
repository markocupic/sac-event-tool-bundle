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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventReminder\DataContainer;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventReminder\DataContainer\Calendar;
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase
{
    public function testOnlyNotificationsOfTypeEventReminderAreOffered(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('FROM tl_nc_notification WHERE type = ?'), ['event_reminder'])
            ->willReturn([
                ['id' => '4', 'title' => 'Event-Erinnerung Kurse'],
                ['id' => '7', 'title' => 'Event-Erinnerung Touren'],
            ])
        ;

        $this->assertSame(
            [4 => 'Event-Erinnerung Kurse', 7 => 'Event-Erinnerung Touren'],
            (new Calendar($connection))->getNotificationOptions(),
        );
    }
}
