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

namespace Markocupic\SacEventToolBundle\Controller\Api;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Stopwatch\Stopwatch;

class EventApiController extends AbstractController
{
    public const CACHE_MAX_AGE = 300;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Get event list filtered by params delivered from a filter board
     * This route is used for the vue.js event list module.
     *
     * @throws Exception
     * @throws \Exception
     */
    #[Route('/eventApi/events', name: 'sac_event_tool_api_event_api_get_events', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['GET'])]
    public function getEventList(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);

        // Get query filter params from request
        $params = $this->getQueryParamsFromRequest($request);

        $stopwatch = new Stopwatch();
        $stopwatch->start('event list api query time');

        // Build the first query
        $qb = $this->buildQuery($this->connection, $params);

        /** @var array<int> $arrIds */
        $arrIds = $qb->fetchFirstColumn();

        // Now we have all the ids, let's prepare the second query
        $arrFields = empty($params['fields']) ? [] : $params['fields'];

        $arrJSON = [
            'meta' => [
                'status' => 'success',
                'countItems' => 0,
                'itemsTotal' => \count($arrIds),
                'queryTime' => '',
                'arrEventIds' => [],
                'sql' => $qb->getSQL(),
                'params' => $qb->getParameters(),
            ],
            'data' => [],
        ];

        if (!empty($arrIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb->select('*')
                ->from('tl_calendar_events', 't')
                ->where($qb->expr()->in('t.id', ':ids'))
                ->setParameter('ids', $arrIds, ArrayParameterType::INTEGER)
                ->orderBy('t.startDate', 'ASC')
            ;

            // Offset
            if ($params['offset'] > 0) {
                $qb->setFirstResult($params['offset']);
            }

            // Limit
            if ($params['limit'] > 0) {
                $qb->setMaxResults($params['limit']);
            }

            $results = $qb->executeQuery();

            while (false !== ($arrEvent = $results->fetchAssociative())) {
                ++$arrJSON['meta']['countItems'];
                $oData = null;

                /** @var CalendarEventsModel $objEvent */
                $objEvent = $calendarEventsModel->findByPk($arrEvent['id']);

                if (null !== $objEvent) {
                    $arrJSON['meta']['arrEventIds'][] = $arrEvent['id'];

                    if (null === $oData) {
                        $oData = new \stdClass();

                        foreach ($arrFields as $field) {
                            $v = $calendarEventsHelper->getEventData($objEvent, $field);
                            $aField = explode('||', $field);
                            $field = $aField[0];
                            $oData->{$field} = $this->prepareValue($v);
                        }
                    }

                    $arrJSON['data'][] = $oData;
                }
            }
        }

        $arrJSON['meta']['queryTime'] = (string) $stopwatch->stop('event list api query time');

        // Allow cross domain requests
        $response = new JsonResponse($arrJSON, 200, ['Access-Control-Allow-Origin' => '*']);

        // Enable cache
        $response->setPublic();
        $response->setSharedMaxAge(self::CACHE_MAX_AGE);
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE - 10);

        return $response;
    }

    /**
     * This route is used for the "pilatus" export, where events are loaded by xhr when the modal window opens
     * $_POST['id'], $_POST['fields'] as comma separated string is optional.
     *
     * @throws \Exception
     */
    #[Route('/eventApi/getEventById', name: 'sac_event_tool_api_event_api_get_event_by_id', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['GET'])]
    public function getEventById(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);

        $eventId = (int) $request->request->get('id');
        $arrFields = '' !== $request->get('fields') ? explode(',', $request->get('fields')) : [];

        $arrJSON = [
            'status' => 'error',
            'arrEventData' => '',
            'eventId' => $eventId,
            'arrFields' => $arrFields,
        ];

        if (null !== ($objEvent = $calendarEventsModel->findByPk($eventId))) {
            $arrJSON['status'] = 'success';
            $arrEvent = [];

            foreach (array_keys($objEvent->row()) as $k) {
                // If $arrFields is empty send all properties
                if (!empty($arrFields)) {
                    if (!\in_array($k, $arrFields, true)) {
                        continue;
                    }
                }

                $arrEvent[$k] = $this->prepareValue($calendarEventsHelper->getEventData($objEvent, $k));
            }
            $arrJSON['arrEventData'] = $arrEvent;
        }

        // Allow cross domain requests
        return new JsonResponse($arrJSON, 200, ['Access-Control-Allow-Origin' => '*']);
    }

    private function getQueryParamsFromRequest(Request $request): array
    {
        return [
            // Arrays
            'organizers' => $request->get('organizers'),
            'eventType' => $request->get('eventType'),
            'calendarIds' => $request->get('calendarIds'),
            'fields' => $request->get('fields'),
            'arrIds' => $request->get('arrIds'),
            // Integers
            'offset' => empty($request->get('offset')) ? 0 : (int) $request->get('offset'),
            'limit' => empty($request->get('limit')) ? 0 : (int) $request->get('limit'),
            'tourType' => (int) $request->get('tourType'),
            'courseType' => (int) $request->get('courseType'),
            'year' => (int) $request->get('year'),
            // Strings
            'courseId' => $request->get('courseId'),
            'eventId' => $request->get('eventId'),
            'dateStart' => $request->get('dateStart'),
            'dateEnd' => $request->get('dateEnd'),
            'textSearch' => $request->get('textSearch'),
            'username' => $request->get('username'),
            'suitableForBeginners' => $request->get('suitableForBeginners') ? '1' : '',
            'publicTransportEvent' => $request->get('publicTransportEvent') ? '1' : '',
        ];
    }

    /**
     * Deserialize arrays, convert binary uuids and clean strings from illegal characters.
     */
    private function prepareValue(mixed $varValue): mixed
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);
        $validator = $this->framework->getAdapter(Validator::class);

        // Transform bin uuids
        $varValue = $validator->isBinaryUuid($varValue) ? $stringUtil->binToUuid($varValue) : $varValue;

        // Deserialize arrays
        $varValue = $stringUtil->deserialize($varValue);

        // Clean arrays recursively
        if (!empty($varValue) && \is_array($varValue)) {
            $varValue = array_map(fn ($v) => $this->prepareValue($v), $varValue);
        }

        $varValue = \is_string($varValue) && $validator->isBinaryUuid($varValue) ? $stringUtil->binToUuid($varValue) : $varValue;
        $varValue = \is_string($varValue) ? $stringUtil->decodeEntities($varValue) : $varValue;

        return \is_string($varValue) || \is_array($varValue) ? mb_convert_encoding($varValue, 'UTF-8', 'UTF-8') : $varValue;
    }

    private function buildQuery(Connection $connection, array $params): QueryBuilder
    {
        // Ignore date range, if certain query params were set
        $blnIgnoreDate = false;

        $qb = $connection->createQueryBuilder();

        $qb->select('id')
            ->from('tl_calendar_events', 't')
            ->where('t.published = :published')
            ->setParameter('published', '1', Types::STRING)
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

        // Filter by event types "tour","course","generalEvent","lastMinuteTour"
        if (!empty($params['eventType'])) {
            $qb->andWhere($qb->expr()->in('t.eventType', ':eventType'));
            $qb->setParameter('eventType', $params['eventType'], ArrayParameterType::INTEGER);
        }

        // Filter by suitableForBeginners
        if ('1' === $params['suitableForBeginners']) {
            $qb->andWhere('t.suitableForBeginners = :suitableForBeginners');
            $qb->setParameter('suitableForBeginners', '1', Types::STRING);
        }

        // Filter by publicTransportEvent
        if ('1' === $params['publicTransportEvent']) {
            $idPublicTransportJourney = $connection->fetchOne(
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
            $userId = $connection->fetchOne(
                'SELECT id FROM tl_user WHERE username = :username',
                ['username' => $params['username']],
                ['username' => Types::STRING],
            );

            if (!$userId) {
                $userId = 0;
            }

            $qb2 = $connection->createQueryBuilder();

            $qb2->select('pid')
                ->from('tl_calendar_events_instructor', 't')
                ->where('t.userId = :instructorId')
                ->setParameter('instructorId', $userId, Types::INTEGER)
            ;

            $arrEvents = $qb2->fetchFirstColumn();

            $qb->andWhere($qb->expr()->in('t.id', ':arrEvents'));
            $qb->setParameter('arrEvents', $arrEvents, ArrayParameterType::INTEGER);
        }

        // Search term (search for expression in tl_calendar_events.title and tl_calendar_events.teaser
        if (!empty($params['textSearch'])) {
            $arrOrExpr = [];

            // Support multiple search expressions
            foreach (explode(' ', $params['textSearch']) as $strNeedle) {
                if (empty(trim($strNeedle))) {
                    continue;
                }

                $strNeedle = trim($strNeedle);

                // Search expression in title & teaser
                $arrOrExpr[] = $qb->expr()->like('t.title', $qb->expr()->literal('%'.$strNeedle.'%'));
                $arrOrExpr[] = $qb->expr()->like('t.teaser', $qb->expr()->literal('%'.$strNeedle.'%'));

                // Check if search expression is the name of an instructor
                $qbSt = $connection->createQueryBuilder();
                $qbSt->select('id')
                    ->from('tl_user', 'u')
                    ->where($qbSt->expr()->like('u.name', $qbSt->expr()->literal('%'.$strNeedle.'%')))
                ;

                $arrInst = $qbSt->fetchFirstColumn();

                // Check if instructor is the instructor in this event
                foreach ($arrInst as $instrId) {
                    $arrOrExpr[] = $qb->expr()->in(
                        't.id',
                        $connection->createQueryBuilder()
                            ->select('pid')
                            ->from('tl_calendar_events_instructor', 't2')
                            ->where('t2.userId = :qbStInstructorId'.$instrId)
                            ->getSQL()
                    );
                    $qb->setParameter('qbStInstructorId'.$instrId, $instrId, Types::INTEGER);
                }
            }

            if (!empty($arrOrExpr)) {
                $qb->andWhere($qb->expr()->or(...$arrOrExpr));
            }
        }

        // Filter by organizers
        if (!empty($params['organizers']) && \is_array($params['organizers'])) {
            $qbEvtOrg = $connection->createQueryBuilder();
            $qbEvtOrg->select('id')
                ->from('tl_event_organizer', 'o')
                ->where('o.ignoreFilterInEventList = :true')
                ->setParameter('true', '1', Types::STRING)
            ;

            $arrIgnoredOrganizer = $qbEvtOrg->fetchFirstColumn();

            $arrOrExpr = [];

            // Show event if it has an organizer with the flag ignoreFilterInEventList=true
            if (!empty($arrIgnoredOrganizer)) {
                foreach ($arrIgnoredOrganizer as $orgId) {
                    $arrOrExpr[] = $qb->expr()->like('t.organizers', $qb->expr()->literal('%:"'.$orgId.'";%'));
                }
            }

            // Show event if its organizer is in the search param
            foreach ($params['organizers'] as $orgId) {
                if (!\in_array($orgId, $arrIgnoredOrganizer, false)) {
                    $arrOrExpr[] = $qb->expr()->like('t.organizers', $qb->expr()->literal('%:"'.$orgId.'";%'));
                }
            }

            if (!empty($arrOrExpr)) {
                $qb->andWhere($qb->expr()->or(...$arrOrExpr));
            }
        }

        // Filter by tour type
        if (!empty($params['tourType']) && $params['tourType'] > 0) {
            $qb->andWhere($qb->expr()->like('t.tourType', $qb->expr()->literal('%:"'.$params['tourType'].'";%')));
        }

        // Filter by course type
        if (!empty($params['courseType']) && $params['courseType'] > 0) {
            $qb->andWhere('t.courseTypeLevel1 = :courseType');
            $qb->setParameter('courseType', $params['courseType'], Types::INTEGER);
        }

        // Filter by course id
        if (!empty($params['courseId'])) {
            $strId = preg_replace('/\s/', '', $params['courseId']);

            if (!empty($strId)) {
                $qb->andWhere($qb->expr()->like('t.courseId', $qb->expr()->literal('%'.$strId.'%')));
                $blnIgnoreDate = true;
            }
        }

        // Filter by event id
        if (!empty($params['eventId'])) {
            $strId = preg_replace('/\s/', '', $params['eventId']);
            $arrChunk = explode('-', $strId);

            $eventId = $arrChunk[1] ?? $strId;

            $qb->andWhere('t.id = :eventId');
            $qb->setParameter('eventId', $eventId, Types::STRING);
            $blnIgnoreDate = true;
        }

        if (!$blnIgnoreDate) {
            if (!empty($params['dateStart']) && (false !== ($tstampStart = strtotime($params['dateStart'])))) {
                // event filter: date start filter
                $qb->andWhere($qb->expr()->gte('t.endDate', ':tstampStart'));
                $qb->setParameter('tstampStart', $tstampStart, Types::INTEGER);
            } elseif ((int) $params['year'] > 2000) {
                // event filter: year filter
                $year = (int) $params['year'];
                $tstampStart = strtotime($year.'-01-01');
                $tstampStop = (int) (strtotime('31-12-'.$year) + 24 * 3600 - 1);
                $qb->andWhere($qb->expr()->gte('t.endDate', ':tstampStart'));
                $qb->andWhere($qb->expr()->lte('t.endDate', ':tstampStop'));
                $qb->setParameter('tstampStart', $tstampStart, Types::INTEGER);
                $qb->setParameter('tstampStop', $tstampStop, Types::INTEGER);
            } else {
                // event filter: upcoming events
                $tstampStart = strtotime(date('Y-m-d', time()));
                $qb->andWhere($qb->expr()->gte('t.endDate', ':tstampStart'));
                $qb->setParameter('tstampStart', $tstampStart, Types::INTEGER);
            }

            // event filter: date stop filter
            if (!empty($params['dateEnd']) && (false !== ($dateEnd = strtotime($params['dateEnd'])))) {
                $qb->andWhere($qb->expr()->lte('t.endDate', ':tstampStop'));
                $qb->setParameter('tstampStop', $dateEnd, Types::INTEGER);
            }
        }

        // Order by startDate ASC
        $qb->orderBy('t.startDate', 'ASC');

        return $qb;
    }
}
