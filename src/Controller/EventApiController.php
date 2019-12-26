<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Markocupic\SacEventToolBundle\FrontendAjax\EventApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Contao\Input;
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
        $framework = $this->container->get('contao.framework');
        $framework->initialize();

        $objApi = $this->container->get('Markocupic\SacEventToolBundle\Services\FrontendAjax\EventApi');

        /** @var Input $inputAdapter */
        $inputAdapter = $framework->getAdapter(Input::class);

        $arrIds = $inputAdapter->post('ids') == '' ? array() : $inputAdapter->post('ids');
        $arrFields = $inputAdapter->post('fields') == '' ? array() : $inputAdapter->post('fields');

        $objApi->getEventDataByIds($arrIds, $arrFields);

        return new Response();
    }
}
