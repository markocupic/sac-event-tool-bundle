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
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler\LoginStep;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class LoginStepTest extends ContaoTestCase
{
    public function testStaticMetaData(): void
    {
        $this->assertSame('login', LoginStep::getName());
        $this->assertSame(300, LoginStep::getPriority());
        $this->assertStringContainsString('login.html.twig', $this->createLoginStep()->getTemplateName());
    }

    public function testDoAutoForwardIsAlwaysTrue(): void
    {
        $step = $this->createLoginStep();

        $this->assertTrue($step->doAutoForward(
            $this->createMock(CalendarEventsModel::class),
            new Request(),
            $this->createMock(ModuleModel::class),
        ));
    }

    public function testValidateReturnsTrueForAFrontendUser(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn($this->createMock(FrontendUser::class))
        ;

        $step = $this->createLoginStep($security);

        $this->assertTrue($step->validate(
            $this->createMock(CalendarEventsModel::class),
            new Request(),
            $this->createMock(ModuleModel::class),
        ));
    }

    public function testValidateReturnsFalseWhenNobodyIsLoggedIn(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn(null)
        ;

        $step = $this->createLoginStep($security);

        $this->assertFalse($step->validate(
            $this->createMock(CalendarEventsModel::class),
            new Request(),
            $this->createMock(ModuleModel::class),
        ));
    }

    public function testValidateReturnsFalseForANonFrontendUser(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn($this->createMock(UserInterface::class))
        ;

        $step = $this->createLoginStep($security);

        $this->assertFalse($step->validate(
            $this->createMock(CalendarEventsModel::class),
            new Request(),
            $this->createMock(ModuleModel::class),
        ));
    }

    public function testPrepareStepExposesTheEventAndModuleModel(): void
    {
        $event = $this->createMock(CalendarEventsModel::class);
        $event
            ->method('current')
            ->willReturnSelf()
        ;

        $module = $this->createMock(ModuleModel::class);
        $module
            ->method('current')
            ->willReturnSelf()
        ;

        $result = $this->createLoginStep()->prepareStep($event, new Request(), $module);

        $this->assertSame($event, $result['event_model']);
        $this->assertSame($module, $result['module_model']);
    }

    private function createLoginStep(Security|null $security = null): LoginStep
    {
        return new LoginStep($security ?? $this->createMock(Security::class));
    }
}
