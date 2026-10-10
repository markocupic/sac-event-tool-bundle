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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventCompletionReminder;

use Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\ReminderSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReminderScheduleTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Zurich');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);

        parent::tearDown();
    }

    /**
     * @dataProvider dueEndDateProvider
     */
    #[DataProvider('dueEndDateProvider')]
    public function testDueEndDateMax(string $endDate, string $now, int $firstOffset, bool $expectedDue): void
    {
        $max = ReminderSchedule::getDueEndDateMax($firstOffset, new \DateTimeImmutable($now));

        $this->assertSame($expectedDue, strtotime($endDate) <= $max);
    }

    public static function dueEndDateProvider(): iterable
    {
        yield 'completion period not yet over (6 days)' => ['2026-10-01 18:00', '2026-10-07 03:45', 7, false];
        yield 'completion period over exactly on day 7, early end' => ['2026-10-01 08:00', '2026-10-08 03:45', 7, true];
        yield 'completion period over exactly on day 7, late end' => ['2026-10-01 23:59', '2026-10-08 03:45', 7, true];
        yield 'event ended long ago' => ['2026-01-01 12:00', '2026-10-08 03:45', 7, true];
        yield 'event ended today' => ['2026-10-08 01:00', '2026-10-08 03:45', 7, false];
        yield 'across DST change' => ['2026-10-20 12:00', '2026-10-27 03:45', 7, true];
    }

    /**
     * @dataProvider notificationDueProvider
     */
    #[DataProvider('notificationDueProvider')]
    public function testIsNotificationDue(string|null $lastSentAt, string $now, int $interval, bool $expected): void
    {
        $lastSentTstamp = null === $lastSentAt ? null : strtotime($lastSentAt);

        $this->assertSame($expected, ReminderSchedule::isNotificationDue($lastSentTstamp, $interval, new \DateTimeImmutable($now)));
    }

    public static function notificationDueProvider(): iterable
    {
        yield 'never sent' => [null, '2026-10-08 03:45', 7, true];
        yield 'sent yesterday' => ['2026-10-07 03:45:10', '2026-10-08 03:45', 7, false];
        yield 'sent 6 days ago' => ['2026-10-02 03:45:10', '2026-10-08 03:45', 7, false];
        yield 'sent 7 days ago, cron a few seconds earlier' => ['2026-10-01 03:45:10', '2026-10-08 03:45:05', 7, true];
        yield 'sent 7 days ago, second cron run at night' => ['2026-10-01 04:45:00', '2026-10-08 00:00:00', 7, true];
        yield 'sent today, interval 1' => ['2026-10-08 03:45', '2026-10-08 04:45', 1, false];
        yield 'sent yesterday, interval 1' => ['2026-10-07 03:45', '2026-10-08 03:45', 1, true];
    }

    public function testZeroTimestampMeansNeverSent(): void
    {
        $this->assertTrue(ReminderSchedule::isNotificationDue(0, 7, new \DateTimeImmutable('2026-10-08 03:45')));
    }
}
