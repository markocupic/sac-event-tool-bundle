<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;

use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ShowEventInModal
 * @package Markocupic\SacEventToolBundle
 */
class ShowEventInModal
{

    protected $objEvent;

    public function __construct($eventId)
    {
        $objEvent = CalendarEventsModel::findByPk($eventId);
        if ($objEvent !== null)
        {

            $this->objEvent = $objEvent;
        }
    }

    /**
     * @return JsonResponse
     */
    public function sendEventToBrowser()
    {
        global $objPage;
        $objPage = PageModel::findByIdOrAlias('touren-detailansicht');
        $GLOBALS['objPage'] = $objPage;
        $arrJson = array();
        $arrJson['status'] = 'false';

        if ($this->objEvent !== null)
        {
            Input::setGet('events', $this->objEvent->id);
            $objTemplate = new FrontendTemplate('event_modal_tour');
            $html = $objTemplate->parse();
            $html = Controller::replaceInsertTags($html);
            $arrJson['status'] = 'success';
            $arrJson['event'] = $html;
        }
        $response = new JsonResponse($arrJson);
        return $response;
    }
}