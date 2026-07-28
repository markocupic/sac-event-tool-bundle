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

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler\ConfirmStep;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConfirmStepTest extends ContaoTestCase
{
    public function testStaticMetaData(): void
    {
        $this->assertSame('confirm', ConfirmStep::getName());
        $this->assertSame(100, ConfirmStep::getPriority());
        $this->assertStringContainsString('confirm.html.twig', $this->createConfirmStep()->getTemplateName());
    }

    public function testDoAutoForwardIsAlwaysFalse(): void
    {
        $this->assertFalse($this->createConfirmStep()->doAutoForward(
            $this->createMock(CalendarEventsModel::class),
            new Request(),
            $this->createMock(ModuleModel::class),
        ));
    }

    public function testPrepareStepThrowsWhenNoUserIsLoggedIn(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn(null)
        ;

        $step = $this->createConfirmStep(security: $security);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('No logged in user found.');

        $step->prepareStep($this->createMock(CalendarEventsModel::class), new Request(), $this->createMock(ModuleModel::class));
    }

    public function testPrepareStepThrowsWhenTheMemberCannotBeResolved(): void
    {
        $memberAdapter = $this->mockAdapter(['findById']);
        $memberAdapter
            ->method('findById')
            ->willReturn(null)
        ;

        $step = $this->createConfirmStep(
            framework: $this->mockContaoFramework([MemberModel::class => $memberAdapter]),
            security: $this->mockSecurityWithFrontendUser(),
        );

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/could not be matched to a member record/');

        $step->prepareStep($this->createMock(CalendarEventsModel::class), new Request(), $this->createMock(ModuleModel::class));
    }

    public function testPrepareStepThrowsWhenNoRegistrationExists(): void
    {
        $memberAdapter = $this->mockAdapter(['findById']);
        $memberAdapter
            ->method('findById')
            ->willReturn($this->mockClassWithProperties(MemberModel::class, ['id' => 5]))
        ;

        $registrationAdapter = $this->mockAdapter(['findByMemberAndEvent']);
        $registrationAdapter
            ->method('findByMemberAndEvent')
            ->willReturn(null)
        ;

        $step = $this->createConfirmStep(
            framework: $this->mockContaoFramework([
                MemberModel::class => $memberAdapter,
                CalendarEventsMemberModel::class => $registrationAdapter,
            ]),
            security: $this->mockSecurityWithFrontendUser(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No registration found/');

        $step->prepareStep($this->mockClassWithProperties(CalendarEventsModel::class, ['id' => 1]), new Request(), $this->createMock(ModuleModel::class));
    }

    public function testPrepareStepReturnsEventAndRegistrationData(): void
    {
        $event = $this->createMock(CalendarEventsModel::class);
        $event
            ->method('row')
            ->willReturn(['id' => 1, 'title' => 'Wanderung'])
        ;

        $registration = $this->createMock(CalendarEventsMemberModel::class);
        $registration
            ->method('row')
            ->willReturn(['stateOfSubscription' => 'subscription-accepted'])
        ;

        $memberAdapter = $this->mockAdapter(['findById']);
        $memberAdapter
            ->method('findById')
            ->willReturn($this->mockClassWithProperties(MemberModel::class, ['id' => 5]))
        ;

        $registrationAdapter = $this->mockAdapter(['findByMemberAndEvent']);
        $registrationAdapter
            ->method('findByMemberAndEvent')
            ->willReturn($registration)
        ;

        $urlGenerator = $this->createMock(ContentUrlGenerator::class);
        $urlGenerator
            ->method('generate')
            ->willReturn('https://example.com/event/1')
        ;

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturn('Angenommen')
        ;

        $step = $this->createConfirmStep(
            framework: $this->mockContaoFramework([
                MemberModel::class => $memberAdapter,
                CalendarEventsMemberModel::class => $registrationAdapter,
            ]),
            urlGenerator: $urlGenerator,
            security: $this->mockSecurityWithFrontendUser(),
            translator: $translator,
        );

        $result = $step->prepareStep($event, new Request(), $this->createMock(ModuleModel::class));

        $this->assertSame('https://example.com/event/1', $result['event_data']['eventUrl']);
        $this->assertSame('Wanderung', $result['event_data']['title']);
        $this->assertSame('subscription-accepted', $result['event_registration_data']['stateOfSubscription']);
        $this->assertSame('Angenommen', $result['event_registration_data']['stateOfSubscriptionTrans']);
    }

    private function mockSecurityWithFrontendUser(): Security&MockObject
    {
        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn($this->mockClassWithProperties(FrontendUser::class, ['id' => 5]))
        ;

        return $security;
    }

    private function createConfirmStep(ContaoFramework|null $framework = null, ContentUrlGenerator|null $urlGenerator = null, Security|null $security = null, TranslatorInterface|null $translator = null): ConfirmStep
    {
        return new ConfirmStep(
            $framework ?? $this->mockContaoFramework(),
            $urlGenerator ?? $this->createMock(ContentUrlGenerator::class),
            $security ?? $this->createMock(Security::class),
            $translator ?? $this->createMock(TranslatorInterface::class),
        );
    }
}
