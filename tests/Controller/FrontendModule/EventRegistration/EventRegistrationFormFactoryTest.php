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

namespace Markocupic\SacEventToolBundle\Tests\Controller\FrontendModule\EventRegistration;

use Contao\CalendarEventsModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Config\CarSeatInfo;
use Markocupic\SacEventToolBundle\Config\EventMountainGuide;
use Markocupic\SacEventToolBundle\Config\TicketInfo;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\EventRegistrationFormFactory;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventRegistrationFormFactoryTest extends ContaoTestCase
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
     * @dataProvider consecutiveDaysProvider
     */
    #[DataProvider('consecutiveDaysProvider')]
    public function testAreConsecutiveDays(string $start, string $end, int $numberOfDates, bool $expected): void
    {
        $this->assertSame($expected, EventRegistrationFormFactory::areConsecutiveDays(strtotime($start), strtotime($end), $numberOfDates));
    }

    public static function consecutiveDaysProvider(): iterable
    {
        yield 'three days' => ['2026-09-11 00:00', '2026-09-13 00:00', 3, true];
        yield 'over the end of daylight saving time (25 h day)' => ['2026-10-24 00:00', '2026-10-26 00:00', 3, true];
        yield 'over the start of daylight saving time (23 h day)' => ['2026-03-28 00:00', '2026-03-30 00:00', 3, true];
        yield 'one date' => ['2026-09-11 00:00', '2026-09-11 00:00', 1, false];
        yield 'dates with a gap' => ['2026-09-11 00:00', '2026-09-18 00:00', 2, false];
        yield 'times of day are ignored' => ['2026-09-11 08:00', '2026-09-12 18:00', 2, true];
    }

    public function testFieldsOfASimpleEvent(): void
    {
        $fields = $this->createFactory()->getFieldNames($this->makeEvent());

        $this->assertSame(['mobile', 'emergencyPhone', 'emergencyPhoneName', 'notes', 'agb', 'hasAcceptedPrivacyRules', 'submit'], $fields);
    }

    public function testFieldsOfAMultiDayEventWithMountainGuideAndAhvNumber(): void
    {
        $event = $this->makeEvent([
            'askForAhvNumber' => '1',
            'mountainguide' => EventMountainGuide::WITH_MOUNTAIN_GUIDE_OFFER,
            // Over the end of daylight saving time
            'eventDates' => serialize([
                ['new_repeat' => strtotime('2026-10-24 00:00')],
                ['new_repeat' => strtotime('2026-10-25 00:00')],
                ['new_repeat' => strtotime('2026-10-26 00:00')],
            ]),
        ]);

        $fields = $this->createFactory(journeyAlias: 'car')->getFieldNames($event);

        $this->assertSame(['carInfo', 'ahvNumber', 'mobile', 'emergencyPhone', 'emergencyPhoneName', 'notes', 'foodHabits', 'avbSbv', 'hasAcceptedPrivacyRules', 'submit'], $fields);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeEvent(array $overrides = []): CalendarEventsModel
    {
        return $this->mockClassWithProperties(CalendarEventsModel::class, array_merge([
            'id' => 1,
            'journey' => 3,
            'askForAhvNumber' => '',
            'eventType' => 'tour',
            'mountainguide' => '',
            'eventDates' => serialize([['new_repeat' => strtotime('2026-09-11 00:00')]]),
        ], $overrides));
    }

    private function createFactory(string|null $journeyAlias = null): EventRegistrationFormFactory
    {
        $journeyAdapter = $this->mockAdapter(['findById']);
        $journeyAdapter
            ->method('findById')
            ->willReturn(null === $journeyAlias ? null : $this->mockClassWithProperties(CalendarEventsJourneyModel::class, ['alias' => $journeyAlias]))
        ;

        // Real methods (getStartTstamp(), getEventTimestamps(), ...), only the organizers are stubbed
        $calendarEventsUtil = $this->getMockBuilder(CalendarEventsUtil::class)
            ->setConstructorArgs([$this->mockContaoFramework()])
            ->onlyMethods(['getEventOrganizerModels'])
            ->getMock()
        ;

        $calendarEventsUtil
            ->method('getEventOrganizerModels')
            ->willReturn([])
        ;

        return new EventRegistrationFormFactory(
            $calendarEventsUtil,
            $this->createMock(CarSeatInfo::class),
            $this->mockContaoFramework([CalendarEventsJourneyModel::class => $journeyAdapter]),
            $this->createMock(TicketInfo::class),
            $this->createMock(TranslatorInterface::class),
        );
    }
}
