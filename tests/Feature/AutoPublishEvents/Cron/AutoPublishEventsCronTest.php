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

namespace Markocupic\SacEventToolBundle\Tests\Feature\AutoPublishEvents\Cron;

use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CalendarRunner;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Cron\AutoPublishEventsCron;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\PublishResult;
use Psr\Log\LoggerInterface;

final class AutoPublishEventsCronTest extends ContaoTestCase
{
    public function testRunsAllDueCalendarsAndContinuesAfterAnError(): void
    {
        $runner = $this->createMock(CalendarRunner::class);
        $runner
            ->method('findDueCalendarIds')
            ->willReturn([3, 7])
        ;

        $runner
            ->expects($this->exactly(2))
            ->method('run')
            ->willReturnCallback(
                static function (int $calendarId): PublishResult {
                    if (3 === $calendarId) {
                        throw new \RuntimeException('Calendar 3 is broken');
                    }

                    return new PublishResult($calendarId, 'Touren 2027');
                },
            )
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Calendar 3 is broken'))
        ;

        $logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('calendar "Touren 2027" (ID 7)'))
        ;

        $cron = new AutoPublishEventsCron($this->mockContaoFramework(), $runner, $logger);
        $cron();
    }
}
