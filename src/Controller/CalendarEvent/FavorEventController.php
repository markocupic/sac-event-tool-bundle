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

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/calendar_event/favor_event/{eventId}', name: self::class, defaults: ['_scope' => 'frontend', '_token_check' => true], methods: ['POST'])]
class FavorEventController extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(int $eventId): JsonResponse
    {
        $this->framework->initialize();

        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            $json = ['status' => 'forbidden'];

            return new JsonResponse($json, Response::HTTP_FORBIDDEN);
        }

        $event = CalendarEventsModel::findByPk($eventId);

        if (null === $event) {
            $json = ['status' => 'success', 'message' => 'Event not found.', 'isFavored' => false];

            return new JsonResponse($json, Response::HTTP_NOT_FOUND);
        }

        $id = $this->connection->fetchOne(
            'SELECT id FROM tl_favored_events WHERE eventId = ? AND memberId = ?',
            [$event->id, $user->id],
            [Types::INTEGER, Types::INTEGER],
        );

        if (false !== $id) {
            $json = ['status' => 'success', 'isFavored' => false];

            try {
                $this->connection->delete('tl_favored_events', ['id' => $id], [Types::INTEGER]);
            } catch (Exception $e) {
                $json = ['status' => 'success', 'message' => 'Could not delete event.', 'isFavored' => false];
            }

            return new JsonResponse($json);
        }

        $set = [
            'memberId' => $user->id,
            'eventId' => $event->id,
            'tstamp' => time(),
        ];

        $types = [
            Types::INTEGER,
            Types::INTEGER,
            Types::INTEGER,
        ];

        $json = ['status' => 'success', 'isFavored' => true];

        try {
            $this->connection->insert('tl_favored_events', $set, $types);
        } catch (Exception $e) {
            $json = ['status' => 'success', 'message' => 'Could not favor event.', 'isFavored' => false];
        }

        return new JsonResponse($json);
    }
}
