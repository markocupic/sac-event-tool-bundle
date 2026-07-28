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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration;

use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler\StepHandlerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

readonly class StepManager
{
    public function __construct(
        #[AutowireLocator('sacevt.event_registration.step_handler', defaultIndexMethod: 'getName', defaultPriorityMethod: 'getPriority')]
        private ContainerInterface $stepHandlers,
    ) {
    }

    public function getSteps(): array
    {
        return $this->stepHandlers->getProvidedServices();
    }

    public function getStep(string $action): StepHandlerInterface
    {
        if (!$this->stepHandlers->has($action)) {
            $action = array_key_first($this->getSteps());
        }

        if (null === $action) {
            throw new \LogicException('No event registration step handler has been registered. At least one service tagged "sacevt.event_registration.step_handler" is required.');
        }

        return $this->stepHandlers->get($action);
    }

    public function getNextStep(StepHandlerInterface $step): StepHandlerInterface
    {
        $stop = false;

        foreach (array_keys($this->getSteps()) as $stepType) {
            if ($stop) {
                return $this->stepHandlers->get($stepType);
            }

            if ($step::getName() === $stepType) {
                $stop = true;
            }
        }

        return $step;
    }

    public function getPreviousSteps(StepHandlerInterface $step): array
    {
        $previousSteps = [];

        foreach (array_keys($this->getSteps()) as $stepType) {
            if ($step::getName() === $stepType) {
                break;
            }

            $previousSteps[] = $this->stepHandlers->get($stepType);
        }

        return $previousSteps;
    }
}
