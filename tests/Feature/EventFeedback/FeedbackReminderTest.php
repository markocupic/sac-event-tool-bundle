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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventFeedback;

use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackReminder;
use PHPUnit\Framework\TestCase;

final class FeedbackReminderTest extends TestCase
{
    public function testScheduleStartsAtConfirmationIfEventHasEnded(): void
    {
        $now = strtotime('2026-10-05 10:00');
        $schedule = FeedbackReminder::getSchedule($now, strtotime('2026-10-04 18:00'), [0, 14, 28], 60);

        $this->assertSame([$now, strtotime('2026-10-19 10:00'), strtotime('2026-11-02 10:00')], $schedule['executionDates']);
        $this->assertSame(strtotime('2026-12-04 10:00'), $schedule['expiration']);
    }

    public function testScheduleStartsAtEventEndIfConfirmedBefore(): void
    {
        $eventEnd = strtotime('2026-10-11 18:00');
        $schedule = FeedbackReminder::getSchedule(strtotime('2026-10-05 10:00'), $eventEnd, [0, 14], 60);

        $this->assertSame([$eventEnd, strtotime('2026-10-25 18:00')], $schedule['executionDates']);
        $this->assertSame(strtotime('2026-12-10 18:00'), $schedule['expiration']);
    }
}
