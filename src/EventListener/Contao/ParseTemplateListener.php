<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Controller\BackendHomeScreen\DashboardController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsHook('parseTemplate', priority: 100)]
class ParseTemplateListener
{
    public function __construct(
        private readonly DashboardController $dashboard,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(Template $template): void
    {
        // List upcoming and past events on the backend home screen.
        $request = $this->requestStack->getCurrentRequest();

        // Do not show the dashboard when using custom routes/controllers
        if ($request->attributes->get('_controller') === BackendController::class.'::mainAction') {
            if (str_starts_with($template->getName(), 'be_main')) {
                if (!$request->query->has('mtg') && !$request->query->has('error') && !$request->query->has('do') && !$request->query->has('act')) {
                    if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'sac_calendar_events_tool')) {
                        $template->main = $this->dashboard->generate()->getContent().$template->main;
                    }
                }
            }
        }
    }
}
