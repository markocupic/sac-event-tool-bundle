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
use Contao\ModuleModel;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler\StepHandlerInterface;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ServiceProviderInterface;

class StepManagerTest extends TestCase
{
    public function testGetStepsReturnsTheProvidedServices(): void
    {
        $providedServices = ['a' => StepA::class, 'b' => StepB::class, 'c' => StepC::class];

        $manager = $this->createStepManager($providedServices);

        $this->assertSame($providedServices, $manager->getSteps());
    }

    public function testGetStepReturnsTheRequestedStep(): void
    {
        $manager = $this->createStepManager();

        $this->assertInstanceOf(StepB::class, $manager->getStep('b'));
    }

    public function testGetStepFallsBackToTheFirstStepForAnUnknownAction(): void
    {
        $manager = $this->createStepManager();

        // 'unknown' is not registered => first registered step ('a') is returned.
        $this->assertInstanceOf(StepA::class, $manager->getStep('unknown'));
    }

    public function testGetStepThrowsWhenNoHandlerIsRegistered(): void
    {
        $manager = $this->createStepManager([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/No event registration step handler/');

        $manager->getStep('anything');
    }

    public function testGetNextStepReturnsTheFollowingStep(): void
    {
        $manager = $this->createStepManager();

        $this->assertInstanceOf(StepB::class, $manager->getNextStep(new StepA()));
    }

    public function testGetNextStepReturnsTheSameStepForTheLastStep(): void
    {
        $manager = $this->createStepManager();

        $last = new StepC();

        // There is no step after the last one, so the same step is returned.
        $this->assertSame($last, $manager->getNextStep($last));
    }

    public function testGetPreviousStepsReturnsAnEmptyArrayForTheFirstStep(): void
    {
        $manager = $this->createStepManager();

        $this->assertSame([], $manager->getPreviousSteps(new StepA()));
    }

    public function testGetPreviousStepsReturnsAllStepsBeforeTheGivenStep(): void
    {
        $manager = $this->createStepManager();

        $previous = $manager->getPreviousSteps(new StepC());

        $this->assertCount(2, $previous);
        $this->assertInstanceOf(StepA::class, $previous[0]);
        $this->assertInstanceOf(StepB::class, $previous[1]);
    }

    /**
     * @param array<string, class-string<StepHandlerInterface>>|null $providedServices
     */
    private function createStepManager(array|null $providedServices = null): StepManager
    {
        $providedServices ??= ['a' => StepA::class, 'b' => StepB::class, 'c' => StepC::class];

        $instances = [
            'a' => new StepA(),
            'b' => new StepB(),
            'c' => new StepC(),
        ];

        // The step handlers are injected as an autowired service locator, which
        // implements ServiceProviderInterface (adds getProvidedServices()).
        $container = $this->createMock(ServiceProviderInterface::class);
        $container
            ->method('getProvidedServices')
            ->willReturn($providedServices)
        ;

        $container
            ->method('has')
            ->willReturnCallback(static fn (string $id): bool => isset($providedServices[$id]))
        ;

        $container
            ->method('get')
            ->willReturnCallback(static fn (string $id): StepHandlerInterface => $instances[$id])
        ;

        return new StepManager($container);
    }
}

/**
 * Minimal step handler stub used to exercise StepManager without the DI container.
 */
class StepA implements StepHandlerInterface
{
    public static function getName(): string
    {
        return 'a';
    }

    public static function getPriority(): int
    {
        return 300;
    }

    public function getTemplateName(): string
    {
        return 'template_a';
    }

    public function doAutoForward(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool
    {
        return true;
    }

    public function prepareStep(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): array
    {
        return [];
    }
}

class StepB implements StepHandlerInterface
{
    public static function getName(): string
    {
        return 'b';
    }

    public static function getPriority(): int
    {
        return 200;
    }

    public function getTemplateName(): string
    {
        return 'template_b';
    }

    public function doAutoForward(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool
    {
        return true;
    }

    public function prepareStep(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): array
    {
        return [];
    }
}

class StepC implements StepHandlerInterface
{
    public static function getName(): string
    {
        return 'c';
    }

    public static function getPriority(): int
    {
        return 100;
    }

    public function getTemplateName(): string
    {
        return 'template_c';
    }

    public function doAutoForward(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool
    {
        return false;
    }

    public function prepareStep(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): array
    {
        return [];
    }
}
