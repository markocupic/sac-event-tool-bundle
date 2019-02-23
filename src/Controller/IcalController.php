<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\CalendarEventsModel;
use Contao\Input;
use Markocupic\SacEventToolBundle\Services\Ical\SendEventIcal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;


class IcalController extends AbstractController
{
    /**
     * Handles ical requests.
     * @Route("/ical", name="sac_event_tool_ical_frontend")
     */
    public function icalAction()
    {
        $this->container->get('contao.framework')->initialize();

        // Course Filter
        if (Input::get('eventId') > 0)
        {
            $objEvent = CalendarEventsModel::findByPk(Input::get('eventId'));
            {
                if ($objEvent !== null)
                {
                    $controller = new SendEventIcal();
                    $controller->sendIcsFile($objEvent);
                }
            }
        }
        exit();
    }
}