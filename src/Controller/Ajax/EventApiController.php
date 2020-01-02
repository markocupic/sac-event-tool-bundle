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
     * Get event data by id and use the session cache
     * This controller is used for the "pilatus" export, where events are loaded by ajax when the modal windows opens
     * $_POST['REQUEST_TOKEN'], $_POST['id'], $_POST['fields'] as comma separated string is optional
     * @Route("/ajaxEventApi/getEventById", name="sac_event_tool_event_ajax_event_api_get_event_by_id", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function getEventById(): JsonResponse
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $eventId = (int)$request->request->get('id');
        $arrFields = $request->get('fields') != '' ? explode(',', $request->get('fields')) : [];

        $arrJSON = array(
            'status'       => 'error',
            'arrEventData' => '',
            'eventId'      => $eventId,
            'arrFields'    => $arrFields,
        );

        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $strFields = empty($arrFields) ? '*' : implode(',', $arrFields);
        $qb->select($strFields)
            ->from('tl_calendar_events', 't')
            ->where('t.id = :id')
            ->setParameter('id', $eventId);

        $qb->setMaxResults(1);

        /** @var \Doctrine\DBAL\Driver\PDOStatement $results */
        $results = $qb->execute();

        while (false !== ($arrEvent = $results->fetch()))
        {
            $arrJSON['status'] = 'success';
            $aEvent = array();

            foreach ($arrEvent as $k => $v)
            {
                $aEvent[$k] = $this->prepareValue($v);
            }
            $arrJSON['arrEventData'] = $aEvent;
        }

        // Allow cross domain requests
        return new JsonResponse($arrJSON, 200, array('Access-Control-Allow-Origin' => '*'));
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
                        $v = $calendarEventsHelperAdapter->getEventData($objEvent, $field);
                        $oData->{$field} = $this->prepareValue($v);
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
        if (!empty($tmp))
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
