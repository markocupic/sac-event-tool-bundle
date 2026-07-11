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

namespace Markocupic\SacEventToolBundle\Tests\Util;

use Contao\CalendarEventsModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;

class CalendarEventsUtilTest extends ContaoTestCase
{
    public function testGetPublicTransportBadgeReturnsTheBadgeMarkup(): void
    {
        $badge = $this->util()->getPublicTransportBadge();

        $this->assertStringStartsWith('<span', $badge);
        $this->assertStringContainsString('bg-success', $badge);
        $this->assertStringContainsString('>ÖV</span>', $badge);
    }

    /**
     * @param array<int, string> $expected
     *
     * @dataProvider coordsProvider
     */
    public function testGetCoordsCH1903AsArray(string $raw, array $expected): void
    {
        $event = $this->mockEvent(['coordsCH1903' => $raw]);

        $this->assertSame($expected, $this->util()->getCoordsCH1903AsArray($event));
    }

    public static function coordsProvider(): iterable
    {
        return [
            'CH1903+ pair' => ['2600000, 1200000', ['2600000', '1200000']],
            'CH1903 pair' => ['600000, 200000', ['600000', '200000']],
            'strips quotes and spaces' => ['"2600000" , "1200000"', ['2600000', '1200000']],
            'empty string' => ['', []],
            'single value is rejected' => ['2600000', []],
        ];
    }

    public function testGetStartTstampReturnsFirstRepeat(): void
    {
        $event = $this->mockEvent([
            'eventDates' => serialize([
                ['new_repeat' => 1000],
                ['new_repeat' => 2000],
                ['new_repeat' => 3000],
            ]),
        ]);

        $this->assertSame(1000, $this->util()->getStartTstamp($event));
    }

    public function testGetEndTstampReturnsLastRepeat(): void
    {
        $event = $this->mockEvent([
            'eventDates' => serialize([
                ['new_repeat' => 1000],
                ['new_repeat' => 2000],
                ['new_repeat' => 3000],
            ]),
        ]);

        $this->assertSame(3000, $this->util()->getEndTstamp($event));
    }

    public function testStartAndEndTstampReturnZeroForEmptyDates(): void
    {
        $event = $this->mockEvent(['eventDates' => '']);

        $this->assertSame(0, $this->util()->getStartTstamp($event));
        $this->assertSame(0, $this->util()->getEndTstamp($event));
    }

    public function testGetEventTimestampsReturnsAllRepeats(): void
    {
        $event = $this->mockEvent([
            'eventDates' => serialize([
                ['new_repeat' => 1000],
                ['new_repeat' => 2000],
            ]),
        ]);

        $this->assertSame([1000, 2000], $this->util()->getEventTimestamps($event));
    }

    public function testGetEventDurationPrefersDurationInfo(): void
    {
        $event = $this->mockEvent([
            'durationInfo' => '2 Tage (Wochenende)',
            'eventDates' => serialize([['new_repeat' => 1000]]),
        ]);

        $this->assertSame('2 Tage (Wochenende)', $this->util()->getEventDuration($event));
    }

    public function testGetEventDurationFallsBackToDateCount(): void
    {
        $event = $this->mockEvent([
            'durationInfo' => '',
            'eventDates' => serialize([
                ['new_repeat' => 1000],
                ['new_repeat' => 2000],
                ['new_repeat' => 3000],
            ]),
        ]);

        $this->assertSame('3 Tage', $this->util()->getEventDuration($event));
    }

    public function testGetEventDurationReturnsEmptyStringWhenNothingIsSet(): void
    {
        $event = $this->mockEvent(['durationInfo' => '', 'eventDates' => '']);

        $this->assertSame('', $this->util()->getEventDuration($event));
    }

    public function testGetTourProfileAsArrayReturnsEmptyArrayWithoutProfile(): void
    {
        $event = $this->mockEvent(['tourProfile' => '']);

        $this->assertSame([], $this->util()->getTourProfileAsArray($event));
    }

    public function testGetTourProfileAsArraySkipsEmptyDays(): void
    {
        $event = $this->mockEvent([
            'tourProfile' => serialize([
                [
                    'tourProfileAscentMeters' => '',
                    'tourProfileAscentTime' => '',
                    'tourProfileDescentMeters' => '',
                    'tourProfileDescentTime' => '',
                ],
            ]),
        ]);

        $this->assertSame([], $this->util()->getTourProfileAsArray($event));
    }

    public function testGetTourProfileAsArrayForSingleDay(): void
    {
        $event = $this->mockEvent([
            'tourProfile' => serialize([
                [
                    'tourProfileAscentMeters' => '1200',
                    'tourProfileAscentTime' => '4',
                    'tourProfileDescentMeters' => '1000',
                    'tourProfileDescentTime' => '3',
                ],
            ]),
        ]);

        $this->assertSame(
            ['Aufst: 1200 Hm/4 h, Abst: 1000 Hm/3 h'],
            $this->util()->getTourProfileAsArray($event),
        );
    }

    public function testGetTourProfileAsArrayForMultipleDaysAddsDayPrefix(): void
    {
        $event = $this->mockEvent([
            'tourProfile' => serialize([
                [
                    'tourProfileAscentMeters' => '1200',
                    'tourProfileAscentTime' => '4',
                    'tourProfileDescentMeters' => '1000',
                    'tourProfileDescentTime' => '3',
                ],
                [
                    'tourProfileAscentMeters' => '800',
                    'tourProfileAscentTime' => '2',
                    'tourProfileDescentMeters' => '600',
                    'tourProfileDescentTime' => '1',
                ],
            ]),
        ]);

        $this->assertSame(
            [
                '1. Tag: Aufst: 1200 Hm/4 h, Abst: 1000 Hm/3 h',
                '2. Tag: Aufst: 800 Hm/2 h, Abst: 600 Hm/1 h',
            ],
            $this->util()->getTourProfileAsArray($event),
        );
    }

    private function util(): CalendarEventsUtil
    {
        return new CalendarEventsUtil($this->mockContaoFramework());
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function mockEvent(array $properties): CalendarEventsModel
    {
        return $this->mockClassWithProperties(CalendarEventsModel::class, $properties);
    }
}
