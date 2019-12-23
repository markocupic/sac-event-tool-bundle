<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\CalendarEventsModel;
use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Input;
use Contao\Template;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;

/**
 * Class UserPortraitController
 * @package Markocupic\SacEventToolBundle\Controller\ContentElement
 * @ContentElement("user_portrait", category="sac_event_tool_content_elements", template="ce_user_portrait")
 */
class UserPortraitController extends AbstractContentElementController
{

    /**
     * @var UserModel
     */
    protected $objUser;

    /**
     * @param Request $request
     * @param ContentModel $model
     * @param string $section
     * @param array|null $classes
     * @return Response
     */
    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null): Response
    {
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ContentModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        /** @var  Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        /** @var  Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var  UserModel $userModelAdapter */
        $userModelAdapter = $this->get('contao.framework')->getAdapter(UserModel::class);

        if (!empty($inputAdapter->get('username')))
        {
            if (null === ($this->objUser = $userModelAdapter->findByUsername($inputAdapter->get('username'))))
            {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        // Do not show disabled users
        if (null === $this->objUser || $this->objUser->disable)
        {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $arrUser = $this->objUser->row();
        $template->user = $arrUser;

        // List all courses
        $arrEvents = array();
        $objEvent = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE published=? AND startTime > ? ORDER BY startDate')->execute('1', time());
        while ($objEvent->next())
        {
            $eventModel = $calendarEventsModelAdapter->findByPk($objEvent->id);
            if ($eventModel !== null)
            {
                $arrInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent->id, true);
                if (in_array($this->objUser->id, $arrInstructors))
                {
                    $arrEvents[$objEvent->eventType][] = $objEvent->row();
                }
            }
        }
        $template->events = $arrEvents;

        return $template->getResponse();
    }

}
