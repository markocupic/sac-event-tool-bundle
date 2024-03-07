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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\CoreBundle\Controller\BackendController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Template;
use Markocupic\SacEventToolBundle\Controller\BackendHomeScreen\DashboardController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ParseTemplateListener
{
    public function __construct(
        private readonly DashboardController $dashboard,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsHook('parseTemplate', priority: 100)]
    public function addDashboardToTheWelcomePage(Template $template): void
    {
        // List upcoming and past events on the backend home screen.
        $request = $this->requestStack->getCurrentRequest();

        // Do not show the dashboard when using custom routes/controllers
        if ($request->attributes->get('_controller') !== BackendController::class.'::mainAction') {
            return;
        }

        if (!str_starts_with($template->getName(), 'be_main')) {
            return;
        }

        if ($request->query->has('mtg') || $request->query->has('error') || $request->query->has('do') || $request->query->has('act')) {
            return;
        }

        if (!$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'calendar')) {
            return;
        }

        // Generate the dashboard
        $dashboardMarkup = $this->dashboard->generate()->getContent();

        // Inject the dashboard before the shortcuts, but after system messages
        $template->main = str_replace('<div id="tl_shortcuts">', $dashboardMarkup.'<div id="tl_shortcuts">', $template->main);
    }
}
