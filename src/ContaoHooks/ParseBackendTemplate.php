<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\EventReleaseLevelPolicyModel;
use Contao\Input;
use Contao\System;

use Markocupic\SacEventToolBundle\CalendarSacEvents;

class ParseBackendTemplate
{

    /**
     * Constructor.
     *
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
    }


    /**
     * @param $strBuffer
     * @param $strTemplate
     * @return mixed
     */
    public function parseBackendTemplate($strBuffer, $strTemplate)
    {
        if ($strTemplate === 'be_main')
        {

            // Add icon explanation legend to tl_calendar_events_member
            if (Input::get('do') === 'sac_calendar_events_tool' && Input::get('table') === 'tl_calendar_events' && Input::get('act') === 'edit')
            {

                if (preg_match('/<input type="hidden" name="FORM_FIELDS\[\]" value="(.*)>/sU', $strBuffer, $matches))
                {

                    if (Input::get('call') !== 'writeTourReport')
                    {
                        $strDashboard = $this->generateEventDashboard();
                        $strBuffer = preg_replace('/<input type="hidden" name="FORM_FIELDS\[\]" value="(.*)>/sU', $matches[0] . $strDashboard, $strBuffer);

                    }
                    else
                    {

                        $strDashboard = $this->generateEventDashboard();
                        $strBuffer = preg_replace('/<input type="hidden" name="FORM_FIELDS\[\]" value="(.*)>/sU', $matches[0] . $strDashboard, $strBuffer);

                    }
                }

            }


            // Add icon explanation legend to tl_calendar_events_member
            if (Input::get('do') === 'sac_calendar_events_tool' && Input::get('table') === 'tl_calendar_events_member')
            {
                if (preg_match('/<table class=\"tl_listing(.*)<\/table>/sU', $strBuffer))
                {
                    Controller::loadDataContainer('tl_calendar_events_member');
                    Controller::loadLanguageFile('tl_calendar_events_member');
                    $strLegend = '<div class="legend-box">';
                    $strLegend .= '<div class="subscription-state-legend">';
                    $strLegend .= '<h3>Status der Event-Anmeldung</h3>';
                    $strLegend .= '<ul>';
                    $arrStates = $GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['stateOfSubscription']['options'];
                    foreach ($arrStates as $state)
                    {
                        $strLegend .= sprintf('<li><img src="%s/icons/%s.svg" width="16" height="16"> %s</li>', Config::get('SAC_EVT_ASSETS_DIR'), $state, $GLOBALS['TL_LANG']['tl_calendar_events_member'][$state]);
                    }
                    $strLegend .= '</ul>';
                    $strLegend .= '</div>';

                    $strLegend .= '<div class="participation-state-legend">';
                    $strLegend .= '<h3>Teilnahmestatus <span style="color:red">(Erst nach der Event-Durchf&uuml;hrung auszuf&uuml;llen!)</span></h3>';
                    $strLegend .= '<ul>';
                    $strLegend .= sprintf('<li><img src="%s/icons/%s.svg" width="16" height="16"> %s</li>', Config::get('SAC_EVT_ASSETS_DIR'), 'has-not-participated', 'Hat am Event nicht/noch nicht teilgenommen');
                    $strLegend .= sprintf('<li><img src="%s/icons/%s.svg" width="16" height="16"> %s</li>', Config::get('SAC_EVT_ASSETS_DIR'), 'has-participated', 'Hat am Event teilgenommen');
                    $strLegend .= '</ul>';
                    $strLegend .= '</div>';

                    $strLegend .= '</div>';

                    // Add legend to the listing table
                    $strBuffer = preg_replace('/<table class=\"tl_listing(.*)<\/table>/sU', '${0}' . $strLegend, $strBuffer);
                }

            }

            // Do not show submit container in the e-mail mode of tl_calendar_events_member
            if (Input::get('do') === 'sac_calendar_events_tool' && Input::get('table') === 'tl_calendar_events_member' && (Input::get('call') === 'refuseWithEmail' || Input::get('call') === 'acceptWithEmail'))
            {
                if (preg_match('/<div class=\"tl_formbody_submit(.*)<\/form>/sU', $strBuffer))
                {
                    // Remove submit tl_formbody_submit
                    $strBuffer = preg_replace('/<div class=\"tl_formbody_submit(.*)<\/form>/sU', '</form>', $strBuffer);
                }
            }
        }

        return $strBuffer;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function generateEventDashboard()
    {

        $objUser = BackendUser::getInstance();

        $objEvent = CalendarEventsModel::findByPk(Input::get('id'));
        if ($objEvent === null)
        {
            return '';
        }

        if (!$objEvent->tstamp || $objEvent->title === '')
        {
            return '';
        }

        $objCalendar = $objEvent->getRelated('pid');
        if ($objCalendar === null)
        {
            return '';
        }

        $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');
        $module = Input::get('do');

        $objTemplate = new BackendTemplate('be_calendar_events_event_dashboard');
        $objTemplate->objEvent = $objEvent;
        // Set button href
        $objTemplate->eventListHref = sprintf('contao?do=%s&table=tl_calendar_events&id=%s&rt=%s&ref=%s', $module, $objCalendar->id, REQUEST_TOKEN, $refererId);
        $objTemplate->writeTourReportHref = Controller::addToUrl('call=writeTourReport&rt=' . REQUEST_TOKEN, true);
        $objTemplate->participantListHref = sprintf('contao?do=%s&table=tl_calendar_events_member&id=%s&rt=%s&ref=%s', $module, Input::get('id'), REQUEST_TOKEN, $refererId);
        $objTemplate->invoiceListHref = sprintf('contao?do=%s&table=tl_calendar_events_instructor_invoice&id=%s&rt=%s&ref=%s', $module, Input::get('id'), REQUEST_TOKEN, $refererId);

        // Check if user is allowed
        if (EventReleaseLevelPolicyModel::hasWritePermission($objUser->id, $objEvent->id) || $objEvent->registrationGoesTo === $objUser->id)
        {

            if ($objEvent->eventType === 'tour' || $objEvent->eventType === 'lastMinuteTour')
            {
                $objTemplate->allowTourReportButton = true;
                $objTemplate->allowInvoiceListButton = true;
            }

            $objTemplate->allowParticipantListButton = true;


        }

        $objTemplate->allowEventPreviewButton = true;
        $objTemplate->eventPreviewUrl = CalendarSacEvents::generateEventPreviewUrl($objEvent);
        if ($objTemplate->eventPreviewUrl !== '')
        {
            $objTemplate->allowEventPreviewButton = true;
        }
        return $objTemplate->parse();
    }


}