<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\PageModel;
use Markocupic\SacEventToolBundle\Controller\CalendarEvent\PurgeEventFavoritesController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsContentElement(PurgeEventFavoritesLinkController::TYPE, category:'sac_event_tool_content_elements', template:'ce_purge_event_favorites_link')]
class PurgeEventFavoritesLinkController extends AbstractContentElementController
{
    public const TYPE = 'purge_event_favorites_link';

    public function __construct(
        private readonly Security $security,
        private readonly RouterInterface $router,
    ) {
    }

    public function __invoke(Request $request, ContentModel $model, string $section, array|null $classes = null, PageModel|null $pageModel = null): Response
    {
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            $url = $this->router->generate(PurgeEventFavoritesController::class, [], UrlGeneratorInterface::ABSOLUTE_URL);
            $template->set('link', $url);
            $template->set('user', $user->getData());
        }

        return $template->getResponse();
    }
}
