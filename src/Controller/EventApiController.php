<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Markocupic\SacEventToolBundle\FrontendAjax\EventApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class EventApiController
 * @package Markocupic\SacEventToolBundle\Controller
 */
class EventApiController extends AbstractController
{

    /**
     * Handles ajax requests.
     * @Route("/_event_api/get_event_data_by_ids", name="sac_event_tool_event_api_get_event_data_by_ids", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function getEventDataByIds()
    {
        //$response = new Response();
        //$response->headers->set('Access-Control-Allow-Origin', '*');
        //$response->send();
        $this->container->get('contao.framework')->initialize();
        $objApi = new EventApi();
        $objApi->sendEventDataByIds();

        return new Response();
    }
}
