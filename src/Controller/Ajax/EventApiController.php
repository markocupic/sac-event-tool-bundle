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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
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
     * Get event data: The controller is used for the tour list, where events are loaded by vue.js
     * Allow if ...
     * - is XmlHttpRequest
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
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

        $offset = ($request->request->get('offset') == '') ? 0 : (int)$request->request->get('offset');
        $limit = ($request->request->get('limit') == '') ? 0 : (int)$request->request->get('limit');
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
                if (null !== ($oData = $this->sessionCache->get($strToken)))
                {
                    $arrJSON['loadedItemsFromSession']++;
                }
                if ($oData === null)
                {
                    $oData = new \stdClass();
                    foreach ($arrFields as $field)
                    {
                        /** @var  CalendarEventsHelper $objEventsHelper */
                        $objEventsHelper = $calendarEventsHelperAdapter->getEventData($objEvent, $field);
                        $oData->{$field} = $objEventsHelper;
                    }
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

}
