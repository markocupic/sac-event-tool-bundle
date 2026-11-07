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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventPdfExport;

use Markocupic\SacEventToolBundle\Feature\EventPdfExport\EventPdfDataProvider;
use PHPUnit\Framework\TestCase;

final class EventPdfDataProviderTest extends TestCase
{
    public function testSumsTheMetersOfAllDays(): void
    {
        $profile = [
            ['tourProfileAscentMeters' => '930', 'tourProfileDescentMeters' => ''],
            ['tourProfileAscentMeters' => "1'210", 'tourProfileDescentMeters' => '2140'],
        ];

        $this->assertSame("2'140", EventPdfDataProvider::sumMeters($profile, 'tourProfileAscentMeters'));
        $this->assertSame("2'140", EventPdfDataProvider::sumMeters($profile, 'tourProfileDescentMeters'));
    }

    public function testReturnsNullWithoutNumbers(): void
    {
        $this->assertNull(EventPdfDataProvider::sumMeters([], 'tourProfileAscentMeters'));
        $this->assertNull(EventPdfDataProvider::sumMeters([['tourProfileAscentMeters' => '']], 'tourProfileAscentMeters'));
        $this->assertNull(EventPdfDataProvider::sumMeters([['tourProfileAscentMeters' => 'ca. 900']], 'tourProfileAscentMeters'));
        $this->assertNull(EventPdfDataProvider::sumMeters([['tourProfileAscentMeters' => '0']], 'tourProfileAscentMeters'));
    }

    public function testFormatsNumbersWithAnApostrophe(): void
    {
        $this->assertSame('950', EventPdfDataProvider::formatNumber(950));
        $this->assertSame("1'640", EventPdfDataProvider::formatNumber(1640));
        $this->assertSame("12'345", EventPdfDataProvider::formatNumber(12345));
    }

    public function testSumsTheAscentAndDescentTimes(): void
    {
        $profile = [
            ['tourProfileAscentTime' => '3.5', 'tourProfileDescentTime' => '0.25'],
            ['tourProfileAscentTime' => '3,5', 'tourProfileDescentTime' => '2.5'],
        ];

        $this->assertSame('7', EventPdfDataProvider::sumHours($profile, 'tourProfileAscentTime'));
        $this->assertSame('2:45', EventPdfDataProvider::sumHours($profile, 'tourProfileDescentTime'));
        $this->assertNull(EventPdfDataProvider::sumHours([['tourProfileAscentTime' => 'ca. 4']], 'tourProfileAscentTime'));
        $this->assertNull(EventPdfDataProvider::sumHours([['tourProfileDescentTime' => '']], 'tourProfileDescentTime'));
        $this->assertNull(EventPdfDataProvider::sumHours([], 'tourProfileAscentTime'));
    }

    public function testFormatsTheProfileRows(): void
    {
        $profile = [
            ['tourProfileAscentMeters' => '1150', 'tourProfileAscentTime' => '4', 'tourProfileDescentMeters' => '', 'tourProfileDescentTime' => ''],
            ['tourProfileAscentMeters' => '', 'tourProfileAscentTime' => '', 'tourProfileDescentMeters' => '', 'tourProfileDescentTime' => ''],
            ['tourProfileAscentMeters' => 'ca. 400', 'tourProfileAscentTime' => '1.5', 'tourProfileDescentMeters' => '1700', 'tourProfileDescentTime' => '5'],
        ];

        $this->assertSame(
            [
                ['day' => 'Tag 1', 'ascent' => "1'150 Hm", 'ascentTime' => '4 h', 'descent' => '', 'descentTime' => ''],
                ['day' => 'Tag 2', 'ascent' => 'ca. 400 Hm', 'ascentTime' => '1.5 h', 'descent' => "1'700 Hm", 'descentTime' => '5 h'],
            ],
            EventPdfDataProvider::formatProfileRows($profile),
        );
    }

    public function testOneDayProfileHasNoDayLabel(): void
    {
        $profile = [['tourProfileAscentMeters' => '1800', 'tourProfileAscentTime' => '12', 'tourProfileDescentMeters' => '1800', 'tourProfileDescentTime' => '5']];

        $this->assertSame('', EventPdfDataProvider::formatProfileRows($profile)[0]['day']);
    }

    public function testFormatsDifficulties(): void
    {
        $ws = ['shortcut' => 'WS', 'title' => 'wenig schwierig'];
        $zs = ['shortcut' => 'ZS', 'title' => 'ziemlich schwierig'];

        $difficulties = [
            ['min' => $ws, 'max' => $ws, 'isEqual' => true],
            ['min' => $ws, 'max' => $zs, 'isEqual' => false],
            ['min' => $zs, 'max' => [], 'isEqual' => false],
            ['min' => [], 'max' => [], 'isEqual' => false],
        ];

        $this->assertSame(
            [
                ['label' => 'WS', 'title' => 'wenig schwierig'],
                ['label' => 'WS – ZS', 'title' => ''],
                ['label' => 'ZS', 'title' => 'ziemlich schwierig'],
            ],
            EventPdfDataProvider::formatDifficulties($difficulties),
        );
    }
}
