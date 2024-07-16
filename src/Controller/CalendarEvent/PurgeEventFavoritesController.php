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

namespace Markocupic\SacEventToolBundle\Controller\CalendarEvent;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/calendar_event/purge_event_favorites', name: self::class, defaults: ['_scope' => 'frontend'], methods: ['GET'])]
class PurgeEventFavoritesController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            $json = ['status' => 'forbidden'];

            return new JsonResponse($json, Response::HTTP_FORBIDDEN);
        }

        $this->connection->delete('tl_favored_events', ['memberId' => $user->id], [Types::INTEGER]);

        $json = ['status' => 'success'];

        return new JsonResponse($json);
    }
}
