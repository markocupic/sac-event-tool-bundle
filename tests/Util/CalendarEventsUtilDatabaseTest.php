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

use Code4Nix\UriSigner\UriSigner;
use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\MemberModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Markocupic\SacEventToolBundle\Config\CourseLevels;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class CalendarEventsUtilDatabaseTest extends ContaoTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TL_LANG']);

        parent::tearDown();
    }

    public function testIsPublicTransportEventReturnsTrueWhenJourneyMatches(): void
    {
        $connection = $this->mockConnection(['fetchOne' => 7]);
        $util = $this->createUtil($connection);

        $this->assertTrue($util->isPublicTransportEvent($this->mockEvent(['journey' => 7])));
    }

    public function testIsPublicTransportEventReturnsFalseWhenJourneyDiffers(): void
    {
        $connection = $this->mockConnection(['fetchOne' => 7]);
        $util = $this->createUtil($connection);

        $this->assertFalse($util->isPublicTransportEvent($this->mockEvent(['journey' => 99])));
    }

    public function testIsPublicTransportEventReturnsFalseWhenNoPublicTransportJourneyExists(): void
    {
        $connection = $this->mockConnection(['fetchOne' => false]);
        $util = $this->createUtil($connection);

        $this->assertFalse($util->isPublicTransportEvent($this->mockEvent(['journey' => 7])));
    }

    public function testGetEventStateReturnsCanceled(): void
    {
        $util = $this->createUtil($this->mockConnection(['fetchOne' => 0]));

        $event = $this->mockEvent($this->eventStateDefaults(['eventState' => EventState::STATE_CANCELED]));

        $this->assertSame('event_status_4', $util->getEventState($event));
    }

    public function testGetEventStateReturnsFinishedWhenStartDateIsInThePast(): void
    {
        $util = $this->createUtil($this->mockConnection(['fetchOne' => 0]));

        $event = $this->mockEvent($this->eventStateDefaults([
            'startDate' => time() - 86400,
            'endDate' => time() + 86400,
        ]));

        $this->assertSame('event_status_2', $util->getEventState($event));
    }

    public function testGetEventStateReturnsWaitingListWhenMaxMembersReached(): void
    {
        $util = $this->createUtil($this->mockConnection(['fetchOne' => 5]));

        $event = $this->mockEvent($this->eventStateDefaults([
            'startDate' => time() + 86400,
            'endDate' => time() + 172800,
            'maxMembers' => 5,
        ]));

        $this->assertSame('event_status_8', $util->getEventState($event));
    }

    public function testGetEventStateReturnsOpenByDefault(): void
    {
        $util = $this->createUtil($this->mockConnection(['fetchOne' => 0]));

        $event = $this->mockEvent($this->eventStateDefaults([
            'startDate' => time() + 86400,
            'endDate' => time() + 172800,
        ]));

        $this->assertSame('event_status_1', $util->getEventState($event));
    }

    public function testEventIsFullyBookedWhenMaxMembersReached(): void
    {
        $util = $this->createUtil($this->mockConnection(['fetchOne' => 3]));

        $event = $this->mockEvent(['id' => 1, 'eventState' => '', 'maxMembers' => 3]);

        $this->assertTrue($util->eventIsFullyBooked($event));
    }

    public function testEventIsNotFullyBookedWhenPlacesAreLeft(): void
    {
        $util = $this->createUtil($this->mockConnection(['fetchOne' => 1]));

        $event = $this->mockEvent(['id' => 1, 'eventState' => '', 'maxMembers' => 3]);

        $this->assertFalse($util->eventIsFullyBooked($event));
    }

    public function testEventIsFullyBookedWhenStateIsFullyBooked(): void
    {
        $util = $this->createUtil($this->mockConnection(['fetchOne' => 0]));

        $event = $this->mockEvent(['id' => 1, 'eventState' => EventState::STATE_FULLY_BOOKED, 'maxMembers' => 0]);

        $this->assertTrue($util->eventIsFullyBooked($event));
    }

    public function testGetInstructorsAsArraySkipsDisabledUsers(): void
    {
        $connection = $this->mockConnection(['fetchFirstColumn' => [10, 20, 30]]);

        $userAdapter = $this->mockAdapter(['findById']);
        $userAdapter
            ->method('findById')
            ->willReturnCallback(
                fn ($id): UserModel|null => match ((int) $id) {
                    10 => $this->mockUser(['id' => 10]),
                    20 => $this->mockUser(['id' => 20, 'disable' => true]),
                    30 => $this->mockUser(['id' => 30]),
                    default => null,
                },
            )
        ;

        $util = $this->createUtil($connection, [UserModel::class => $userAdapter]);

        $this->assertSame([10, 30], $util->getInstructorsAsArray($this->mockEvent(['id' => 1])));
    }

    public function testGetMainInstructorNameReturnsLastAndFirstName(): void
    {
        $connection = $this->mockConnection(['fetchOne' => 5]);

        $userAdapter = $this->mockAdapter(['findById']);
        $userAdapter
            ->method('findById')
            ->willReturn($this->mockUser(['id' => 5, 'lastname' => 'Muster', 'firstname' => 'Hans']))
        ;

        $util = $this->createUtil($connection, [UserModel::class => $userAdapter]);

        $this->assertSame('Muster Hans', $util->getMainInstructorName($this->mockEvent(['id' => 1])));
    }

    public function testGetMainInstructorNameReturnsEmptyStringWhenNoInstructor(): void
    {
        $connection = $this->mockConnection(['fetchOne' => false]);
        $util = $this->createUtil($connection, [UserModel::class => $this->mockAdapter(['findById'])]);

        $this->assertSame('', $util->getMainInstructorName($this->mockEvent(['id' => 1])));
    }

    public function testGetSectionMembershipAsStringResolvesSectionNames(): void
    {
        $GLOBALS['TL_LANG']['tl_member']['section'] = [
            4250 => 'SAC Pilatus',
            3000 => 'SAC Test',
        ];

        $util = $this->createUtil();

        $member = $this->mockClassWithProperties(MemberModel::class, [
            'sectionId' => serialize([4250, 3000, 9999]),
        ]);

        // Unknown section ids fall back to the raw id.
        $this->assertSame('SAC Pilatus, SAC Test, 9999', $util->getSectionMembershipAsString($member));
    }

    /**
     * @param array<string, mixed> $adapters
     */
    private function createUtil(Connection|null $connection = null, array $adapters = []): CalendarEventsUtil
    {
        $system = $this->mockAdapter(['loadLanguageFile']);
        $framework = $this->mockContaoFramework(array_merge([System::class => $system], $adapters));

        return $this->createCalendarEventsUtil($framework, $connection);
    }

    /**
     * @param array<string, mixed> $returnValues
     */
    private function mockConnection(array $returnValues): Connection
    {
        $connection = $this->createMock(Connection::class);

        foreach ($returnValues as $method => $value) {
            $connection
                ->method($method)
                ->willReturn($value)
            ;
        }

        return $connection;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function mockEvent(array $properties): CalendarEventsModel
    {
        return $this->mockClassWithProperties(CalendarEventsModel::class, $properties);
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function mockUser(array $properties): UserModel
    {
        return $this->mockClassWithProperties(UserModel::class, array_merge([
            'id' => 0,
            'disable' => false,
            'stop' => '',
            'start' => '',
            'hideUser' => false,
            'lastname' => '',
            'firstname' => '',
        ], $properties));
    }

    /**
     * Sensible defaults for the many properties getEventState() reads.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function eventStateDefaults(array $overrides = []): array
    {
        return array_merge(
            [
                'id' => 1,
                'eventState' => '',
                'startDate' => time() + 86400,
                'endDate' => time() + 172800,
                'setRegistrationPeriod' => false,
                'registrationStartDate' => 0,
                'registrationEndDate' => 0,
                'maxMembers' => 0,
                'disableOnlineRegistration' => false,
            ],
            $overrides,
        );
    }

    /**
     * Creates a CalendarEventsUtil instance whose services are mocked.
     */
    private function createCalendarEventsUtil(ContaoFramework $framework, Connection|null $connection = null,): CalendarEventsUtil
    {
        return new CalendarEventsUtil(
            framework: $framework,
            connection: $connection ?? $this->createMock(Connection::class),
            contentUrlGenerator: $this->createMock(ContentUrlGenerator::class),
            insertTagParser: $this->createMock(InsertTagParser::class),
            twig: $this->createMock(Environment::class),
            packages: $this->createMock(Packages::class),
            router: $this->createMock(RouterInterface::class),
            contaoCsrfTokenManager: $this->createMock(ContaoCsrfTokenManager::class),
            requestStack: new RequestStack(),
            security: $this->createMock(Security::class),
            urlParser: $this->createMock(UrlParser::class),
            uriSigner: new UriSigner('secret'),
            avatar: $this->createMock(Avatar::class),
            courseLevels: new CourseLevels([]),
            projectDir: '/project',
            eventCourseFallbackImage: 'files/fallback_image.svg',
            eventGeoLink: 'https://map.geo.admin.ch/?E=%s&N=%s',
            sacRoutePortalBaseLink: 'https://www.sac-cas.ch/de/huetten-und-touren/sac-tourenportal/',
            regStartTimeOffset: 0,
        );
    }
}
