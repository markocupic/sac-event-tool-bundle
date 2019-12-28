<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Services\FrontendAjax;

use Contao\CalendarEventsModel;
use Contao\Input;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Contao\CoreBundle\Framework\ContaoFramework;

/**
 * Class EventApi
 * @package Markocupic\SacEventToolBundle\Services\FrontendAjax
 */
class EventApi
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * EventApi constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * This method is used for the tour listing in the frontend
     * @important For Cors Requests Markocupic\SacEventToolBundle\EventListener\Cors\CorsListener has to be enabled
     * @return JsonResponse
     * @throws \Exception
     */
    public function getEventDataByIds(array $arrEventIds, array $arrFields): JsonResponse
    {
        $arrJSON = array(
            'status'       => 'success',
            'eventIds'     => $arrEventIds,
            'arrFields'    => $arrFields,
            'arrEventData' => array()
        );

        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        foreach ($arrEventIds as $id)
        {
            $objEvent = $calendarEventsModelAdapter->findByPk($id);

            if ($objEvent !== null)
            {
                $oData = new \stdClass();
                foreach ($arrFields as $field)
                {
                    $oData->{$field} = $calendarEventsHelperAdapter->getEventData($objEvent, $field);
                }
                $arrJSON['arrEventData'][] = $oData;
            }
        }

        // Allow cross domain requests
        $response = new JsonResponse($arrJSON, 200, array('Access-Control-Allow-Origin' => '*'));
        return $response->send();
    }
}
