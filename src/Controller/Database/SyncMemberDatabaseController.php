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

namespace Markocupic\SacEventToolBundle\Controller\Database;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Markocupic\SacEventToolBundle\Database\SyncMemberDatabase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Mirror/Update tl_member from SAC Member Database Zentralverband Bern
 * Unidirectional sync
 * SAC Member Database Zentralverband Bern -> tl_member.
 */
#[Route('%contao.backend.route_prefix%/sync_sac_member_database', name: self::class, defaults: ['_scope' => 'backend', '_token_check' => true])]
class SyncMemberDatabaseController extends AbstractBackendController
{
    public function __construct(
        private readonly SyncMemberDatabase $syncMemberDatabase,
        private readonly Security $security,
        private readonly RouterInterface $router,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            $url = $this->router->generate(
                'contao_backend_login',
                ['redirect' => urlencode($request->getUri())],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            return $this->redirect($url);
        }

        if ('sync_member_database' === $request->request->get('FORM_SUBMIT')) {
            // Run database sync
            $this->syncMemberDatabase->run();

            // Get the log
            $arrLog = $this->syncMemberDatabase->getSyncLog();

            $arrResult = [
                'message' => 'Successfully executed the db sync.',
                'processed' => $arrLog['processed'],
                'inserts' => $arrLog['inserts'],
                'updates' => $arrLog['updates'],
                'log' => $arrLog['log'],
                'duration' => $arrLog['duration'].' s',
                'with_error' => $arrLog['with_error'],
                'exception' => $arrLog['exception'],
            ];
        }

        return $this->render('@MarkocupicSacEventTool/Backend/CustomRoutes/be_sync_member_database.html.twig', [
            'rt' => $this->csrfTokenManager->getDefaultTokenValue(),
            'headline' => 'Contao Mitgliederdatenbank mit Mitgliederdatenbank Zentralverband abgleichen',
            'result' => !empty($arrResult) ? json_encode($arrResult) : null,
        ]);
    }
}
