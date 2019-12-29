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
 * Class FrontendAjax
 * @package Markocupic\SacEventToolBundle\Services\FrontendAjax
 */
class FrontendAjax
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * FrontendAjax constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Ajax lazyload for the calendar event list module
     * @return JsonResponse
     * @throws \Exception
     */
    public function getEventData()
    {
        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $arrJSON = [];

        $arrData = json_decode($inputAdapter->post('data'));
        foreach ($arrData as $i => $v)
        {
            // $v[0] is the event id
            $objEvent = $calendarEventsModelAdapter->findByPk($v[0]);
            if ($objEvent !== null)
            {
                // $v[1] fieldname/property
                $strHtml = $calendarEventsHelperAdapter->getEventData($objEvent, $v[1]);
                $arrData[$i][] = $strHtml;
            }
        }

        $arrJSON['status'] = 'success';
        $arrJSON['data'] = $arrData;

        $response = new JsonResponse($arrJSON);
        return $response->send();
    }

}
