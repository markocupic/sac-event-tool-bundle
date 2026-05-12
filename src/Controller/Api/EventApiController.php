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

namespace Markocupic\SacEventToolBundle\Controller\Api;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Stopwatch\Stopwatch;

class EventApiController extends AbstractController
{
    public const int CACHE_MAX_AGE = 60;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly Stopwatch $stopwatch,
    ) {
    }

    /**
     * Get event list filtered by params delivered from a filter board This route is
     * used for the vue.js event list module.
     *
     * @throws Exception
     * @throws \Exception
     */
    #[Route('/eventApi/events', name: 'sac_event_tool_api_event_api_get_events', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['GET'])]
    public function getEventList(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);

        // Get query filter params from request
        $params = $this->getQueryParamsFromRequest($request);

        $this->stopwatch->start('event list api query time');

        // Build the first query
        $qb = $this->buildQuery($params);

        /** @var array<int> $ids */
        $ids = $qb->fetchFirstColumn();

        // Now we have all the ids, let's prepare the second query
        $fields = empty($params['fields']) ? [] : $params['fields'];

        $arrJSON = [
            'meta' => [
                'status' => 'success',
                'perPage' => 0,
                'itemsTotal' => \count($ids),
                'queryTime' => '',
                'arrEventIds' => [],
            ],
            'data' => [],
        ];

        if (!empty($ids)) {
            $qb = $this->connection->createQueryBuilder();
            $qb->select('e.*,c.title') // e -> tl_calendar_events, c -> tl_calendar
                ->from('tl_calendar_events', 'e')
                ->join('e', 'tl_calendar', 'c', 'e.pid = c.id')
                ->where($qb->expr()->in('e.id', ':ids'))
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
                ->orderBy('e.startDate', 'ASC')
                ->addOrderBy('e.endDate', 'ASC')
                ->addOrderBy('c.title', 'DESC')
                ->addOrderBy('e.eventType', 'ASC')
                ->addOrderBy('e.id', 'ASC')
            ;

            // Offset
            if ($params['offset'] > 0) {
                $qb->setFirstResult($params['offset']);
            }

            // Limit
            if ($params['limit'] > 0) {
                $qb->setMaxResults($params['limit']);
            }

            $calEvents = $qb->fetchAllAssociative();

            foreach ($calEvents as $calEvent) {
                ++$arrJSON['meta']['perPage'];

                /** @var CalendarEventsModel $objEvent */
                $objEvent = $calendarEventsModel->findById($calEvent['id']);

                if (null !== $objEvent) {
                    $arrJSON['meta']['arrEventIds'][] = $calEvent['id'];

                    $oData = new \stdClass();

                    foreach ($fields as $key) {
                        $value = $this->calendarEventsUtil->getEventData($objEvent, $key);
                        // $key may contain a query string: eventImage?size=5
                        $parts = explode('?', $key, 2);
                        $key = $parts[0];
                        $oData->{$key} = $this->prepareValue($value);
                    }

                    $arrJSON['data'][] = $oData;
                }
            }
        }

        $arrJSON['meta']['queryTime'] = (string) $this->stopwatch->stop('event list api query time');

        $response = new JsonResponse($arrJSON, 200);

        // Enable cache for not logged-in frontend users (guests)
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            $response->setPublic();
            $response->setSharedMaxAge(self::CACHE_MAX_AGE);
            $response->setMaxAge(self::CACHE_MAX_AGE);
        }

        return $response;
    }

    /**
     * This route is used for the "pilatus" export, where events are loaded by xhr
     * when the modal window opens $_POST['id'], $_POST['fields'] as comma-separated
     * string is optional.
     *
     * @throws \Exception
     */
    #[Route('/eventApi/getEventById', name: 'sac_event_tool_api_event_api_get_event_by_id', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['GET'])]
    public function getEventById(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);

        $eventId = (int) $request->query->get('id');

        $toStringArray = static fn (array $v): array => array_values(array_unique(array_filter(array_map('strval', $v))));

        // Arrays -> we cannot use $request->query->get() for non-scalar values
        $fields = $toStringArray($request->query->all('fields'));

        $arrJSON = [
            'status' => 'error',
            'arrEventData' => [],
            'eventId' => $eventId,
            'arrFields' => $fields,
        ];

        $respStatus = 404;

        $objEvent = $calendarEventsModel->findById($eventId);

        if (null !== $objEvent && $objEvent->published) {
            $arrJSON['status'] = 'success';
            $arrEvent = [];

            foreach (array_keys($objEvent->row()) as $k) {
                // If $fields is empty, send all properties
                if (!empty($fields)) {
                    if (!\in_array($k, $fields, true)) {
                        continue;
                    }
                }

                $arrEvent[$k] = $this->prepareValue($this->calendarEventsUtil->getEventData($objEvent, $k));
            }

            $arrJSON['arrEventData'] = $arrEvent;
            $respStatus = 200;
        }

        return new JsonResponse($arrJSON, $respStatus);
    }

    private function getQueryParamsFromRequest(Request $request): array
    {
        $toIntArray = static fn (mixed $v): array => \is_array($v) ? array_map('intval', $v) : [];

        $toStringArray = static fn (array $v): array => array_values(array_unique(array_filter(array_map('strval', $v))));

        return [
            // Arrays -> we cannot use $request->query->get() for non-scalar values
            'organizers' => $toIntArray($request->query->all('organizers')),
            'tourType' => $toIntArray($request->query->all('tourType')),
            'courseType' => $toIntArray($request->query->all('courseType')),
            'calendarIds' => $toIntArray($request->query->all('calendarIds')),
            'arrIds' => $toIntArray($request->query->all('arrIds')),
            'eventType' => $toStringArray($request->query->all('eventType')),
            'fields' => $toStringArray($request->query->all('fields')),
            // Integers
            'offset' => (int) $request->query->get('offset', 0),
            'limit' => (int) $request->query->get('limit', 0),
            // Strings
            'courseId' => $request->query->get('courseId'),
            'eventId' => $request->query->get('eventId'),
            'dateStart' => $request->query->get('dateStart'),
            'dateEnd' => $request->query->get('dateEnd'),
            'textSearch' => $request->query->get('textSearch'),
            'username' => $request->query->get('username'),
            // booleans
            'getUpcoming' => '1' === $request->query->get('getUpcoming'),
            'suitableForBeginners' => '1' === $request->query->get('suitableForBeginners'),
            'publicTransportEvent' => '1' === $request->query->get('publicTransportEvent'),
            'favoredEvent' => '1' === $request->query->get('favoredEvent'),
        ];
    }

    /**
     * Deserialize arrays, convert binary uuids, and clean strings from illegal characters.
     */
    private function prepareValue(mixed $varValue): mixed
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);
        $validator = $this->framework->getAdapter(Validator::class);

        // Transform bin uuids
        $varValue = \is_string($varValue) && $validator->isBinaryUuid($varValue) ? $stringUtil->binToUuid($varValue) : $varValue;

        // Deserialize arrays
        $varValue = $stringUtil->deserialize($varValue);

        // Clean arrays recursively
        if (!empty($varValue) && \is_array($varValue)) {
            $varValue = array_map(fn ($v) => $this->prepareValue($v), $varValue);
        }

        $varValue = \is_string($varValue) && $validator->isBinaryUuid($varValue) ? $stringUtil->binToUuid($varValue) : $varValue;
        $varValue = \is_string($varValue) ? $stringUtil->decodeEntities($varValue) : $varValue;

        return \is_string($varValue) ? mb_scrub($varValue, 'UTF-8') : $varValue;
    }

    private function buildQuery(array $params): QueryBuilder
    {
        // Ignore date range if certain query params were set
        $blnIgnoreDate = false;

        $qb = $this->connection->createQueryBuilder();

        $qb->select('id')
            ->from('tl_calendar_events', 't')
            ->where('t.published = :published')
            ->setParameter('published', 1, Types::INTEGER)
        ;

        // Filter by calendar ids tl_calendar.id
        if (!empty($params['calendarIds'])) {
            $qb->andWhere($qb->expr()->in('t.pid', ':calendarIds'));
            $qb->setParameter('calendarIds', $params['calendarIds'], ArrayParameterType::INTEGER);
        }

        // Filter by event ids tl_calendar_events.id
        if (!empty($params['arrIds'])) {
            $qb->andWhere($qb->expr()->in('t.id', ':arrIds'));
            $qb->setParameter('arrIds', $params['arrIds'], ArrayParameterType::INTEGER);
        }

        // Filter by event types
        if (!empty($params['eventType'])) {
            $qb->andWhere($qb->expr()->in('t.eventType', ':eventType'));
            $qb->setParameter('eventType', $params['eventType'], ArrayParameterType::STRING);
        }

        // Filter by suitableForBeginners
        if ($params['suitableForBeginners']) {
            $qb->andWhere('t.suitableForBeginners = :suitableForBeginners');
            $qb->setParameter('suitableForBeginners', 1, Types::INTEGER);
        }

        // Filter by publicTransportEvent
        if ($params['publicTransportEvent']) {
            $idPublicTransportJourney = $this->connection->fetchOne(
                'SELECT id from tl_calendar_events_journey WHERE alias = :alias',
                ['alias' => 'public-transport'],
                ['alias' => Types::STRING],
            );

            if ($idPublicTransportJourney) {
                $qb->andWhere('t.journey = :publicTransportEvent');
                $qb->setParameter('publicTransportEvent', (int) $idPublicTransportJourney, Types::INTEGER);
            }
        }

        // Filter by a certain instructor $_GET['username']
        if (!empty($params['username'])) {
            $userId = $this->connection->fetchOne(
                'SELECT id FROM tl_user WHERE username = :username',
                ['username' => $params['username']],
                ['username' => Types::STRING],
            );

            if (!$userId) {
                $userId = 0;
            }

            $qb2 = $this->connection->createQueryBuilder();

            $qb2->select('pid')
                ->from('tl_calendar_events_instructor', 't')
                ->where('t.userId = :instructorId')
                ->setParameter('instructorId', $userId, Types::INTEGER)
            ;

            $ids = $qb2->fetchFirstColumn();

            $eventIds = empty($ids) ? [0] : $ids;

            $qb->andWhere($qb->expr()->in('t.id', ':eventIds'));
            $qb->setParameter('eventIds', $eventIds, ArrayParameterType::INTEGER);
        }

        // Show favored events only
        if ($params['favoredEvent']) {
            $user = $this->security->getUser();

            $favoredEventsIds = [0];

            if ($user instanceof FrontendUser) {
                $ids = $this->connection->fetchFirstColumn(
                    'SELECT eventId FROM tl_favored_events WHERE memberId = ?',
                    [$user->id],
                    [Types::INTEGER],
                );

                $favoredEventsIds = empty($ids) ? [0] : $ids;
            }

            $qb->andWhere($qb->expr()->in('t.id', ':favoredEvents'));
            $qb->setParameter('favoredEvents', $favoredEventsIds, ArrayParameterType::INTEGER);
        }

        // Search term (search for expression in tl_calendar_events.title and
        // tl_calendar_events.teaser)
        if (!empty($params['textSearch'])) {
            // Support multiple search terms Only return these events in which each search
            // term (needle) was found.
            $i = 0;

            foreach (explode(' ', $params['textSearch']) as $strNeedle) {
                $orExpressions = [];

                if (empty(trim($strNeedle))) {
                    continue;
                }

                ++$i;

                // Search expression in title & teaser
                $strNeedle = trim($strNeedle);
                $safeNeedle = addcslashes($strNeedle, '%_\\');

                $orExpressions[] = $qb->expr()->like('t.title', ":needle$i");
                $orExpressions[] = $qb->expr()->like('t.teaser', ":needle$i");
                $qb->setParameter("needle$i", '%'.$safeNeedle.'%');

                // Check if search expression is the name of an instructor
                $qbSt = $this->connection->createQueryBuilder();
                $qbSt->select('id')
                    ->from('tl_user', 'u')
                    ->where($qbSt->expr()->like('u.name', ":needleInst$i"))
                    ->setParameter("needleInst$i", '%'.$safeNeedle.'%')
                ;

                $instructorIds = $qbSt->fetchFirstColumn();

                // Check if instructor is the instructor in this event
                foreach ($instructorIds as $instructorId) {
                    $orExpressions[] = $qb->expr()->in(
                        't.id',
                        $this->connection->createQueryBuilder()
                            ->select('pid')
                            ->from('tl_calendar_events_instructor', 't2')
                            ->where('t2.userId = :qbStInstructorId'.$instructorId)
                            ->getSQL(),
                    );
                    $qb->setParameter('qbStInstructorId'.$instructorId, $instructorId, Types::INTEGER);
                }

                if (!empty($orExpressions)) {
                    $qb->andWhere($qb->expr()->or(...$orExpressions));
                }
            }
        }

        // Filter by organizers (multiselect)
        if (!empty($params['organizers']) && \is_array($params['organizers'])) {
            $qbEvtOrg = $this->connection->createQueryBuilder();
            $qbEvtOrg->select('id')
                ->from('tl_event_organizer', 'o')
                ->where('o.ignoreFilterInEventList = :true')
                ->setParameter('true', 1, Types::INTEGER)
            ;

            $ignoredOrganizerIds = $qbEvtOrg->fetchFirstColumn();

            $orExpressions = [];

            // Show event if it has an organizer with the flag ignoreFilterInEventList=true
            if (!empty($ignoredOrganizerIds)) {
                foreach ($ignoredOrganizerIds as $orgId) {
                    $paramName = 'orgIgnored'.(int) $orgId;
                    $orExpressions[] = $qb->expr()->like('t.organizers', ':'.$paramName);
                    $qb->setParameter($paramName, '%:"'.(int) $orgId.'";%');
                }
            }

            // Show event if its organizer is in the search param
            foreach ($params['organizers'] as $orgId) {
                $orgId = (int) $orgId;

                if (!\in_array($orgId, array_map('intval', $ignoredOrganizerIds), true)) {
                    $paramName = 'org'.$orgId;
                    $orExpressions[] = $qb->expr()->like('t.organizers', ':'.$paramName);
                    $qb->setParameter($paramName, '%:"'.$orgId.'";%');
                }
            }

            if (!empty($orExpressions)) {
                $qb->andWhere($qb->expr()->or(...$orExpressions));
            }
        }

        // Filter by tourType (multiselect)
        if (!empty($params['tourType']) && \is_array($params['tourType'])) {
            $orExpressions = [];

            // Show event if its tourType is in the search param
            foreach ($params['tourType'] as $tourTypeId) {
                $tourTypeId = (int) $tourTypeId;
                $paramName = 'tourType'.$tourTypeId;
                $orExpressions[] = $qb->expr()->like('t.tourType', ':'.$paramName);
                $qb->setParameter($paramName, '%:"'.$tourTypeId.'";%');
            }

            if (!empty($orExpressions)) {
                $qb->andWhere($qb->expr()->or(...$orExpressions));
            }
        }

        // Filter by course type (multiselect)
        if (!empty($params['courseType']) && \is_array($params['courseType'])) {
            $courseTypeIds = $params['courseType'];
            $qb->andWhere($qb->expr()->in('t.courseTypeLevel1', ':ids'));
            $qb->setParameter('ids', $courseTypeIds, ArrayParameterType::INTEGER);
        }

        // Filter by course id
        if (!empty($params['courseId'])) {
            $strId = preg_replace('/\s/', '', $params['courseId']);

            if (!empty($strId)) {
                $safeCourseId = addcslashes($strId, '%_\\');
                $qb->andWhere($qb->expr()->like('t.courseId', ':courseId'));
                $qb->setParameter('courseId', '%'.$safeCourseId.'%');
                $blnIgnoreDate = true;
            }
        }

        // Filter by event id
        if (!empty($params['eventId'])) {
            $strId = preg_replace('/\s/', '', $params['eventId']);

            if (preg_match('/^\d+-(\d+)$/', $strId, $matches)) {
                $eventId = (int) $matches[1]; // e.g. "2026-789" → 789
            } elseif (preg_match('/^\d+$/', $strId)) {
                $eventId = (int) $strId; // e.g. "789" → 789
            } else {
                $eventId = 0;
            }

            $qb->andWhere('t.id = :eventId');
            $qb->setParameter('eventId', $eventId, Types::INTEGER);
            $blnIgnoreDate = true;
        }

        if (!$blnIgnoreDate) {
            if ($params['getUpcoming']) {
                $tstampStart = strtotime('today');
                $qb->andWhere($qb->expr()->gte('t.startDate', ':tstampStart'));
                $qb->setParameter('tstampStart', $tstampStart, Types::INTEGER);
            } else {
                if (!empty($params['dateStart']) && (false !== ($tstampStart = strtotime($params['dateStart'])))) {
                    // event filter: date filter
                    $qb->andWhere($qb->expr()->gte('t.endDate', ':tstampStart'));
                    $qb->setParameter('tstampStart', $tstampStart, Types::INTEGER);
                }

                if (!empty($params['dateEnd']) && (false !== ($tstampStop = strtotime($params['dateEnd'])))) {
                    // event filter: date filter
                    $qb->andWhere($qb->expr()->lte('t.endDate', ':tstampStop'));
                    $qb->setParameter('tstampStop', $tstampStop, Types::INTEGER);
                }
            }
        }

        // Order by startDate ASC
        $qb->orderBy('t.startDate', 'ASC');

        return $qb;
    }
}
