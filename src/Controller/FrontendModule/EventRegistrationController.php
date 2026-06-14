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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler\StepHandlerInterface;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler\ValidationStepInterface;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

#[AsFrontendModule(EventRegistrationController::TYPE, category: 'sac_event_tool_frontend_modules')]
class EventRegistrationController extends AbstractFrontendModuleController
{
    public const string TYPE = 'event_registration';

    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly StepManager $stepManager,
        private readonly TwigEnvironment $twig,
        private readonly UrlParser $urlParser,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        if ($this->scopeMatcher->isFrontendRequest($request)) {
            // Do not index nor cache this page.
            if (null !== $page) {
                $page->noSearch = true;
                $page->cache = false;
                $page->clientCache = false;
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Resolve the event model from URL parameters.
        $eventModel = $this->getEventModel();

        if (null === $eventModel) {
            throw new PageNotFoundException('No valid event id/alias could be found in the url parameters.');
        }

        $step = $this->stepManager->getStep($request->query->get('action', ''));

        if ($url = $this->getStepUrlIfMiss($step, $request)) {
            return $this->redirect($url);
        }

        // Validate all previous steps and redirect to the first invalid one.
        /** @var StepHandlerInterface $previousStep */
        foreach ($this->stepManager->getPreviousSteps($step) as $previousStep) {
            if ($previousStep instanceof ValidationStepInterface && !$previousStep->validate($eventModel, $request, $model)) {
                return $this->redirectToStep($previousStep, $request);
            }
        }

        $isValid = !$step instanceof ValidationStepInterface || $step->validate($eventModel, $request, $model);

        if ($isValid && $step->doAutoForward($eventModel, $request, $model)) {
            $nextStep = $this->stepManager->getNextStep($step);
            if ($nextStep::getName() !== $step::getName()) {
                return $this->redirectToStep($nextStep, $request);
            }
        }

        $template->set('stepType', $step::getName());
        $template->set('stepIndicator', $this->renderStepIndicatorResponse($step, $isValid, $eventModel, $request, $model)->getContent());
        $template->set('step', $this->renderStepResponse($step, $eventModel, $request, $model)->getContent());

        return $template->getResponse();
    }

    private function redirectToStep(StepHandlerInterface $step, Request $request): RedirectResponse
    {
        $url = $this->urlParser->addQueryString('action='.$step::getName(), $request->getUri());

        return $this->redirect($url);
    }

    private function getEventModel(): CalendarEventsModel|null
    {
        // The auto_item parameter must be read via Contao\Input.
        // Contao’s frontend request handling relies on its own Input system when
        // validating reader URLs (items/auto_item). If the parameter is only accessed
        // through the Symfony Request object, Contao may not recognize it as being
        // used within the reader context and can later trigger a PageNotFoundException.
        $eventIdOrAlias = (string) $this->getContaoAdapter(Input::class)->get('auto_item');

        if ('' === $eventIdOrAlias) {
            return null;
        }

        return $this->getContaoAdapter(CalendarEventsModel::class)->findByIdOrAlias($eventIdOrAlias);
    }

    private function getStepUrlIfMiss(StepHandlerInterface $stepHandler, Request $request): string|null
    {
        if ($request->query->get('action') !== $stepHandler::getName()) {
            $url = $this->urlParser->addQueryString('action='.$stepHandler::getName(), $request->getUri());

            // Only redirect if the URL actually changed — guards against an infinite
            // redirect loop if addQueryString fails to inject the parameter.
            if ($url !== $request->getUri()) {
                return $url;
            }
        }

        return null;
    }

    private function renderStepResponse(StepHandlerInterface $step, CalendarEventsModel $eventModel, Request $request, ModuleModel $model): Response
    {
        return new Response(
            $this->twig->render(
                $step->getTemplateName(),
                $step->prepareStep($eventModel, $request, $model),
            ),
        );
    }

    private function renderStepIndicatorResponse(StepHandlerInterface $currentStep, bool $currentStepIsValid, CalendarEventsModel $eventModel, Request $request, ModuleModel $model): Response
    {
        $items = [];
        $reachedCurrent = false;

        foreach (array_keys($this->stepManager->getSteps()) as $stepName) {
            $step = $this->stepManager->getStep($stepName);
            $isCurrent = $step::getName() === $currentStep::getName();

            if ($isCurrent) {
                $isValid = $currentStepIsValid;
                $reachedCurrent = true;
            } elseif (!$reachedCurrent && $step instanceof ValidationStepInterface) {
                $isValid = $step->validate($eventModel, $request, $model);
            } else {
                $isValid = false;
            }

            $items[] = [
                'name' => $step::getName(),
                'is_current' => $isCurrent,
                'is_future' => $reachedCurrent && !$isCurrent,
                'is_valid' => $isValid,
            ];
        }

        return new Response(
            $this->twig->render(
                '@Contao_MarkocupicSacEventToolBundle/frontend_module/partials/event_registration/step_indicator.html.twig',
                ['items' => $items],
            ),
        );
    }
}
