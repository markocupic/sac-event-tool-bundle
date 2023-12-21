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

namespace Markocupic\SacEventToolBundle\Controller\Api;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Stopwatch\Stopwatch;

class EventApiController extends AbstractController
{
    public const CACHE_MAX_AGE = 300;

    private readonly Adapter $calendarEventsHelper;
    private readonly Adapter $calendarEventsModel;
    private readonly Adapter $stringUtil;
    private readonly Adapter $userModel;
    private readonly Adapter $validator;

    /**
     * Get event data from JSON.
     */
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
    ) {
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->userModel = $this->framework->getAdapter(UserModel::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    /**
     * Get event list filtered by params delivered from a filter board
     * This route is used for the vue.js event list module.
     *
     * @throws Exception
     * @throws \Exception
     */
    #[Route('/eventApi/events', name: 'sac_event_tool_api_event_api_get_events', methods: ['GET'], defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function getEventList(): JsonResponse
    {
        $this->framework->initialize();

        $request = $this->requestStack->getCurrentRequest();

        $param = [
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

        $stopwatch = new Stopwatch();
        $stopwatch->start('event list api query time');

        // Ignore date range, ff certain query params were set
        $blnIgnoreDate = false;

        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('tl_calendar_events', 't')
            ->where('t.published = :published')
            ->setParameter('published', '1')
        ;

        // Filter by calendar ids tl_calendar.id
        if (!empty($param['calendarIds'])) {
            $qb->andWhere($qb->expr()->in('t.pid', ':calendarIds'));
            $qb->setParameter('calendarIds', $param['calendarIds'], ArrayParameterType::INTEGER);
        }

        // Filter by event ids tl_calendar_events.id
        if (!empty($param['arrIds'])) {
            $qb->andWhere($qb->expr()->in('t.id', ':arrIds'));
            $qb->setParameter('arrIds', $param['arrIds'], ArrayParameterType::INTEGER);
        }

        // Filter by event types "tour","course","generalEvent","lastMinuteTour"
        if (!empty($param['eventType'])) {
            $qb->andWhere($qb->expr()->in('t.eventType', ':eventType'));
            $qb->setParameter('eventType', $param['eventType'], ArrayParameterType::INTEGER);
        }

        // Filter by suitableForBeginners
        if ('1' === $param['suitableForBeginners']) {
            $qb->andWhere('t.suitableForBeginners = :suitableForBeginners');
            $qb->setParameter('suitableForBeginners', '1');
        }

        // Filter by publicTransportEvent
        if ('1' === $param['publicTransportEvent']) {
            $idPublicTransportJourney = $this->connection->fetchOne(
                'SELECT id from tl_calendar_events_journey WHERE alias = ?',
                ['public-transport'],
            );

            if ($idPublicTransportJourney) {
                $qb->andWhere('t.journey = :publicTransportEvent');
                $qb->setParameter('publicTransportEvent', (int) $idPublicTransportJourney);
            }
        }

        // Filter by a certain instructor $_GET['username']
        if (!empty($param['username'])) {
            if (null !== ($user = $this->userModel->findOneBy('username', $param['username']))) {
                $userId = (int) $user->id;
            } else {
                // Do not show any events if username does not exist
                $userId = 0;
            }

            $qb2 = $this->connection->createQueryBuilder();
            $qb2->select('pid')
                ->from('tl_calendar_events_instructor', 't')
                ->where('t.userId = :instructorId')
                ->setParameter('instructorId', $userId)
            ;

            $arrEvents = $qb2->fetchFirstColumn();

            $qb->andWhere($qb->expr()->in('t.id', ':arrEvents'));
            $qb->setParameter('arrEvents', $arrEvents, ArrayParameterType::INTEGER);
        }

        // Search term (search for expression in tl_calendar_events.title and tl_calendar_events.teaser
        if (!empty($param['textSearch'])) {
            $arrOrExpr = [];

            // Support multiple search expressions
            foreach (explode(' ', $param['textSearch']) as $strNeedle) {
                if (empty(trim($strNeedle))) {
                    continue;
                }

                $strNeedle = trim($strNeedle);

                // Search expression in title & teaser
                $arrOrExpr[] = $qb->expr()->like('t.title', $qb->expr()->literal('%'.$strNeedle.'%'));
                $arrOrExpr[] = $qb->expr()->like('t.teaser', $qb->expr()->literal('%'.$strNeedle.'%'));

                // Check if search expression is the name of an instructor
                $qbSt = $this->connection->createQueryBuilder();
                $qbSt->select('id')
                    ->from('tl_user', 'u')
                    ->where($qbSt->expr()->like('u.name', $qbSt->expr()->literal('%'.$strNeedle.'%')))
                ;

                $arrInst = $qbSt->fetchFirstColumn();

                // Check if instructor is the instructor in this event
                foreach ($arrInst as $instrId) {
                    $arrOrExpr[] = $qb->expr()->in(
                        't.id',
                        $this->connection->createQueryBuilder()
                            ->select('pid')
                            ->from('tl_calendar_events_instructor', 't2')
                            ->where('t2.userId = :qbStInstructorId'.$instrId)
                            ->getSQL()
                    );
                    $qb->setParameter('qbStInstructorId'.$instrId, $instrId);
                }
            }

            if (!empty($arrOrExpr)) {
                $qb->andWhere($qb->expr()->or(...$arrOrExpr));
            }
        }

        // Filter by organizers
        if (!empty($param['organizers']) && \is_array($param['organizers'])) {
            $qbEvtOrg = $this->connection->createQueryBuilder();
            $qbEvtOrg->select('id')
                ->from('tl_event_organizer', 'o')
                ->where('o.ignoreFilterInEventList = :true')
                ->setParameter('true', '1')
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
            foreach ($param['organizers'] as $orgId) {
                if (!\in_array($orgId, $arrIgnoredOrganizer, false)) {
                    $arrOrExpr[] = $qb->expr()->like('t.organizers', $qb->expr()->literal('%:"'.$orgId.'";%'));
                }
            }

            if (!empty($arrOrExpr)) {
                $qb->andWhere($qb->expr()->or(...$arrOrExpr));
            }
        }

        // Filter by tour type
        if (!empty($param['tourType']) && $param['tourType'] > 0) {
            $qb->andWhere($qb->expr()->like('t.tourType', $qb->expr()->literal('%:"'.$param['tourType'].'";%')));
        }

        // Filter by course type
        if (!empty($param['courseType']) && $param['courseType'] > 0) {
            $qb->andWhere('t.courseTypeLevel1 = :courseType');
            $qb->setParameter('courseType', $param['courseType']);
        }

        // Filter by course id
        if (!empty($param['courseId'])) {
            $strId = preg_replace('/\s/', '', $param['courseId']);

            if (!empty($strId)) {
                $qb->andWhere($qb->expr()->like('t.courseId', $qb->expr()->literal('%'.$strId.'%')));
                $blnIgnoreDate = true;
            }
        }

        // Filter by event id
        if (!empty($param['eventId'])) {
            $strId = preg_replace('/\s/', '', $param['eventId']);
            $arrChunk = explode('-', $strId);

            $eventId = $arrChunk[1] ?? $strId;

            $qb->andWhere('t.id = :eventId');
            $qb->setParameter('eventId', $eventId);
            $blnIgnoreDate = true;
        }

        if (!$blnIgnoreDate) {
            if (!empty($param['dateStart']) && (false !== ($tstampStart = strtotime($param['dateStart'])))) {
                // event filter: date start filter
                $qb->andWhere($qb->expr()->gte('t.endDate', ':tstampStart'));
                $qb->setParameter('tstampStart', $tstampStart);
            } elseif ((int) $param['year'] > 2000) {
                // event filter: year filter
                $year = (int) $param['year'];
                $tstampStart = strtotime($year.'-01-01');
                $tstampStop = (int) (strtotime('31-12-'.$year) + 24 * 3600 - 1);
                $qb->andWhere($qb->expr()->gte('t.endDate', ':tstampStart'));
                $qb->andWhere($qb->expr()->lte('t.endDate', ':tstampStop'));
                $qb->setParameter('tstampStart', $tstampStart);
                $qb->setParameter('tstampStop', $tstampStop);
            } else {
                // event filter: upcoming events
                $tstampStart = strtotime(date('Y-m-d', time()));
                $qb->andWhere($qb->expr()->gte('t.endDate', ':tstampStart'));
                $qb->setParameter('tstampStart', $tstampStart);
            }

            // event filter: date stop filter
            if (!empty($param['dateEnd']) && (false !== ($dateEnd = strtotime($param['dateEnd'])))) {
                $qb->andWhere($qb->expr()->lte('t.endDate', ':tstampStop'));
                $qb->setParameter('tstampStop', $dateEnd);
            }
        }

        // Order by startDate ASC
        $qb->orderBy('t.startDate', 'ASC');

        /** @var array<int> $arrIds */
        $arrIds = $qb->fetchFirstColumn();

        // Now we have all the ids, let's prepare the second query
        $arrFields = empty($param['fields']) ? [] : $param['fields'];

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
            if ($param['offset'] > 0) {
                $qb->setFirstResult($param['offset']);
            }

            // Limit
            if ($param['limit'] > 0) {
                $qb->setMaxResults($param['limit']);
            }

            $results = $qb->executeQuery();

            while (false !== ($arrEvent = $results->fetchAssociative())) {
                ++$arrJSON['meta']['countItems'];
                $oData = null;

                /** @var CalendarEventsModel $objEvent */
                $objEvent = $this->calendarEventsModel->findByPk($arrEvent['id']);

                if (null !== $objEvent) {
                    $arrJSON['meta']['arrEventIds'][] = $arrEvent['id'];

                    if (null === $oData) {
                        $oData = new \stdClass();

                        foreach ($arrFields as $field) {
                            $v = $this->calendarEventsHelper->getEventData($objEvent, $field);
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
     * This route is used for the "pilatus" export, where events are loaded by xhr when the modal windows opens
     * $_POST['id'], $_POST['fields'] as comma separated string is optional.
     *
     * @throws \Exception
     */
    #[Route('/eventApi/getEventById', name: 'sac_event_tool_api_event_api_get_event_by_id', methods: ['GET'], defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function getEventById(): JsonResponse
    {
        $this->framework->initialize();

        $request = $this->requestStack->getCurrentRequest();

        $eventId = (int) $request->request->get('id');
        $arrFields = '' !== $request->get('fields') ? explode(',', $request->get('fields')) : [];

        $arrJSON = [
            'status' => 'error',
            'arrEventData' => '',
            'eventId' => $eventId,
            'arrFields' => $arrFields,
        ];

        if (null !== ($objEvent = $this->calendarEventsModel->findOneById($eventId))) {
            $arrEvent = $objEvent->row();
            $arrJSON['status'] = 'success';
            $aEvent = [];

            foreach (array_keys($arrEvent) as $k) {
                // If $arrFields is empty send all properties
                if (!empty($arrFields)) {
                    if (!\in_array($k, $arrFields, true)) {
                        continue;
                    }
                }

                $aEvent[$k] = $this->prepareValue($this->calendarEventsHelper->getEventData($objEvent, $k));
            }
            $arrJSON['arrEventData'] = $aEvent;
        }

        // Allow cross domain requests
        return new JsonResponse($arrJSON, 200, ['Access-Control-Allow-Origin' => '*']);
    }

    /**
     * Deserialize arrays, convert binary uuids and clean strings from illegal characters.
     */
    private function prepareValue(mixed $varValue): mixed
    {
        // Transform bin uuids
        $varValue = $this->validator->isBinaryUuid($varValue) ? $this->stringUtil->binToUuid($varValue) : $varValue;

        // Deserialize arrays
        $varValue = $this->stringUtil->deserialize($varValue);

        // Clean arrays recursively
        if (!empty($varValue) && \is_array($varValue)) {
            $varValue = array_map(fn ($v) => $this->prepareValue($v), $varValue);
        }

        $varValue = !empty($tmp) && \is_array($tmp) ? $tmp : $varValue;
        $varValue = \is_string($varValue) && $this->validator->isBinaryUuid($varValue) ? $this->stringUtil->binToUuid($varValue) : $varValue;
        $varValue = \is_string($varValue) ? $this->stringUtil->decodeEntities($varValue) : $varValue;

        return \is_string($varValue) || \is_array($varValue) ? mb_convert_encoding($varValue, 'UTF-8', 'UTF-8') : $varValue;
    }
}
