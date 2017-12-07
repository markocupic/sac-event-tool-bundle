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

class AjaxController extends Controller
{
    /**
     * Handles ajax requests.
     *
     * @return JsonResponse
     *
     * @Route("/ajax", name="ajax_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function ajaxAction()
    {
        $this->container->get('contao.framework')->initialize();
        //$controller = new FrontendAjax();
        $data = ['bla'];
        $response = new JsonResponse(array('result' => 'success', 'data' => $data));
        $response->send();
        exit();
    }
}