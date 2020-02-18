<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\EventReleaseLevelPolicyModel;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\MenuFactory;
use Knp\Menu\Renderer\ListRenderer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;

/**
 * Class ParseBackendTemplateListener
 * @package Markocupic\SacEventToolBundle\EventListener\Contao
 */
class ParseBackendTemplateListener
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * ParseBackendTemplateListener constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * @param $strBuffer
     * @param $strTemplate
     * @return string
     * @throws \Exception
     */
    public function onParseBackendTemplate($strBuffer, $strTemplate): string
    {
        // Set adapters
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $configAdapter = $this->framework->getAdapter(Config::class);

        if ($strTemplate === 'be_main')
        {
            // Add icon explanation legend to tl_calendar_events_member
            if ($inputAdapter->get('do') === 'sac_calendar_events_tool' && $inputAdapter->get('table') === 'tl_calendar_events' && $inputAdapter->get('act') === 'edit')
            {
                if (preg_match('/<input type="hidden" name="FORM_FIELDS\[\]" value="(.*)>/sU', $strBuffer, $matches))
                {
                    if ($inputAdapter->get('call') !== 'writeTourReport')
                    {
                        $strDashboard = $this->_generateEventDashboard();
                        $strBuffer = preg_replace('/<input type="hidden" name="FORM_FIELDS\[\]" value="(.*)>/sU', $matches[0] . $strDashboard, $strBuffer);
                    }
                    else
                    {
                        $strDashboard = $this->_generateEventDashboard();
                        $strBuffer = preg_replace('/<input type="hidden" name="FORM_FIELDS\[\]" value="(.*)>/sU', $matches[0] . $strDashboard, $strBuffer);
                    }
                }
            }

            // Add icon explanation legend to tl_calendar_events_member
            if ($inputAdapter->get('do') === 'sac_calendar_events_tool' && $inputAdapter->get('table') === 'tl_calendar_events_member')
            {
                $objEvent = $calendarEventsModelAdapter->findByPk($inputAdapter->get('id'));
                if ($objEvent !== null)
                {
                    if (preg_match('/<table class=\"tl_listing(.*)<\/table>/sU', $strBuffer))
                    {
                        $controllerAdapter->loadDataContainer('tl_calendar_events_member');
                        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');
                        $strLegend = '';

                        $strLegend .= '<div class="legend-box">';

                        // Event details
                        $strLegend .= '<div class="event-detail-legend">';
                        $strLegend .= '<h3>' . $stringUtilAdapter->substr($objEvent->title, 30, '...') . '</h3>';
                        $strLegend .= '<p>' . $calendarEventsHelperAdapter->getEventPeriod($objEvent) . '</p>';
                        $strLegend .= '<p><strong>Leiter:</strong><br>' . implode("<br>", $calendarEventsHelperAdapter->getInstructorNamesAsArray($objEvent)) . '</p>';
                        $strLegend .= '</div>';

                        $strLegend .= '<div class="subscription-state-legend">';
                        $strLegend .= '<h3>Status der Event-Anmeldung</h3>';
                        $strLegend .= '<ul>';
                        $arrStates = $GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['stateOfSubscription']['options'];
                        foreach ($arrStates as $state)
                        {
                            $strLegend .= sprintf('<li><img src="%s/icons/%s.svg" width="16" height="16"> %s</li>', $configAdapter->get('SAC_EVT_ASSETS_DIR'), $state, $GLOBALS['TL_LANG']['tl_calendar_events_member'][$state]);
                        }
                        $strLegend .= '</ul>';
                        $strLegend .= '</div>';

                        $strLegend .= '<div class="participation-state-legend">';
                        $strLegend .= '<h3>Teilnahmestatus <span style="color:red">(Erst nach der Event-Durchf&uuml;hrung auszuf&uuml;llen!)</span></h3>';
                        $strLegend .= '<ul>';
                        $strLegend .= sprintf('<li><img src="%s/icons/%s.svg" width="16" height="16"> %s</li>', $configAdapter->get('SAC_EVT_ASSETS_DIR'), 'has-not-participated', 'Hat am Event nicht/noch nicht teilgenommen');
                        $strLegend .= sprintf('<li><img src="%s/icons/%s.svg" width="16" height="16"> %s</li>', $configAdapter->get('SAC_EVT_ASSETS_DIR'), 'has-participated', 'Hat am Event teilgenommen');
                        $strLegend .= '</ul>';
                        $strLegend .= '</div>';

                        $strLegend .= '</div>';

                        // Add legend to the listing table
                        $strBuffer = preg_replace('/<table class=\"tl_listing(.*)<\/table>/sU', '${0}' . $strLegend, $strBuffer);
                    }
                }
            }

            // Do not show submit container in the e-mail mode of tl_calendar_events_member
            if ($inputAdapter->get('do') === 'sac_calendar_events_tool' && $inputAdapter->get('table') === 'tl_calendar_events_member' && ($inputAdapter->get('call') === 'refuseWithEmail' || $inputAdapter->get('call') === 'accept_with_email'))
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
     */
    private function _generateEventDashboard(): string
    {
        // Set adapters
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $eventReleaseLevelPolicyModelAdapter = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $backendUserAdapter = $this->framework->getAdapter(BackendUser::class);

        $objUser = $backendUserAdapter->getInstance();
        $container = System::getContainer();
        $requestToken = $container->get('contao.csrf.token_manager')->getToken($container->getParameter('contao.csrf_token_name'))->getValue();

        $objEvent = $calendarEventsModelAdapter->findByPk($inputAdapter->get('id'));
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

        // Get the refererId
        $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');

        // Get the backend module name
        $module = $inputAdapter->get('do');

        $objTemplate = new BackendTemplate('be_calendar_events_event_dashboard');

        // Use KnpMenu to generate button-menu
        $factory = new MenuFactory();
        $menu = $factory->createItem('Event Dashboard');

        // Go to event list button
        $eventListHref = sprintf('contao/main.php?do=%s&table=tl_calendar_events&id=%s&rt=%s&ref=%s', $module, $objCalendar->id, $requestToken, $refererId);
        $menu->addChild('Eventliste', ['uri' => $eventListHref])
            ->setLinkAttribute('role', 'button')
            ->setLinkAttribute('class', 'tl_submit')
            ->setLinkAttribute('target', '_blank')
            //->setLinkAttribute('accesskey', 'm')
            ->setLinkAttribute('title', 'Eventliste anzeigen');

        // Go to event preview button
        if (($previewHref = $calendarEventsHelperAdapter->generateEventPreviewUrl($objEvent)) != '')
        {
            $menu->addChild('Vorschau', ['uri' => $previewHref])
                ->setLinkAttribute('role', 'button')
                ->setLinkAttribute('class', 'tl_submit')
                ->setLinkAttribute('target', '_blank')
                ->setLinkAttribute('accesskey', 'p')
                ->setLinkAttribute('title', 'Vorschau anzeigen [ALT + p]');
        }

        // Go to event participant list button
        if ($eventReleaseLevelPolicyModelAdapter->hasWritePermission($objUser->id, $objEvent->id) || $objEvent->registrationGoesTo === $objUser->id)
        {
            $participantListHref = sprintf('contao/main.php?do=%s&table=tl_calendar_events_member&id=%s&rt=%s&ref=%s', $module, $inputAdapter->get('id'), $requestToken, $refererId);
            $menu->addChild('Teilnehmerliste', ['uri' => $participantListHref])
                ->setAttribute('role', 'button')
                ->setLinkAttribute('class', 'tl_submit')
                ->setLinkAttribute('target', '_blank')
                ->setLinkAttribute('accesskey', 'm')
                ->setLinkAttribute('title', 'Teilnehmerliste anzeigen [ALT + m]');
        }

        // Go to "Angaben für Tourrapport erfassen"- & "Tourrapport und Vergütungsformular drucken" button
        if ($eventReleaseLevelPolicyModelAdapter->hasWritePermission($objUser->id, $objEvent->id) || $objEvent->registrationGoesTo === $objUser->id)
        {
            if ($objEvent->eventType === 'tour' || $objEvent->eventType === 'lastMinuteTour')
            {
                $writeTourReportHref = $controllerAdapter->addToUrl('call=writeTourReport&rt=' . $requestToken, true);
                $menu->addChild('Tourrapport erfassen', ['uri' => $writeTourReportHref])
                    ->setLinkAttribute('role', 'button')
                    ->setLinkAttribute('class', 'tl_submit')
                    ->setLinkAttribute('target', '_blank')
                    ->setLinkAttribute('accesskey', 'r')
                    ->setLinkAttribute('title', 'Tourrapport anzeigen [ALT + r]');

                $invoiceListHref = sprintf('contao/main.php?do=%s&table=tl_calendar_events_instructor_invoice&id=%s&rt=%s&ref=%s', $module, $inputAdapter->get('id'), $requestToken, $refererId);
                $menu->addChild('Tourrapport und Verg&uuml;tungsformulare drucken', ['uri' => $invoiceListHref])
                    ->setAttribute('role', 'button')
                    ->setLinkAttribute('class', 'tl_submit')
                    ->setLinkAttribute('target', '_blank')
                    ->setLinkAttribute('accesskey', 'i')
                    ->setLinkAttribute('title', 'Tourrapport und Verguetungsformulare drucken [ALT + i]');
            }
        }

        $renderer = new ListRenderer(new Matcher());
        $objTemplate->menu = $renderer->render($menu);

        return $objTemplate->parse();
    }

}
