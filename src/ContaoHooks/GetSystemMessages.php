<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;


use Contao\BackendUser;
use Contao\CalendarEventsMemberModel;
use Contao\Config;
use Contao\Database;
use Contao\Date;
use Contao\RequestToken;
use Contao\System;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;

/**
 * Class GetSystemMessages
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class GetSystemMessages
{


    /**
     * Show all upcoming events (where user is main instructor) for the logged in user
     * @return string
     */
    public function listUntreatedEventSubscriptions()
    {
        $strBuffer = '';
        if (TL_MODE === 'BE')
        {
            $objUser = BackendUser::getInstance();
            if ($objUser->id > 0)
            {
                $objEvent = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE mainInstructor=? AND startDate>? ORDER BY startDate')->execute($objUser->id, time() + 14 * 24 * 3600);
                if ($objEvent->numRows)
                {
                    $strBuffer .= '<h3>' . $GLOBALS['TL_LANG']['MSC']['yourUpcomingEvents'] . '</h3>';
                    $strBuffer .= '<table id="tl_upcoming_events" class="tl_listing">';
                    $strBuffer .= '<thead><tr><th>Teiln.</th><th>Eventname</th><th></th></tr></thead>';
                    $strBuffer .= '<tbody>';

                    $container = System::getContainer();
                    $rt = $container->get('contao.csrf.token_manager')->getToken($container->getParameter('contao.csrf_token_name'))->getValue();

                    while ($objEvent->next())
                    {
                        $link = sprintf('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s', $objEvent->id, $rt);
                        $linkMemberList = sprintf('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s', $objEvent->id, $rt);
                        $strBuffer .= sprintf('<tr><td>%s</td><td><a href="%s" title="Event \'%s\' bearbeiten">%s [%s]</a></td><td><a href="%s" title="Zur TN-Liste für \'%s\'">TN-Liste</a></td></tr>', CalendarEventsHelper::getEventStateOfSubscriptionBadgesString($objEvent), $link, $objEvent->title, $objEvent->title, Date::parse(Config::get('dateFormat'), $objEvent->startDate), $linkMemberList, $objEvent->title);
                    }
                    $strBuffer .= '</tbody>';
                    $strBuffer .= '</table>';
                }
            }
        }
        return $strBuffer;

    }

}


