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

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsFrontendModule(EventRegistrationCheckoutLinkController::TYPE, category: 'sac_event_tool_frontend_modules', template: 'mod_event_registration_checkout_link')]
final class EventRegistrationCheckoutLinkController extends AbstractFrontendModuleController
{
    public const string TYPE = 'event_registration_checkout_link';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly PageRegistry $pageRegistry,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Get the alias from auto_item
        $eventAlias = $this->framework->getAdapter(Input::class)->get('auto_item');

        if (empty($eventAlias)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $calEvent = $this->framework->getAdapter(CalendarEventsModel::class)->findByIdOrAlias($eventAlias);

        if (null === $calEvent) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $jumpToPage = $this->framework->getAdapter(PageModel::class)->findPublishedById($model->eventRegCheckoutLinkPage);

        if (null === $jumpToPage) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $urlParams = '/'.$calEvent->alias;

        $template->set('jumpTo', $this->getFrontendUrl($jumpToPage, $urlParams));
        $template->set('btnLbl', $model->eventRegCheckoutLinkLabel);

        return $template->getResponse();
    }

    private function getFrontendUrl(PageModel $pageModel, string $urlParams): string
    {
        $pageModel->loadDetails();

        try {
            $url = $this->contentUrlGenerator->generate($pageModel, ['parameters' => $urlParams], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException $e) {
            if (!$this->pageRegistry->isRoutable($pageModel)) {
                throw new ResourceNotFoundException(\sprintf('Page ID %s is not routable', $pageModel->id), 0, $e);
            }

            throw $e;
        }

        return $url;
    }
}
