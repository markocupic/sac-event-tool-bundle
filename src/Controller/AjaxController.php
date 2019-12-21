<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\Environment;
use Contao\Input;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AjaxController
 * @package Markocupic\SacEventToolBundle\Controller
 */
class AjaxController extends AbstractController
{

    /**
     * Handles ajax requests.
     * @Route("/ajax", name="sac_event_tool_ajax_frontend", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function ajaxAction()
    {
        $framework = $this->container->get('contao.framework');
        $framework->initialize();
        $controller = $this->container->get('markocupic.sac_event_tool_bundle.services.frontend_ajax.frontend_ajax');


        // Do allow only xhr requests
        if (Environment::get('isAjaxRequest') === false)
        {
            throw $this->createNotFoundException('The route "/ajax" is allowed to xhr requests only.');
        }

        // Ajax lazyload for the calendar event list module
        if (Input::post('action') === 'getEventData')
        {
            $controller->getEventData();
        }

        // Event story
        if (Input::post('action') === 'setPublishState')
        {
            $controller->setPublishState();
        }

        // Event story
        if (Input::post('action') === 'sortGallery')
        {
            $controller->sortGallery();
        }

        // Event story
        if (Input::post('action') === 'removeImage')
        {
            $controller->removeImage();
        }

        // Event story
        if (Input::post('action') === 'rotate-image')
        {
            $fileId = Input::post('fileId');
            $controller->rotateImage($fileId);
        }

        // Event story
        if (Input::post('action') === 'getCaption')
        {
            $controller->getCaption();
        }

        // Event story
        if (Input::post('action') === 'setCaption')
        {
            $controller->setCaption();
        }

        exit();
    }
}
