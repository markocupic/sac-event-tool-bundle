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
use Markocupic\SacEventToolBundle\Database\SyncEventRegistrationDatabase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[Route('%contao.backend.route_prefix%/sync_event_registration_database', name: self::class, defaults: ['_scope' => 'backend', '_token_check' => true])]
class SyncEventRegistrationDatabaseController extends AbstractBackendController
{
    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly RouterInterface $router,
        private readonly Security $security,
        private readonly SyncEventRegistrationDatabase $syncEventRegistrationDatabase,
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

        if ('sync_event_registration_database' === $request->request->get('FORM_SUBMIT')) {
            $response = $this->syncEventRegistrationDatabase->run();

            $result = json_decode($response->getContent(), true);
        }

        return $this->render('@MarkocupicSacEventTool/Backend/CustomRoutes/be_sync_event_registration_database.html.twig', [
            'rt' => $this->csrfTokenManager->getDefaultTokenValue(),
            'headline' => 'Benutzerdaten in der Registrierungsdatenbank updaten',
            'result' => !empty($result) ? json_encode($result) : null,
        ]);
    }
}
