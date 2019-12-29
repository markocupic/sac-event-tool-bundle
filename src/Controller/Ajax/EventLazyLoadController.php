<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class EventLazyLoadController
 * @package Markocupic\SacEventToolBundle\Controller
 */
class EventLazyLoadController extends AbstractController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var CsrfTokenManagerInterface
     */
    private $tokenManager;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var string
     */
    private $tokenName;

    /**
     * EventLazyLoadController constructor.
     * Handles ajax requests.
     * Allow if ...
     * - is XmlHttpRequest
     * - csrf token is valid
     * @param ContaoFramework $framework
     * @param CsrfTokenManagerInterface $tokenManager
     * @param RequestStack $requestStack
     * @param Security $security
     * @param string $tokenName
     * @throws \Exception
     */

    public function __construct(ContaoFramework $framework, CsrfTokenManagerInterface $tokenManager, RequestStack $requestStack, Security $security, string $tokenName)
    {
        $this->framework = $framework;
        $this->tokenManager = $tokenManager;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->tokenName = $tokenName;

        $this->framework->initialize();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        // Validate request token
        if (!$this->tokenManager->isTokenValid(new CsrfToken($this->tokenName, $request->get('REQUEST_TOKEN'))))
        {
            throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
        }

        // Do allow only xhr requests
        if (!$request->isXmlHttpRequest())
        {
            throw $this->createNotFoundException('The route "/ajaxEventLazyLoad" is allowed to XMLHttpRequest requests only.');
        }
    }

    /**
     * !!! No more used !!!
     * Lazy load event properties from the event list module.
     * Add data-event-lazyload property to html tag and embed markocupic\sac-event-tool-bundle\src\Resources\public\js\event_data_lazy_load.js
     * <p data-event-lazyload="***eventId***,***strFieldname***"></p>
     * @Route("/ajaxEventLazyLoad/getEventData", name="sac_event_tool_ajax_event_lazy_load_get_event_data", defaults={"_scope" = "frontend"})
     */
    public function getEventDataAction(): JsonResponse
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        $arrJSON = [];

        $arrData = json_decode($request->request->get('data'));
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

        return new JsonResponse($arrJSON);
    }

}
