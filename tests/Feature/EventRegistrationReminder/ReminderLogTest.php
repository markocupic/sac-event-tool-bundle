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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventRegistrationReminder;

use Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\ReminderLog;
use PHPUnit\Framework\TestCase;

final class ReminderLogTest extends TestCase
{
    public function testIsReminderDue(): void
    {
        $now = strtotime('2026-10-08 04:30:00');

        $this->assertTrue(ReminderLog::isReminderDue(null, 7, $now), 'Never sent: due');
        $this->assertTrue(ReminderLog::isReminderDue(strtotime('2026-10-01 04:30:00'), 7, $now), 'Exactly 7 days ago: due');
        $this->assertTrue(ReminderLog::isReminderDue(strtotime('2026-10-01 04:30:50'), 7, $now), 'Cron started a few seconds earlier than last time: due');
        $this->assertFalse(ReminderLog::isReminderDue(strtotime('2026-10-01 05:30:00'), 7, $now), 'Less than 7 days ago: not due');
        $this->assertFalse(ReminderLog::isReminderDue(strtotime('2026-10-08 04:30:00'), 7, strtotime('2026-10-08 05:30:00')), 'Second cron run on the same day: not due');
    }
}
