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
                $objEvent = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE (mainInstructor=? OR registrationGoesTo=?) AND startDate>? ORDER BY startDate')->execute($objUser->id, $objUser->id, time() - 3 * 30 * 24 * 3600);
                if ($objEvent->numRows)
                {
                    $strBuffer .= '<h3>' . $GLOBALS['TL_LANG']['MSC']['yourUpcomingEvents'] . '</h3>';
                    $strBuffer .= '<table id="tl_upcoming_events" class="tl_listing">';
                    $strBuffer .= '<thead><tr><th>Teiln.</th><th>Datum &amp; Eventname</th><th></th></tr></thead>';
                    $strBuffer .= '<tbody>';

                    $container = System::getContainer();
                    $rt = $container->get('contao.csrf.token_manager')->getToken($container->getParameter('contao.csrf_token_name'))->getValue();

                    while ($objEvent->next())
                    {
                        $strCSSRowClass = ($objEvent->endDate > time()) ? 'upcoming-event' : 'past-event';
                        $link = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s', $objEvent->id, $rt);
                        $linkMemberList = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s', $objEvent->id, $rt);
                        $strBuffer .= sprintf('<tr class="hover-row %s"><td>%s</td><td>[%s] <a href="%s" style="text-decoration:underline" target="_blank" title="Event \'%s\' bearbeiten">%s</a></td><td><a href="%s" style="text-decoration:underline" target="_blank" title="Zur TN-Liste für \'%s\'">TN-Liste</a></td></tr>',
                            $strCSSRowClass,
                            CalendarEventsHelper::getEventStateOfSubscriptionBadgesString($objEvent),
                            Date::parse(Config::get('dateFormat'), $objEvent->startDate),
                            $link,
                            $objEvent->title,
                            $objEvent->title,
                            $linkMemberList,
                            $objEvent->title
                        );
                    }
                    $strBuffer .= '</tbody>';
                    $strBuffer .= '</table>';
                }
            }
        }
        return $strBuffer;

    }

}


