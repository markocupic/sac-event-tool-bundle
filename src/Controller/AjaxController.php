<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Contao\Input;
use Markocupic\SacEventToolBundle\FrontendAjax;

class AjaxController extends Controller
{
    /**
     * Handles ajax requests.
     * @return JsonResponse
     * @Route("/ajax", name="sac_event_tool_ajax_frontend", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function ajaxAction()
    {
        $this->container->get('contao.framework')->initialize();


        // Course Filter
        if (Input::post('action') === 'filterTourList')
        {
            $controller = new FrontendAjax();
            $controller->filterTourList();
        }

        // Course Filter
        if (Input::post('action') === 'filterCourseList')
        {
            $controller = new FrontendAjax();
            $controller->filterCourseList();
        }

        // Event story
        if (Input::post('action') === 'setPublishState')
        {
            $controller = new FrontendAjax();
            $controller->setPublishState();
        }

        // Event story
        if (Input::post('action') === 'sortGallery')
        {
            $controller = new FrontendAjax();
            $controller->sortGallery();
        }

        // Event story
        if (Input::post('action') === 'removeImage')
        {
            $controller = new FrontendAjax();
            $controller->removeImage();
        }


        //$controller = new FrontendAjax();
        //$data = ['bla'];
        //$response = new JsonResponse(array('result' => 'success', 'data' => $data));
        //$response->send();
        exit();
    }
}