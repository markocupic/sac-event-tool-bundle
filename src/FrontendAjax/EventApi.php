<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\FrontendAjax;

use Contao\CalendarEventsModel;
use Contao\Input;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class EventApi
 * @package Markocupic\SacEventToolBundle\FrontendAjax
 */
class EventApi
{

    /**
     * This method is used for the tour listing in the frontend
     * @return JsonResponse
     * @throws \Exception
     */
    public function sendEventDataByIds()
    {
        $arrIds = Input::post('ids') == '' ? array() : Input::post('ids');
        $arrFields = Input::post('fields') == '' ? array() : Input::post('fields');
        if (is_array($arrIds))
        {
            foreach ($arrIds as $id)
            {
                $objEvent = CalendarEventsModel::findByPk($id);

                if ($objEvent !== null)
                {
                    $oData = new \stdClass();
                    foreach ($arrFields as $field)
                    {
                        $oData->{$field} = CalendarEventsHelper::getEventData($objEvent, $field);
                    }
                    $arrJSON[] = $oData;
                }
            }
        }

        $response = new JsonResponse($arrJSON);
        return $response->send();
    }
}
