<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
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