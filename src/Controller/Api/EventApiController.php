<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\Api;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\Query\QueryBuilder;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class EventApiController.
 */
class EventApiController extends AbstractController
{
    const CACHE_MAX_AGE = 180;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * EventApiController constructor.
     * Get event data as json object.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;

        $this->framework->initialize();
    }

    /**
     * Get event list filtered by params delivered from a filter board
     * This controller is used for the vje.js event list module.
     *
     * @Route("/eventApi/events", name="sac_event_tool_api_event_api_get_events", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function getEventList(): JsonResponse
    {
        System::getContainer()->get('contao.framework')->initialize();

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

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
        ];

        $startTime = microtime(true);

        // Ignore date range, ff certain query params were set
        $blnIgnoreDate = false;

        /** @var QueryBuilder $qb */
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
            $qb->andWhere('t.suitableForBeginners', ':suitableForBeginners');
            $qb->setParameter('suitableForBeginners', '1');
        }

        // Filter by a certain instructor $_GET['username']
        if (!empty($param['username'])) {
            if (($user = $userModelAdapter->findByUsername($param['username'])) === null) {
                // Do not show any events if username does not exist
                $userId = 0;
            } else {
                $userId = $user->id;
            }
            $qb2 = $this->connection->createQueryBuilder();
            $qb2->select('pid')
                ->from('tl_calendar_events_instructor', 't')
                ->where('t.userId = :instructorId')
                ->setParameter('instructorId', $userId)
            ;
            $arrEvents = $qb2->execute()->fetchAll(\PDO::FETCH_COLUMN, 0);

            $qb->andWhere($qb->expr()->in('t.id', ':arrEvents'));
            $qb->setParameter('arrEvents', $arrEvents, Connection::PARAM_INT_ARRAY);
        }

        // Searchterm (search for expression in tl_calendar_events.title and tl_calendar_events.teaser
        if (!empty($param['textsearch'])) {
            $orxSearchTerm = $qb->expr()->orX();

            // Support multiple search expressions
            foreach (explode(' ', $param['textsearch']) as $strNeedle) {
                if (empty(trim($strNeedle))) {
                    continue;
                }

                $strNeedle = trim($strNeedle);

                // Search expression in title & teaser
                $orxSearchTerm->add($qb->expr()->like('t.title', $qb->expr()->literal('%'.$strNeedle.'%')));
                $orxSearchTerm->add($qb->expr()->like('t.teaser', $qb->expr()->literal('%'.$strNeedle.'%')));

                // Check if search expression is the name of an instructor
                $qbSt = $this->connection->createQueryBuilder();
                $qbSt->select('id')
                    ->from('tl_user', 'u')
                    ->where($qbSt->expr()->like('u.name', $qbSt->expr()->literal('%'.$strNeedle.'%')))
                ;
                $arrInst = $qbSt->execute()->fetchAll(\PDO::FETCH_COLUMN, 0);

                // Check if instructor is the instructor in this event
                foreach ($arrInst as $instrId) {
                    $orxSearchTerm->add($qb->expr()->in(
                        't.id',
                        $this->connection->createQueryBuilder()
                            ->select('pid')
                            ->from('tl_calendar_events_instructor', 't2')
                            ->where('t2.userId = :qbStInstructorid'.$instrId)
                            ->getSQL()
                    ));
                    $qb->setParameter('qbStInstructorid'.$instrId, $instrId);
                }
            }
            $qb->andWhere($orxSearchTerm);
        }

        // Filter by organizers
        if (!empty($param['organizers']) && \is_array($param['organizers'])) {
            $qbEvtOrg = $this->connection->createQueryBuilder();
            $qbEvtOrg->select('id')
                ->from('tl_event_organizer', 'o')
                ->where('o.ignoreFilterInEventList = :true')
                ->setParameter('true', '1')
            ;
            $arrIgnoredOrganizer = $qbEvtOrg->execute()->fetchAll(\PDO::FETCH_COLUMN, 0);

            $orxOrg = $qb->expr()->orX();

            // Show event if it has an organizer with the flag ignoreFilterInEventList=true
            if (!empty($arrIgnoredOrganizer) && \is_array($arrIgnoredOrganizer)) {
                foreach ($arrIgnoredOrganizer as $orgId) {
                    $orxOrg->add($qb->expr()->like('t.organizers', $qb->expr()->literal('%:"'.$orgId.'";%')));
                }
            }
            // Show event if its organizer is in the search param
            foreach ($param['organizers'] as $orgId) {
                if (!\in_array($orgId, $arrIgnoredOrganizer, false)) {
                    $orxOrg->add($qb->expr()->like('t.organizers', $qb->expr()->literal('%:"'.$orgId.'";%')));
                }
            }
            $qb->andWhere($orxOrg);
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

            if (isset($arrChunk[1])) {
                $eventId = $arrChunk[1];
            } else {
                $eventId = $strId;
            }

            $qb->andWhere('t.id = :eventId');
            $qb->setParameter('eventId', $eventId);
            $blnIgnoreDate = true;
        }

        if (!$blnIgnoreDate) {
            // dateStart filter
            if (!empty($param['dateStart']) && (false !== ($dateStart = strtotime($param['dateStart'])))) {
                $qb->andWhere($qb->expr()->gte('t.endDate', ':dateStart'));
                $qb->setParameter('dateStart', $dateStart);
            }
            // Filterboard: year filter
            elseif ((int) $param['year'] > 2000) {
                $year = (int) $param['year'];
                $intStart = strtotime('01-01-'.$year);
                $intEnd = (int) (strtotime('31-12-'.$year) + 24 * 3600 - 1);
                $qb->andWhere($qb->expr()->gte('t.endDate', ':startDate'));
                $qb->andWhere($qb->expr()->lte('t.endDate', ':endDate'));
                $qb->setParameter('startDate', $intStart);
                $qb->setParameter('endDate', $intEnd);
            } else {
                // Show upcoming events
                $intNow = (int) strtotime($dateAdapter->parse('Y-m-d'));
                $qb->andWhere($qb->expr()->gte('t.endDate', ':intNow'));
                $qb->setParameter('intNow', $intNow);
            }

            // Filterboard: dateEnd filter
            if (!empty($param['dateEnd']) && (false !== ($dateEnd = strtotime($param['dateEnd'])))) {
                $qb->andWhere($qb->expr()->lte('t.endDate', ':dateEnd'));
                $qb->setParameter('dateEnd', $dateEnd);
            }
        }

        // Order by startDate ASC
        $qb->orderBy('t.startDate', 'ASC');

        $query = $qb->getSQL();

        /** @var array $arrIds */
        $arrIds = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN, 0);

        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;

        // Now we have all the ids, let's prepare the second query
        $arrFields = empty($param['fields']) ? [] : $param['fields'];

        $arrJSON = [
            'meta' => [
                'status' => 'success',
                'countItems' => 0,
                'itemsTotal' => \count($arrIds),
                'queryTime' => $queryTime,
                'sql' => $query,
                'arrEventIds' => [],
                'params' => [],
            ],
            'data' => [],
        ];

        foreach ($param as $k => $v) {
            $arrJSON['meta']['params'][$k] = $v;
        }

        if (!empty($arrIds)) {
            /** @var QueryBuilder $qb */
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

            /** @var PDOStatement $results */
            $results = $qb->execute();

            while (false !== ($arrEvent = $results->fetch())) {
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

        // Allow cross domain requests
        $response = new JsonResponse($arrJSON, 200, ['Access-Control-Allow-Origin' => '*']);

        // Enable cache
        $response->setPublic();
        $response->setSharedMaxAge(self::CACHE_MAX_AGE);
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE-10);

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
     *
     * @param $varValue
     *
     * @return array|string|null
     */
    private function prepareValue($varValue)
    {
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Transform bin uuids
        $varValue = $validatorAdapter->isBinaryUuid($varValue) ? $stringUtilAdapter->binToUuid($varValue) : $varValue;

        // Deserialize arrays and convert binuuids
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
     *
     * @param $arr
     * @param $fn
     *
     * @return array
     */
    private function arrayMapRecursive(&$arr, $fn)
    {
        return array_map(
            function ($item) use ($fn) {
                return \is_array($item) ? $this->arrayMapRecursive($item, $fn) : $fn($item);
            },
            $arr
        );
    }
}
