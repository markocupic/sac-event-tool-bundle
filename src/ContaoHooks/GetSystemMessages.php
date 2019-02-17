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
use Contao\Database;
use Contao\RequestToken;
use Contao\System;

/**
 * Class GetSystemMessages
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class GetSystemMessages
{


    /**
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
                $objEvent = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE mainInstructor=? AND startDate>? ORDER BY startDate')->execute($objUser->id, 0);
                if ($objEvent->numRows)
                {
                    $strBuffer .= '<h3>Deine Events</h3>';
                    $strBuffer .= '<table>';
                    while ($objEvent->next())
                    {
                        $link = sprintf('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s', $objEvent->id, RequestToken::get());
                        $strBuffer .= sprintf('<tr><td>%s</td><td><a href="%s">%s</a></td></tr>', $this->_getRegistrationData($objEvent), $link, $objEvent->title);
                    }
                    $strBuffer .= '</table>';
                }
            }
        }
        return $strBuffer;

    }

    /**
     * @param $objEvent
     * @return string
     */
    protected function _getRegistrationData($objEvent)
    {
        $strRegistrations = '';
        $intNotConfirmed = 0;
        $intAccepted = 0;
        $intRefused = 0;
        $intWaitlisted = 0;
        $intUnsubscribedUser = 0;

        $eventsMemberModel = CalendarEventsMemberModel::findByEventId($objEvent->id);
        if ($eventsMemberModel !== null)
        {
            while ($eventsMemberModel->next())
            {

                if ($eventsMemberModel->stateOfSubscription === 'subscription-not-confirmed')
                {
                    $intNotConfirmed++;
                }
                if ($eventsMemberModel->stateOfSubscription === 'subscription-accepted')
                {
                    $intAccepted++;
                }
                if ($eventsMemberModel->stateOfSubscription === 'subscription-refused')
                {
                    $intRefused++;
                }
                if ($eventsMemberModel->stateOfSubscription === 'subscription-waitlisted')
                {
                    $intWaitlisted++;
                }
                if ($eventsMemberModel->stateOfSubscription === 'user-has-unsubscribed')
                {
                    $intUnsubscribedUser++;
                }
            }
            $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');

            $href = sprintf("'contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s&ref=%s'", $objEvent->id, REQUEST_TOKEN, $refererId);

            if ($intNotConfirmed > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge not-confirmed" title="%s unbestätigte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intNotConfirmed, $href, $intNotConfirmed);
            }
            if ($intAccepted > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge accepted" title="%s bestätigte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intAccepted, $href, $intAccepted);
            }
            if ($intRefused > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge refused" title="%s abgelehnte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intRefused, $href, $intRefused);
            }
            if ($intWaitlisted > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge waitlisted" title="%s Anmeldungen auf Warteliste" role="button" onclick="window.location.href=%s">%s</span>', $intWaitlisted, $href, $intWaitlisted);
            }
            if ($intUnsubscribedUser > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge unsubscribed-user" title="%s Abgemeldete Teilnehmer" role="button" onclick="window.location.href=%s">%s</span>', $intUnsubscribedUser, $href, $intUnsubscribedUser);
            }
        }


        return $strRegistrations;
    }
}


