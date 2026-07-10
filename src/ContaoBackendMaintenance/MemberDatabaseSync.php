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

namespace Markocupic\SacEventToolBundle\ContaoBackendMaintenance;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\MaintenanceModuleInterface;
use Markocupic\SacEventToolBundle\Database\SyncMember\SyncLogger;
use Markocupic\SacEventToolBundle\Database\SyncMemberDatabase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Twig\Environment;

#[AsController]
readonly class MemberDatabaseSync implements MaintenanceModuleInterface
{
    public function __construct(
        private ContaoCsrfTokenManager $contaoCsrToken,
        private Environment $twig,
        private RequestStack $requestStack,
        private SyncMemberDatabase $syncMemberDatabase,
    ) {
    }

    /**
     * Return true if the module is active.
     */
    public function isActive(): bool
    {
        return 'memberDatabaseSync' === $this->getRequest()->get('act');
    }

    /**
     * Generate the module.
     */
    public function run(): string
    {
        if (!$this->isActive()) {
            return $this->twig->render('@MarkocupicSacEventTool/Maintenance/member_database_sync.html.twig', [
                'is_active' => false,
                'is_running' => false,
                'csrf_token' => $this->contaoCsrToken->getDefaultTokenValue(),
            ]);
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $syncLogger = new SyncLogger();
            $this->syncMemberDatabase->run($syncLogger);
            $arrSyncLog = $syncLogger->toArray();
            $response = new JsonResponse($arrSyncLog);

            throw new ResponseException($response);
        }

        return $this->twig->render('@MarkocupicSacEventTool/Maintenance/member_database_sync.html.twig', [
            'is_active' => true,
            'is_running' => true,
        ]);
    }

    private function getRequest(): Request
    {
        return $this->requestStack->getCurrentRequest();
    }
}
