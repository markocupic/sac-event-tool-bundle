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

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Controller\BackendWelcomePage\DashboardController;
use Symfony\Component\Security\Core\Security;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * List all upcoming and past events (where logged in backend user is the main instructor).
 */
#[AsHook('getSystemMessages', priority: 100)]
class GetSystemMessagesListener
{
    public function __construct(
        private readonly DashboardController $dashboard,
        private readonly Security $security,
    ) {
    }

    /**
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(): string
    {
        if ($this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'sac_calendar_events_tool')) {
            return $this->dashboard->generate()->getContent();
        }

        return '';
    }
}
