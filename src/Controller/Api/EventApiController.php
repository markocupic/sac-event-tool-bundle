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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Stopwatch\Stopwatch;

class EventApiController extends AbstractController
{
    public const CACHE_MAX_AGE = 0;

    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Connection $connection;

    /**
     * EventApiController constructor.
     * Get event data as json object.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
    }

    /**
     * Get event list filtered by params delivered from a filter board
     * This controller is used for the vje.js event list module.
     *
     * @Route("/eventApi/events", name="sac_event_tool_api_event_api_get_events", defaults={"_scope" = "frontend", "_token_check" = false})
     *
     * @throws Exception
     * @throws \Exception
     */
    public function getEventList(): JsonResponse
    {
        $this->framework->initialize();

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

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
            'textsearch' => $request->get('textsearch'),
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
            $qb->setParameter('calendarIds', $param['calendarIds'], Connection::PARAM_INT_ARRAY);
        }

        // Filter by event ids tl_calendar_events.id
        if (!empty($param['arrIds'])) {
            $qb->andWhere($qb->expr()->in('t.id', ':arrIds'));
            $qb->setParameter('arrIds', $param['arrIds'], Connection::PARAM_INT_ARRAY);
        }

        // Filter by event types "tour","course","generalEvent","lastMinuteTour"
        if (!empty($param['eventType'])) {
            $qb->andWhere($qb->expr()->in('t.eventType', ':eventType'));
            $qb->setParameter('eventType', $param['eventType'], Connection::PARAM_STR_ARRAY);
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
            if (null !== ($user = $userModelAdapter->findOneBy('username', $param['username']))) {
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
            $qb->setParameter('arrEvents', $arrEvents, Connection::PARAM_INT_ARRAY);
        }

        // Search term (search for expression in tl_calendar_events.title and tl_calendar_events.teaser
        if (!empty($param['textsearch'])) {
            $arrOrExpr = [];

            // Support multiple search expressions
            foreach (explode(' ', $param['textsearch']) as $strNeedle) {
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

        /** @var array $arrIds */
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
                ->setParameter('ids', $arrIds, Connection::PARAM_INT_ARRAY)
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
                $objEvent = $calendarEventsModelAdapter->findByPk($arrEvent['id']);

                if (null !== $objEvent) {
                    $arrJSON['meta']['arrEventIds'][] = $arrEvent['id'];

                    if (null === $oData) {
                        $oData = new \stdClass();

                        foreach ($arrFields as $field) {
                            $v = $calendarEventsHelperAdapter->getEventData($objEvent, $field);
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
     * This controller is used for the "pilatus" export, where events are loaded by ajax when the modal windows opens
     * $_POST['id'], $_POST['fields'] as comma separated string is optional.
     *
     * @Route("/eventApi/getEventById", name="sac_event_tool_api_event_api_get_event_by_id", defaults={"_scope" = "frontend", "_token_check" = false})
     *
     * @throws \Exception
     */
    public function getEventById(): JsonResponse
    {
        $this->framework->initialize();

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $eventId = (int) $request->request->get('id');
        $arrFields = '' !== $request->get('fields') ? explode(',', $request->get('fields')) : [];

        $arrJSON = [
            'status' => 'error',
            'arrEventData' => '',
            'eventId' => $eventId,
            'arrFields' => $arrFields,
        ];

        if (null !== ($objEvent = $calendarEventsModelAdapter->findOneById($eventId))) {
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

                $aEvent[$k] = $this->prepareValue($calendarEventsHelperAdapter->getEventData($objEvent, $k));
            }
            $arrJSON['arrEventData'] = $aEvent;
        }

        // Allow cross domain requests
        return new JsonResponse($arrJSON, 200, ['Access-Control-Allow-Origin' => '*']);
    }

    /**
     * Deserialize arrays and convert binary uuids.
     */
    private function prepareValue(mixed $varValue): mixed
    {
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Transform bin uuids
        $varValue = $validatorAdapter->isBinaryUuid($varValue) ? $stringUtilAdapter->binToUuid($varValue) : $varValue;

        // Deserialize arrays and convert binary uuids
        $tmp = $stringUtilAdapter->deserialize($varValue);

        if (!empty($tmp) && \is_array($tmp)) {
            $tmp = $this->arrayMapRecursive(
                $tmp,
                function ($v) {
                    /** @var Validator $validatorAdapter */
                    $validatorAdapter = $this->framework->getAdapter(Validator::class);

                    /** @var StringUtil $stringUtilAdapter */
                    $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

                    return \is_string($v) && $validatorAdapter->isBinaryUuid($v) ? $stringUtilAdapter->binToUuid($v) : $v;
                }
            );
        }
        $varValue = !empty($tmp) && \is_array($tmp) ? $tmp : $varValue;

        return \is_string($varValue) ? $stringUtilAdapter->decodeEntities($varValue) : $varValue;
    }

    /**
     * array_map for deep arrays.
     */
    private function arrayMapRecursive(array $arr, callable $fn): array
    {
        return array_map(
            fn ($item) => \is_array($item) ? $this->arrayMapRecursive($item, $fn) : $fn($item),
            $arr
        );
    }
}
