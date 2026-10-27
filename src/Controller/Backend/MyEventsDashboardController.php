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

namespace Markocupic\SacEventToolBundle\Controller\Backend;

use Codefog\HasteBundle\UrlParser;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperation;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperationsBuilder;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as Twig;

class MyEventsDashboardController
{
    private Adapter $calendarEventsModelAdapter;

    private Adapter $configAdapter;

    private Adapter $stringUtilAdapter;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventOperationsBuilder $eventOperationsBuilder,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly Twig $twig,
        private readonly UrlParser $urlParser,
    ) {
        $this->calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
    }

    public function generate(): Response
    {
        /** @var BackendUser $user */
        $user = $this->security->getUser();

        $limit = (int) $this->requestStack->getCurrentRequest()->query->get('pastEventsLimit', 10);

        if (!$user instanceof BackendUser) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $upcomingEvents = $this->getEvents($user, 'upcoming');
        $pastEvents = $this->getEvents($user, 'past');
        $pastEventsCount = \count($pastEvents);
        $pastEvents = \array_slice($pastEvents, 0, $limit);

        $events = array_merge(
            [['separator' => 'upcoming-events']],
            $this->prepareEventsForView($upcomingEvents, 'upcoming-event'),
            [['separator' => 'past-events']],
            $this->prepareEventsForView($pastEvents, 'past-event'),
        );

        $html = $this->twig->render('@MarkocupicSacEventTool/Backend/MyEventsDashboard/my_events_dashboard.html.twig', [
            'events' => $events,
            'has_upcoming_events' => !empty($upcomingEvents),
            'has_past_events' => !empty($pastEvents),
            'has_load_more_past_events_button' => $pastEventsCount > $limit,
            'load_more_past_events_url' => $pastEventsCount > $limit ? $this->urlParser->addQueryString('pastEventsLimit='.$limit + 10) : null,
        ]);

        return new Response($html);
    }

    /**
     * @throws Exception
     */
    private function getEvents(BackendUser $user, string $timeframe = 'upcoming'): array
    {
        if (!\in_array($timeframe, ['past', 'upcoming'], true)) {
            throw new \InvalidArgumentException('Invalid type! Type must be "upcoming" or "past".');
        }

        $timeCut = time() - 15 * 24 * 3600; // 14 + 1 days

        $arrAllowedCalIds = $this->getAllowedCalendarIds();

        if (empty($arrAllowedCalIds)) {
            return [];
        }

        $sql = match ($timeframe) {
            'upcoming' => 'SELECT * FROM tl_calendar_events AS t1 WHERE pid IN(?) AND (t1.registrationGoesTo = ? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId = ?)) AND t1.startTime > ? ORDER BY t1.startTime',
            'past' => 'SELECT * FROM tl_calendar_events AS t1 WHERE pid IN (?) AND (t1.registrationGoesTo = ? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId = ?)) AND t1.startTime <= ? ORDER BY t1.startTime DESC',
            default => throw new \InvalidArgumentException(\sprintf('Invalid timeframe "%s". Expected "upcoming" or "past".', $timeframe)),
        };

        $result = $this->connection->executeQuery(
            $sql,
            [
                $arrAllowedCalIds,
                $user->id,
                $user->id,
                $timeCut,
            ],
            [
                ArrayParameterType::INTEGER,
                Types::INTEGER,
                Types::INTEGER,
                Types::INTEGER,
            ],
        );

        return $result->fetchAllAssociative();
    }

    private function prepareEventsForView(array $arrEvents, string $rowClass): array
    {
        $events = [];

        foreach ($arrEvents as $row) {
            $eventModel = $this->calendarEventsModelAdapter->findById($row['id']);
            $title = $this->stringUtilAdapter->decodeEntities($eventModel->title);
            $title = $this->stringUtilAdapter->restoreBasicEntities($title);
            $title = empty($title) ? 'Neuer Event' : $title;

            $operations = $this->eventOperationsBuilder->build($eventModel);
            $editOperation = $operations[EventOperationsBuilder::OPERATION_EDIT_EVENT];

            $event = [];
            $event['href_event'] = $editOperation->href;
            $event['title_event'] = $editOperation->title;

            $event['row_class'] = $rowClass;
            $event['badge'] = $this->calendarEventsUtil->getSubscriptionStateBadges($eventModel);
            $event['title'] = $title;
            $event['date'] = date($this->configAdapter->get('dateFormat'), (int) $eventModel->startDate);
            $event['state_icon'] = $this->calendarEventsUtil->getEventStateIcon($eventModel);
            $event['release_level'] = $this->calendarEventsUtil->getEventReleaseLevelAsString($eventModel);
            $event['has_filled_in_tour_report'] = $eventModel->filledInEventReportForm;

            // Get operations (without "edit-event", see href_event)
            $event['operations'] = $this->toDashboardOperations($operations);

            $events[] = $event;
        }

        return $events;
    }

    /**
     * Maps the operations to the keys used in the template ("be-dashb-op--<name>").
     *
     * @param array<string, EventOperation> $operations
     *
     * @return array<string, array<string, mixed>>
     */
    private function toDashboardOperations(array $operations): array
    {
        $dashboardOperations = [];

        foreach (EventOperationsBuilder::MENU_OPERATIONS as $name) {
            $dashboardOperations['be-dashb-op--'.$name] = $operations[$name]->toArray();
        }

        return $dashboardOperations;
    }

    private function getAllowedCalendarContainerIds(): array
    {
        /** @var BackendUser $user */
        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $arrIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_container');
        } else {
            $arrIds = $user->calendar_containers;

            if (!\is_array($arrIds) || empty($arrIds)) {
                $arrIds = [];
            }
        }

        return array_map('\intval', $arrIds);
    }

    private function getAllowedCalendarIds(): array
    {
        $arrCalContainerIds = $this->getAllowedCalendarContainerIds();

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $arrCalendarIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar');
        } else {
            $arrCalendarIds = $user->calendars;

            if (!\is_array($arrCalendarIds) || empty($arrCalendarIds)) {
                $arrCalendarIds = [];
            }
        }

        $arrAllowed = [];

        foreach ($arrCalendarIds as $calId) {
            $pid = $this->connection->fetchOne(
                'SELECT pid FROM tl_calendar WHERE id = ?',
                [$calId],
                [Types::INTEGER],
            );

            if (false !== $pid) {
                if (\in_array($pid, $arrCalContainerIds, true)) {
                    $arrAllowed[] = $calId;
                }
            }
        }

        return array_map('\intval', $arrAllowed);
    }
}
