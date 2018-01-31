<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\Environment;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Contao\Input;
use Markocupic\SacEventToolBundle\FrontendAjax\FrontendAjax;

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
        if (Environment::get(isAjaxRequest) === false)
        {
            throw $this->createNotFoundException('The route "/ajax" is allowed to xhr requests only.');
        }
        
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

        // Event story
        if (Input::post('action') === 'getCaption')
        {
            $controller = new FrontendAjax();
            $controller->getCaption();
        }

        // Event story
        if (Input::post('action') === 'setCaption')
        {
            $controller = new FrontendAjax();
            $controller->setCaption();
        }


        exit();
    }
}