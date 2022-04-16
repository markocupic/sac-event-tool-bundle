<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule(EventRegistrationCheckoutLinkController::TYPE, category="sac_event_tool_frontend_modules")
 */
class EventRegistrationCheckoutLinkController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_registration_checkout_link';

    private ContaoFramework $framework;
    private ScopeMatcher $scopeMatcher;
    private ?PageModel $objJumpTo = null;
    private ?CalendarEventsModel $objEvent = null;

    public function __construct(ContaoFramework $framework, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);
        $pageModelAdapter = $this->get('contao.framework')->getAdapter(PageModel::class);
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        // Set the item from the auto_item parameter
        if (!isset($_GET['events']) && $configAdapter->get('useAutoItem') && isset($_GET['auto_item'])) {
            $inputAdapter->setGet('events', $inputAdapter->get('auto_item'));
        }

        $this->objEvent = $calendarEventsModelAdapter->findByIdOrAlias($inputAdapter->get('events'));

        $this->objJumpTo = $pageModelAdapter->findPublishedById($model->eventRegCheckoutLinkPage);

        if ($request && $this->scopeMatcher->isFrontendRequest($request) && (!$this->objEvent || !$this->objJumpTo)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        $params = '/'.($configAdapter->get('useAutoItem') ? '' : 'events/').$this->objEvent->alias;

        $template->jumpTo = $this->objJumpTo->getFrontendUrl($params);

        $template->btnLbl = $model->eventRegCheckoutLinkLabel;

        return $template->getResponse();
    }
}
