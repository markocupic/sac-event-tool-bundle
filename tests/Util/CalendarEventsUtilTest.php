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
use Contao\CalendarModel;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\PageModel;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

class CalendarEventsUtilTest extends ContaoTestCase
{
    public static function tearDownAfterClass(): void
    {
        // The temporary directory would not be removed without this call!
        parent::tearDownAfterClass();

        unset($GLOBALS['TL_MODELS']['tl_user']);
    }

    protected function setUp(): void
    {
        $tempDir = $this->getTempDir();
        $fs = new Filesystem();
        $fs->mkdir($tempDir.'/var/cache');

        $GLOBALS['TL_MODELS']['tl_user'] = UserModel::class;
    }

    public function testIsPublicTransportEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(1)
        ;

        $container = $this->getContainerWithContaoConfiguration();
        $container->set('database_connection', $connection);
        System::setContainer($container);

        $systemAdapter = $this->mockAdapter(['getContainer']);
        $systemAdapter
            ->method('getContainer')
            ->willReturn($container)
        ;

        $framework = $this->mockContaoFramework([
            System::class => $systemAdapter,
        ]);

        $objEventMock = $this->mockClassWithProperties(CalendarEventsModel::class);
        $objEventMock->journey = 1;
        $calendarEventsUtil = new CalendarEventsUtil($framework);
        $this->assertTrue($calendarEventsUtil->isPublicTransportEvent($objEventMock));

        $calendarEventsUtil = new CalendarEventsUtil($framework);
        $objEventMock->journey = 2;
        $this->assertFalse($calendarEventsUtil->isPublicTransportEvent($objEventMock));
    }

    public function testGenerateInstructorContactBoxes(): void
    {
        $container = $this->getContainerWithContaoConfiguration();

        $objCalendarMock = $this->mockClassWithProperties(CalendarModel::class);
        $objCalendarMock->userPortraitJumpTo = 1;

        $objEventMock = $this->mockClassWithProperties(CalendarEventsModel::class);
        $objEventMock->pid = 1;

        $objEventMock
            ->method('getRelated')
            ->with('pid')
            ->willReturn($objCalendarMock)
        ;

        $options = [
            'includeDisabled' => true,
            'includeHidden' => true,
        ];

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(1))
            ->method('fetchFirstColumn')
            ->willReturn([1])
        ;
        $container->set('database_connection', $connection);

        $contentUrlGeneratorMock = $this->createMock(ContentUrlGenerator::class);
        $contentUrlGeneratorMock
            ->expects($this->once())
            ->method('generate')
            ->willReturn('profile_page')
        ;
        $container->set('contao.routing.content_url_generator', $contentUrlGeneratorMock);

        $backendUserModel = $this->mockClassWithProperties(UserModel::class, ['id' => 1, 'username' => 'johndoe', 'firstname' => 'John', 'lastname' => 'Doe', 'phone' => '041 999 99 99', 'mobile' => '041 999 99 99', 'email' => 'john.doe@foo.bar', 'gender' => 'male', 'disable' => false, 'hideUser' => false, 'start' => '', 'stop' => '']);
        $userAdapter = $this->mockAdapter(['findById']);
        $userAdapter
            ->method('findById')
            ->willReturn($backendUserModel)
        ;

        $filesAdapter = $this->mockAdapter(['findByUuid']);

        $pageModel = $this->mockClassWithProperties(PageModel::class);
        $pageAdapter = $this->mockAdapter(['findById']);
        $pageAdapter
            ->method('findById')
            ->willReturn($pageModel)
        ;

        $systemAdapter = $this->mockAdapter(['getContainer']);
        $systemAdapter
            ->method('getContainer')
            ->willReturn($container)
        ;

        $framework = $this->mockContaoFramework([
            PageModel::class => $pageAdapter,
            UserModel::class => $userAdapter,
            FilesModel::class => $filesAdapter,
            System::class => $systemAdapter,
        ]);

        $projectDir = $this->getTempDir();
        $avatarFemale = 'folder/female.jpg';
        $avatarMale = 'folder/male.jpg';
        $avatarOther = 'folder/other.jpg';
        $container->set(Avatar::class, new Avatar($framework, $projectDir, $avatarFemale, $avatarMale, $avatarOther));

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->once())
            ->method('render')
            ->willReturnCallback(
                function ($template, $context) {
                    $expectedContext = [
                        'instructors' => [
                            [
                                'id' => 1,
                                'username' => 'johndoe',
                                'firstname' => 'John',
                                'lastname' => 'Doe',
                                'phone' => '041 999 99 99',
                                'mobile' => '041 999 99 99',
                                'email' => 'john.doe@foo.bar',
                                'gender' => 'male',
                                'disable' => false,
                                'hideUser' => false,
                                'start' => '',
                                'stop' => '',
                                'href' => 'profile_page?getUpcoming=1&username=johndoe',
                                'has_link' => true,
                                'avatar_path' => 'folder/male.jpg',
                                'main_qualification' => '',
                                'contact_options' => [
                                    'phone' => '041 999 99 99',
                                    'mobile' => '041 999 99 99',
                                    'email' => 'john.doe@foo.bar',
                                ],
                            ],
                        ],
                    ];
                    $this->assertSame($expectedContext, $context);

                    return '';
                },
            )
        ;
        $container->set('twig', $twig);

        System::setContainer($container);

        $calendarEventsUtil = new CalendarEventsUtil($framework);

        // We will not check the output but the $context of Twig::render($template, $context)
        $this->assertSame('', $calendarEventsUtil->generateInstructorContactBoxes($objEventMock, $options));
    }

    public function testGetInstructorsAsArray(): void
    {
        $userModels = [
            $this->mockClassWithProperties(UserModel::class, ['id' => 1, 'disable' => false, 'hideUser' => false, 'start' => '', 'stop' => '']),
            $this->mockClassWithProperties(UserModel::class, ['id' => 2, 'disable' => false, 'hideUser' => false, 'start' => '', 'stop' => '']),
            $this->mockClassWithProperties(UserModel::class, ['id' => 3, 'disable' => true, 'hideUser' => false, 'start' => '', 'stop' => '']),
            $this->mockClassWithProperties(UserModel::class, ['id' => 4, 'disable' => false, 'hideUser' => false, 'start' => strtotime('+1 day'), 'stop' => '']),
            $this->mockClassWithProperties(UserModel::class, ['id' => 5, 'disable' => false, 'hideUser' => false, 'start' => '', 'stop' => strtotime('-1 day')]),
            $this->mockClassWithProperties(UserModel::class, ['id' => 6, 'disable' => false, 'hideUser' => true, 'start' => '', 'stop' => '']),
        ];

        $container = $this->getContainerWithContaoConfiguration();

        $systemAdapter = $this->mockAdapter(['getContainer']);
        $systemAdapter
            ->method('getContainer')
            ->willReturn($container)
        ;

        $userAdapter = $this->mockAdapter(['findById']);
        $userAdapter
            ->expects($this->exactly(18))
            ->method('findById')
            ->willReturn(...$userModels, ...$userModels, ...$userModels)
        ;

        $framework = $this->mockContaoFramework([
            UserModel::class => $userAdapter,
            System::class => $systemAdapter,
        ]);

        $framework
            ->expects($this->atLeastOnce())
            ->method('initialize')
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(3))
            ->method('fetchFirstColumn')
            ->willReturn([1, 2, 3, 4, 5, 6])
        ;

        $container->set('database_connection', $connection);
        $container->set('contao.framework', $framework);

        (new Filesystem())->mkdir($this->getTempDir().'/languages/en');
        $container->setParameter('contao.resources_paths', $this->getTempDir());

        System::setContainer($container);

        $eventModel = $this->mockClassWithProperties(CalendarEventsModel::class);
        $eventModel->id = 1;

        $calendarEventsUtil = new CalendarEventsUtil($framework);

        $options = [
            'includeDisabled' => true,
            'includeHidden' => true,
        ];
        $this->assertSame([1, 2, 3, 4, 5, 6], $calendarEventsUtil->getInstructorsAsArray($eventModel, $options));

        $options = [
            'includeDisabled' => false,
            'includeHidden' => true,
        ];
        $this->assertSame([1, 2, 6], $calendarEventsUtil->getInstructorsAsArray($eventModel, $options));

        $options = [
            'includeDisabled' => false,
            'includeHidden' => false,
        ];
        $this->assertSame([1, 2], $calendarEventsUtil->getInstructorsAsArray($eventModel, $options));
    }

    public function testGetEventState(): void
    {
        $container = $this->getContainerWithContaoConfiguration();

        $systemAdapter = $this->mockAdapter(['getContainer']);
        $systemAdapter
            ->method('getContainer')
            ->willReturn($container)
        ;

        $framework = $this->mockContaoFramework([
            System::class => $systemAdapter,
        ]);

        $framework
            ->expects($this->atLeastOnce())
            ->method('initialize')
        ;
        $container->set('contao.framework', $framework);

        $registrationCount = 5;
        $database = $this->createMock(Connection::class);
        $database
            ->expects($this->atLeastOnce())
            ->method('fetchOne')
            ->willReturn($registrationCount)
        ;
        $container->set('database_connection', $database);

        $regStartTimeOffset = 6 * 60 * 60;
        $container->setParameter('sacevt.event_registration.config.reg_start_time_offset', $regStartTimeOffset); // 6h

        System::setContainer($container);
        $calendarEventsUtil = new CalendarEventsUtil($framework);

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class);
        $objEvent->eventState = EventState::STATE_CANCELED;
        $this->assertSame('event_status_4', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class);
        $objEvent->eventState = EventState::STATE_RESCHEDULED;
        $this->assertSame('event_status_6', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class);
        $objEvent->eventState = EventState::STATE_FULLY_BOOKED;
        $this->assertSame('event_status_3', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['startDate' => time() - 1]);
        $this->assertSame('event_status_2', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['endDate' => time() - 1]);
        $this->assertSame('event_status_2', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['setRegistrationPeriod' => true, 'registrationEndDate' => time() - 1]);
        $this->assertSame('event_status_2', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['startDate' => strtotime('+7 days'), 'endDate' => strtotime('+8 days')]);
        $objEvent->maxMembers = $registrationCount;
        $this->assertSame('event_status_8', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['startDate' => strtotime('+7 days'), 'endDate' => strtotime('+8 days')]);
        $objEvent->maxMembers = $registrationCount - 1;
        $this->assertSame('event_status_8', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['setRegistrationPeriod' => true, 'registrationStartDate' => time() - $regStartTimeOffset + 10, 'registrationEndDate' => strtotime('+6 days'), 'startDate' => strtotime('+7 days'), 'endDate' => strtotime('+8 days')]);
        $this->assertSame('event_status_5', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['setRegistrationPeriod' => true, 'registrationStartDate' => time() - $regStartTimeOffset - 10, 'registrationEndDate' => strtotime('+7 days'), 'startDate' => strtotime('+7 days'), 'endDate' => strtotime('+8 days')]);
        $this->assertSame('event_status_1', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['disableOnlineRegistration' => true, 'startDate' => strtotime('+7 days'), 'endDate' => strtotime('+8 days')]);
        $this->assertSame('event_status_7', $calendarEventsUtil->getEventState($objEvent));

        $objEvent = $this->mockClassWithProperties(CalendarEventsModel::class, ['startDate' => strtotime('+7 days'), 'endDate' => strtotime('+8 days')]);
        $this->assertSame('event_status_1', $calendarEventsUtil->getEventState($objEvent));
    }
}
