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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(EventRegistrationCheckoutLinkController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_event_registration_checkout_link')]
class EventRegistrationCheckoutLinkController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_registration_checkout_link';

    private PageModel|null $objJumpTo = null;
    private CalendarEventsModel|null $objEvent = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        // Get the alias from auto_item
        $eventAlias = $inputAdapter->get('auto_item');

        if ($this->scopeMatcher->isFrontendRequest($request) && empty($eventAlias)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $this->objEvent = $calendarEventsModelAdapter->findByIdOrAlias($eventAlias);
        $this->objJumpTo = $pageModelAdapter->findPublishedById($model->eventRegCheckoutLinkPage);

        if ($this->scopeMatcher->isFrontendRequest($request) && (null === $this->objEvent || null === $this->objJumpTo)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $params = '/'.$this->objEvent->alias;

        $template->set('jumpTo', $this->objJumpTo->getFrontendUrl($params));
        $template->set('btnLbl', $model->eventRegCheckoutLinkLabel);

        return $template->getResponse();
    }
}
