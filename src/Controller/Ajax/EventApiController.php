<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\Ajax;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\EventOrganizerModel;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\FrontendCache\SessionCache\SessionCache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class EventApiController
 * @package Markocupic\SacEventToolBundle\Controller
 */
class EventApiController extends AbstractController
{
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
     * @var SessionCache
     */
    private $sessionCache;

    /**
     * Cache response in session
     * @const CACHE_EXPIRATION_TIMEOUT seconds
     */
    private const CACHE_EXPIRATION_TIMEOUT = 180;

    /**
     * @var string
     */
    private $sessionCacheToken;

    /**
     * EventApiController constructor.
     * Get event data as json object
     * Allow if ...
     * - is XmlHttpRequest
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param Connection $connection
     * @param SessionCache $sessionCache
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, SessionCache $sessionCache)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->sessionCache = $sessionCache;

        $this->framework->initialize();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        // Do allow only xhr requests
        if (!$request->isXmlHttpRequest())
        {
            throw $this->createNotFoundException('The route "/ajaxEventApi" is allowed to XMLHttpRequest requests only.');
        }
    }

    /**
     * Get event list filtered by params delivered from a filter board
     * This controller is used for the vje.js event list module
     * @Route("/ajaxEventApi/getEventList", name="sac_event_tool_event_ajax_event_api_get_event_list", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function getEventList(): JsonResponse
    {
        System::getContainer()->get('contao.framework')->initialize();

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        /** @var  EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);

        $request = $this->requestStack->getCurrentRequest();
        /**
         * 'apiParams': {
         * 'organizers': [<?= $this->apiParam['organizers'] ?>],
         * 'tourType': '<?= $this->apiParam['tourType'] ?>',
         * 'courseType': '<?= $this->apiParam['courseType'] ?>',
         * 'courseId': '<?= $this->apiParam['courseId'] ?>',
         * 'year': '<?= $this->apiParam['year'] ?>',
         * 'dateStart': '<?= $this->apiParam['dateStart'] ?>',
         * 'searchterm': '<?= $this->apiParam['searchterm'] ?>',
         * 'eventId': '<?= $this->apiParam['eventId'] ?>',
         * 'arrIds': [<?= $this->apiParam['eventId'] ?>],
         * 'calendarIds': [<?= $this->apiParam['calendarIds'] ?>],
         * 'limitPerRequest': '<?= $this->apiParam['limitPerRequest'] ?>',
         * },
         **/

        $param = [
            // Arrays
            'organizers'        => $request->request->get('organizers'),
            'eventTypes'        => $request->request->get('eventTypes'),
            'calendarIds'       => $request->request->get('calendarIds'),
            'fields'            => $request->request->get('fields'),
            // Array or string null
            'arrIds'            => $request->request->get('arrIds'),
            // Integers
            'offset'            => (int) $request->request->get('offset'),
            'tourType'          => (int) $request->request->get('tourType'),
            'courseType'        => (int) $request->request->get('courseType'),
            'year'              => (int) $request->request->get('year'),
            'limitPerRequest'   => (int) $request->request->get('limitPerRequest'),
            // Strings
            'courseId'          => $request->request->get('courseId'),
            'eventId'           => $request->request->get('eventId'),
            'dateStart'         => $request->request->get('dateStart'),
            'searchterm'        => $request->request->get('searchterm'),
            'username'          => $request->request->get('username'),
            'sessionCacheToken' => $request->request->get('sessionCacheToken'),
            // Boolean
            'isPreloadRequest'  => $request->request->get('isPreloadRequest'),
        ];

        // Event ids can be passed with $_POST
        // (f.ex. second request in the vue.js event list application)
        if ($param['arrIds'] !== 'null')
        {
            // The FormData object will send null value as string "null"
            $arrIds = $param['arrIds'];
        }

        if ($param['arrIds'] === 'null')
        {
            /** @var QueryBuilder $qb */
            $qb = $this->connection->createQueryBuilder();
            $qb->select('*')
                ->from('tl_calendar_events', 't')
                ->where('t.published = :published')
                ->andWhere($qb->expr()->in('t.pid', ':calendarIds'))
                ->andWhere($qb->expr()->in('t.eventType', ':eventTypes'))
                ->setParameter('published', '1')
                ->setParameter('calendarIds', $param['calendarIds'], Connection::PARAM_INT_ARRAY)
                ->setParameter('eventTypes', $param['eventTypes'], Connection::PARAM_STR_ARRAY);

            // Filter by a certain instructor $_GET['username']
            if (!empty($instructorUsername = $param['username']))
            {
                if (($user = $userModelAdapter->findByUsername($instructorUsername)) === null)
                {
                    // Do not show any events if username does not exist
                    $userId = 0;
                }
                else
                {
                    $userId = $user->id;
                }
                $qb2 = $this->connection->createQueryBuilder();
                $qb2->select('pid')
                    ->from('tl_calendar_events_instructor', 't')
                    ->where('t.userId = :instructorId')
                    ->setParameter('instructorId', $userId);
                $arrEvents = $qb2->execute()->fetchAll(\PDO::FETCH_COLUMN, 0);

                $qb->andWhere($qb->expr()->in('t.id', ':arrEvents'));
                $qb->setParameter('arrEvents', $arrEvents, Connection::PARAM_INT_ARRAY);
            }

            // Filterboard: year filter
            if ((int) $param['year'] > 2000)
            {
                $year = (int) $param['year'];
                $intStart = strtotime('01-01-' . $year);
                $intEnd = (int) (strtotime('31-12-' . $year) + 24 * 3600 - 1);
                $qb->andWhere($qb->expr()->gte('t.startDate', ':startDate'));
                $qb->andWhere($qb->expr()->lte('t.startDate', ':endDate'));
                $qb->setParameter('startDate', $intStart);
                $qb->setParameter('endDate', $intEnd);
            }
            else
            {
                // Show upcoming events
                $intNow = (int) strtotime($dateAdapter->parse('Y-m-d'));
                $qb->andWhere($qb->expr()->gte('t.endDate', ':intNow'));
                $qb->setParameter('intNow', $intNow);
            }

            // Filterboard: dateStart filter
            if (!empty($param['dateStart']))
            {
                $dateStart = strtotime($param['dateStart']);
                if ($dateStart > 0)
                {
                    $qb->andWhere($qb->expr()->gte('t.startDate', ':dateStart'));
                    $qb->setParameter('dateStart', $dateStart);
                }
            }

            // Filterboard: eventId filter
            if (!empty($param['eventId']))
            {
                $strId = preg_replace('/\s/', '', $param['eventId']);
                $arrChunk = explode('-', $strId);
                if (isset($arrChunk[1]) && is_numeric((int) $arrChunk[1]))
                {
                    if (is_numeric($arrChunk[1]))
                    {
                        $eventId = (int) $arrChunk[1];
                        $qb->andWhere('t.id', ':eventId');
                        $qb->setParameter('eventId', $eventId);
                    }
                }
            }

            // Filterboard: courseId
            if (!empty($param['courseId']))
            {
                $strId = trim($param['courseId']);
                if (!empty($strId))
                {
                    $qb->andWhere($qb->expr()->like('t.courseId', $qb->expr()->literal('%' . $strId . '%')));
                }
            }

            $qb->orderBy('t.startDate', 'ASC');

            /** @var \Doctrine\DBAL\Driver\PDOStatement $results */
            $results = $qb->execute();

            $arrEvents = array();
            while (false !== ($event = $results->fetch()))
            {
                // Filter items that can not be filtered in the query above
                // Filterboard: organizers
                if (!empty($param['organizers']))
                {
                    $arrOrganizers = $param['organizers'];
                    $arrEventOrganizers = $stringUtilAdapter->deserialize($event['organizers'], true);
                    $objEventOrganizerModel = $eventOrganizerModelAdapter->findByIds($arrEventOrganizers);
                    $blnIgnoreOrganizerFilter = false;
                    if ($objEventOrganizerModel !== null)
                    {
                        while ($objEventOrganizerModel->next())
                        {
                            // Ignore organizers filter if event belongs to organizer where the ignoreFilterInEventList flas is set to true
                            // tl_event_organizer.ignoreFilterInEventList
                            // Thanks to Peter Erni, 22.11.2019
                            if ($objEventOrganizerModel->ignoreFilterInEventList)
                            {
                                $blnIgnoreOrganizerFilter = true;
                            }
                        }
                    }

                    if ($blnIgnoreOrganizerFilter === false && count(array_intersect($arrOrganizers, $arrEventOrganizers)) < 1)
                    {
                        continue;
                    }
                }

                // Filterboard: tourType
                if ($param['tourType'] > 0)
                {
                    $arrTourTypes = $stringUtilAdapter->deserialize($event['tourType'], true);
                    if (!in_array($param['tourType'], $arrTourTypes))
                    {
                        continue;
                    }
                }

                // Filterboard: courseType
                if ($param['courseType'] > 0)
                {
                    $arrCourseTypes = $stringUtilAdapter->deserialize($event['courseTypeLevel1'], true);
                    if (!in_array($param['courseType'], $arrCourseTypes))
                    {
                        continue;
                    }
                }

                $strSearchterm = $param['searchterm'];
                if ($strSearchterm != '')
                {
                    $intFound = 0;
                    foreach (explode(' ', $strSearchterm) as $strNeedle)
                    {
                        if ($intFound)
                        {
                            continue;
                        }

                        // Suche nach Namen des Kursleiters
                        $arrInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($event['id']);
                        $strLeiter = implode(', ', array_map(function ($userId) {
                            /** @var UserModel $userModelAdapter */
                            $userModelAdapter = $this->framework->getAdapter(UserModel::class);
                            return $userModelAdapter->findByPk($userId)->name;
                        }, $arrInstructors));

                        if ($intFound == 0)
                        {
                            if ($this->textSearch($strNeedle, $strLeiter))
                            {
                                $intFound++;
                            }
                        }

                        if ($intFound == 0)
                        {
                            // Suchbegriff im Titel suchen
                            if ($this->textSearch($strNeedle, $event['title']))
                            {
                                $intFound++;
                            }
                        }

                        if ($intFound == 0)
                        {
                            // Suchbegriff im Teaser suchen
                            if ($this->textSearch($strNeedle, $event['teaser']))
                            {
                                $intFound++;
                            }
                        }
                    }

                    if ($intFound < 1)
                    {
                        continue;
                    }
                }

                // Pass the filter
                $arrEvents[] = $event['id'];
            }
            $arrIds = $arrEvents;
        }

        // Second query
        $arrFields = empty($param['fields']) ? [] : $param['fields'];

        $offset = empty($param['offset']) ? 0 : (int) $param['offset'];
        $limitPerRequest = empty($param['limitPerRequest']) ? 0 : (int) $param['limitPerRequest'];
        $isPreloadRequest = ($param['isPreloadRequest'] != 'true') ? false : true;

        $this->sessionCacheToken = $param['sessionCacheToken'];
        $arrJSON = array(
            'CACHE_EXPIRATION_TIMEOUT' => static::CACHE_EXPIRATION_TIMEOUT,
            'loadedItemsFromSession'   => 0,
            'status'                   => 'success',
            'isPreloadRequest'         => $isPreloadRequest,
            'sessionCacheToken'        => $this->sessionCacheToken,
            'countItems'               => 0,
            'offset'                   => $offset,
            'limitPerRequest'          => $limitPerRequest,
            'arrIds'                   => $arrIds,
            'arrFields'                => $arrFields,
            'arrEventData'             => array(),
            'itemsFound'               => is_array($arrIds) ? count($arrIds) : 0,
        );

        if ($arrIds !== null && is_array($arrIds))
        {
            /** @var QueryBuilder $qb */
            $qb = $this->connection->createQueryBuilder();
            $qb->select('*')
                ->from('tl_calendar_events', 't')
                ->where($qb->expr()->in('t.id', ':ids'))
                ->setParameter('ids', $arrIds, Connection::PARAM_INT_ARRAY)
                ->orderBy('t.startDate', 'ASC');
        }

        // Offset
        if ($offset > 0)
        {
            $qb->setFirstResult($offset);
        }

        // Limit
        if ($limitPerRequest > 0)
        {
            $qb->setMaxResults($limitPerRequest);
        }

        /** @var \Doctrine\DBAL\Driver\PDOStatement $results */
        $results = $qb->execute();

        $count = 0;
        while (false !== ($arrEvent = $results->fetch()))
        {
            $count++;
            $oData = null;

            /** @var  CalendarEventsModel $objEvent */
            $objEvent = $calendarEventsModelAdapter->findByPk($arrEvent['id']);
            if ($objEvent !== null)
            {
                $strToken = $this->sessionCacheToken . $arrEvent['id'];

                // Try to load from cache
                if (null !== ($oData = $this->sessionCache->get($strToken)))
                {
                    $arrJSON['loadedItemsFromSession']++;
                }

                if ($oData === null)
                {
                    $oData = new \stdClass();
                    foreach ($arrFields as $field)
                    {
                        $v = $calendarEventsHelperAdapter->getEventData($objEvent, $field);
                        $aField = explode('||', $field);
                        $field = $aField[0];
                        $oData->{$field} = $this->prepareValue($v);
                    }

                    // Cache data
                    $this->sessionCache->set($strToken, $oData, static::CACHE_EXPIRATION_TIMEOUT + time());
                }

                $arrJSON['arrEventData'][] = $oData;
            }
        }
        $arrJSON['countItems'] = $count;

        // If it is preload request do not return the items
        if ($isPreloadRequest === true)
        {
            $arrJSON['countItems'] = 0;
            $arrJSON['arrEventData'] = array();
        }

        // Allow cross domain requests
        return new JsonResponse($arrJSON, 200, array('Access-Control-Allow-Origin' => '*'));
    }

    /**
     * Helper method of event filtering
     * @param $strNeedle
     * @param $strHaystack
     * @return bool
     */
    protected function textSearch($strNeedle = '', $strHaystack = '')
    {
        if ($strNeedle == '')
        {
            return true;
        }
        elseif (trim($strNeedle) == '')
        {
            return true;
        }
        elseif ($strHaystack == '')
        {
            return false;
        }
        elseif (trim($strHaystack) == '')
        {
            return false;
        }
        else
        {
            if (preg_match('/' . $strNeedle . '/i', $strHaystack))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Get event data by id and use the session cache
     * This controller is used for the "pilatus" export, where events are loaded by ajax when the modal windows opens
     * $_POST['REQUEST_TOKEN'], $_POST['id'], $_POST['fields'] as comma separated string is optional
     * @Route("/ajaxEventApi/getEventById", name="sac_event_tool_event_ajax_event_api_get_event_by_id", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function getEventById(): JsonResponse
    {
        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $eventId = (int) $request->request->get('id');
        $arrFields = $request->get('fields') != '' ? explode(',', $request->get('fields')) : [];

        $arrJSON = array(
            'status'       => 'error',
            'arrEventData' => '',
            'eventId'      => $eventId,
            'arrFields'    => $arrFields,
        );

        if (null !== ($objEvent = $calendarEventsModelAdapter->findOneById($eventId)))
        {
            $arrEvent = $objEvent->row();
            $arrJSON['status'] = 'success';
            $aEvent = array();

            foreach ($arrEvent as $k => $v)
            {
                // If $arrFields is empty send all properties
                if (!empty($arrFields))
                {
                    if (!in_array($k, $arrFields))
                    {
                        continue;
                    }
                }

                $aEvent[$k] = $this->prepareValue($calendarEventsHelperAdapter->getEventData($objEvent, $k));
            }
            $arrJSON['arrEventData'] = $aEvent;
        }

        // Allow cross domain requests
        return new JsonResponse($arrJSON, 200, array('Access-Control-Allow-Origin' => '*'));
    }

    /**
     * !!!!! Not used at the moment: Replaced by self::getEventList
     * Get event data by ids and use the session cache
     * This controller is used for the tour list, where events are loaded by vue.js
     * $_POST['REQUEST_TOKEN'], $_POST['ids'], $_POST['fields'] are mandatory
     * @Route("/ajaxEventApi/getEventDataByIds", name="sac_event_tool_event_ajax_event_api_get_event_data_by_ids", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function getEventDataByIds(): JsonResponse
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        $arrIds = $request->request->get('ids') == '' ? array() : $request->request->get('ids');
        $arrFields = $request->request->get('fields') == '' ? array() : $request->request->get('fields');

        $offset = ($request->request->get('offset') == '') ? 0 : (int) $request->request->get('offset');
        $limit = ($request->request->get('limit') == '') ? 0 : (int) $request->request->get('limit');
        $isPreloadRequest = ($request->request->get('isPreloadRequest') != 'true') ? false : true;

        $this->sessionCacheToken = $request->request->get('sessionCacheToken');

        $arrJSON = array(
            'CACHE_EXPIRATION_TIMEOUT' => static::CACHE_EXPIRATION_TIMEOUT,
            'loadedItemsFromSession'   => 0,
            'status'                   => 'success',
            'isPreloadRequest'         => $isPreloadRequest,
            'sessionCacheToken'        => $this->sessionCacheToken,
            'countItems'               => 0,
            'offset'                   => $offset,
            'limit'                    => $limit,
            'eventIds'                 => $arrIds,
            'arrFields'                => $arrFields,
            'arrEventData'             => array(),
        );

        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from('tl_calendar_events', 't')
            ->where($qb->expr()->in('t.id', ':ids'))
            ->setParameter('ids', $arrIds, Connection::PARAM_INT_ARRAY)
            ->orderBy('t.startDate', 'ASC');

        // Offset
        if ($offset > 0)
        {
            $qb->setFirstResult($offset);
        }

        // Limit
        if ($limit > 0)
        {
            $qb->setMaxResults($limit);
        }

        /** @var \Doctrine\DBAL\Driver\PDOStatement $results */
        $results = $qb->execute();

        $count = 0;
        while (false !== ($arrEvent = $results->fetch()))
        {
            $count++;
            $oData = null;

            /** @var  CalendarEventsModel $objEvent */
            $objEvent = $calendarEventsModelAdapter->findByPk($arrEvent['id']);
            if ($objEvent !== null)
            {
                $strToken = $this->sessionCacheToken . $arrEvent['id'];

                // Try to load from cache
                if (null !== ($oData = $this->sessionCache->get($strToken)))
                {
                    $arrJSON['loadedItemsFromSession']++;
                }
                if ($oData === null)
                {
                    $oData = new \stdClass();
                    foreach ($arrFields as $field)
                    {
                        $v = $calendarEventsHelperAdapter->getEventData($objEvent, $field);
                        $oData->{$field} = $this->prepareValue($v);
                    }

                    // Cache data
                    $this->sessionCache->set($strToken, $oData, static::CACHE_EXPIRATION_TIMEOUT + time());
                }

                $arrJSON['arrEventData'][] = $oData;
            }
        }
        $arrJSON['countItems'] = $count;

        // If it is preload request do not return the items
        if ($isPreloadRequest === true)
        {
            $arrJSON['countItems'] = 0;
            $arrJSON['arrEventData'] = array();
        }

        // Allow cross domain requests
        return new JsonResponse($arrJSON, 200, array('Access-Control-Allow-Origin' => '*'));
    }

    /**
     * Deserialize arrays and convert binary uuids
     * @param $varValue
     * @return array|null|string
     */
    private function prepareValue($varValue)
    {
        /** @var  Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        // Transform binuuids
        $varValue = $validatorAdapter->isBinaryUuid($varValue) ? $stringUtilAdapter->binToUuid($varValue) : $varValue;

        // Deserialize arrays and convert binuuids
        $tmp = $stringUtilAdapter->deserialize($varValue);
        if (!empty($tmp) && is_array($tmp))
        {
            $tmp = $this->arrayMapRecursive($tmp, function ($v) {
                /** @var  Validator $validatorAdapter */
                $validatorAdapter = $this->framework->getAdapter(Validator::class);

                /** @var StringUtil $stringUtilAdapter */
                $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

                return (is_string($v) && $validatorAdapter->isBinaryUuid($v)) ? $stringUtilAdapter->binToUuid($v) : $v;
            });
        }
        $varValue = (!empty($tmp) && is_array($tmp)) ? $tmp : $varValue;

        $varValue = is_string($varValue) ? $stringUtilAdapter->decodeEntities($varValue) : $varValue;
        return $varValue;
    }

    /**
     * array_map for deep arrays
     * @param $arr
     * @param $fn
     * @return array
     */
    private function arrayMapRecursive(&$arr, $fn)
    {
        return array_map(function ($item) use ($fn) {
            return is_array($item) ? $this->arrayMapRecursive($item, $fn) : $fn($item);
        }, $arr);
    }
}
