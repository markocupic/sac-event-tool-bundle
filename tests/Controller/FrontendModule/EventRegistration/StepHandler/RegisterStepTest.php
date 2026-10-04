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

namespace Markocupic\SacEventToolBundle\Tests\Controller\FrontendModule\EventRegistration\StepHandler;

use Codefog\HasteBundle\Form\Form;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\EventRegistrationCreator;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\EventRegistrationEligibility;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\EventRegistrationFormFactory;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\LoggedInMemberProvider;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler\RegisterStep;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\Exception\EventRegistrationException;
use Markocupic\SacEventToolBundle\Event\EventRegistrationEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterStepTest extends ContaoTestCase
{
    public function testStaticMetaData(): void
    {
        $this->assertSame('register', RegisterStep::getName());
        $this->assertSame(200, RegisterStep::getPriority());
        $this->assertStringContainsString('register.html.twig', $this->createStep()->getTemplateName());
    }

    public function testValidateReturnsFalseWithoutMember(): void
    {
        $step = $this->createStep(member: null);

        $this->assertFalse($step->validate($this->makeEvent(), new Request(), $this->createMock(ModuleModel::class)));
    }

    public function testValidateReturnsWhetherTheMemberIsRegistered(): void
    {
        $this->assertTrue($this->createStep(isRegistered: true)->validate($this->makeEvent(), new Request(), $this->createMock(ModuleModel::class)));
        $this->assertFalse($this->createStep(isRegistered: false)->validate($this->makeEvent(), new Request(), $this->createMock(ModuleModel::class)));
    }

    public function testPrepareStepThrowsWithoutMember(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createStep(member: null)->prepareStep($this->makeEvent(), new Request(), $this->createMock(ModuleModel::class));
    }

    public function testPrepareStepShowsTheFormWithoutSaving(): void
    {
        $form = $this->createMock(Form::class);
        $form
            ->method('validate')
            ->willReturn(false)
        ;

        $creator = $this->createMock(EventRegistrationCreator::class);
        $creator
            ->expects($this->never())
            ->method('create')
        ;

        $template = $this->createStep(form: $form, creator: $creator)->prepareStep($this->makeEvent(), new Request(), $this->createMock(ModuleModel::class));

        $this->assertSame($form, $template['form']);
        $this->assertArrayNotHasKey('eventFullyBooked', $template);
    }

    public function testPrepareStepSavesTheRegistrationAndReloads(): void
    {
        $form = $this->createMock(Form::class);
        $form
            ->method('validate')
            ->willReturn(true)
        ;

        $form
            ->method('fetchAll')
            ->willReturn(['notes' => 'Hallo'])
        ;

        $registration = $this->createMock(CalendarEventsMemberModel::class);
        $registration
            ->method('row')
            ->willReturn(['id' => 77])
        ;

        $creator = $this->createMock(EventRegistrationCreator::class);
        $creator
            ->expects($this->once())
            ->method('create')
            ->with($this->anything(), $this->anything(), ['notes' => 'Hallo'])
            ->willReturn($registration)
        ;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(EventRegistrationEvent::class))
        ;

        $controllerAdapter = $this->mockAdapter(['reload']);
        $controllerAdapter
            ->expects($this->once())
            ->method('reload')
        ;

        $this->createStep(form: $form, creator: $creator, dispatcher: $dispatcher, controllerAdapter: $controllerAdapter)
            ->prepareStep($this->makeEvent(), new Request(), $this->createMock(ModuleModel::class))
        ;
    }

    public function testFullyBookedEventIsFlagged(): void
    {
        $template = $this->createStep(fullyBooked: true)->prepareStep($this->makeEvent(), new Request(), $this->createMock(ModuleModel::class));

        $this->assertTrue($template['eventFullyBooked']);
    }

    public function testIneligibleMemberGetsNoForm(): void
    {
        $eligibility = $this->createMock(EventRegistrationEligibility::class);
        $eligibility
            ->method('check')
            ->willThrowException(new EventRegistrationException('Not published.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventNotPublishedYet'))
        ;

        $formFactory = $this->createMock(EventRegistrationFormFactory::class);
        $formFactory
            ->expects($this->never())
            ->method('create')
        ;

        $template = $this->createStep(eligibility: $eligibility, formFactory: $formFactory)->prepareStep($this->makeEvent(), new Request(), $this->createMock(ModuleModel::class));

        $this->assertArrayNotHasKey('form', $template);
    }

    public function testUnknownErrorIsLoggedWithTheException(): void
    {
        $exception = new \RuntimeException('Database is gone');

        $formFactory = $this->createMock(EventRegistrationFormFactory::class);
        $formFactory
            ->method('create')
            ->willThrowException($exception)
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Database is gone',
                $this->callback(static fn (array $context): bool => $exception === $context['exception']),
            )
        ;

        $this->createStep(formFactory: $formFactory, logger: $logger)->prepareStep($this->makeEvent(), new Request(), $this->createMock(ModuleModel::class));
    }

    private function makeEvent(): CalendarEventsModel
    {
        return $this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 1, 'title' => 'Testevent']);
    }

    private function createStep(MemberModel|false|null $member = false, bool $isRegistered = false, bool $fullyBooked = false, Form|null $form = null, EventRegistrationEligibility|null $eligibility = null, EventRegistrationFormFactory|null $formFactory = null, EventRegistrationCreator|null $creator = null, EventDispatcherInterface|null $dispatcher = null, MockObject|null $controllerAdapter = null, LoggerInterface|null $logger = null): RegisterStep
    {
        // false means "a valid member"
        if (false === $member) {
            $member = $this->mockClassWithProperties(MemberModel::class, ['id' => 5]);
        }

        $memberProvider = $this->createMock(LoggedInMemberProvider::class);
        $memberProvider
            ->method('getMember')
            ->willReturn($member)
        ;

        $registrationAdapter = $this->mockAdapter(['isRegistered']);
        $registrationAdapter
            ->method('isRegistered')
            ->willReturn($isRegistered)
        ;

        $messageAdapter = $this->mockAdapter(['add', 'addError', 'hasError', 'hasInfo']);
        $messageAdapter
            ->method('hasError')
            ->willReturn(false)
        ;

        $messageAdapter
            ->method('hasInfo')
            ->willReturn(false)
        ;

        if (null === $formFactory) {
            $formFactory = $this->createMock(EventRegistrationFormFactory::class);
            $formFactory
                ->method('create')
                ->willReturn($form ?? $this->createMock(Form::class))
            ;
        }

        $calendarEventsUtil = $this->createMock(CalendarEventsUtil::class);
        $calendarEventsUtil
            ->method('eventIsFullyBooked')
            ->willReturn($fullyBooked)
        ;

        return new RegisterStep(
            $calendarEventsUtil,
            $this->mockContaoFramework([
                CalendarEventsMemberModel::class => $registrationAdapter,
                Message::class => $messageAdapter,
                Controller::class => $controllerAdapter ?? $this->mockAdapter(['reload']),
            ]),
            $dispatcher ?? $this->createMock(EventDispatcherInterface::class),
            $creator ?? $this->createMock(EventRegistrationCreator::class),
            $eligibility ?? $this->createMock(EventRegistrationEligibility::class),
            $formFactory,
            $memberProvider,
            $this->createMock(TranslatorInterface::class),
            $logger,
        );
    }
}
