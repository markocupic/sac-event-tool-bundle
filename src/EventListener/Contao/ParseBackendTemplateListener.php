<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendTemplate;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\MenuFactory;
use Knp\Menu\Renderer\ListRenderer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\Bundle;

class ParseBackendTemplateListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * ParseBackendTemplateListener constructor.
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * @param $strBuffer
     * @param $strTemplate
     *
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

        if ('be_main' === $strTemplate) {
            // Add icon explanation legend to tl_calendar_events_member
            if ('sac_calendar_events_tool' === $inputAdapter->get('do') && 'tl_calendar_events' === $inputAdapter->get('table') && 'edit' === $inputAdapter->get('act')) {
                if (preg_match('/<input type="hidden" name="FORM_FIELDS\[\]" value="(.*)>/sU', $strBuffer, $matches)) {
                    if ('writeTourReport' !== $inputAdapter->get('call')) {
                        $strDashboard = $this->_generateEventDashboard();
                        $strBuffer = preg_replace('/<input type="hidden" name="FORM_FIELDS\[\]" value="(.*)>/sU', $matches[0].$strDashboard, $strBuffer);
                    } else {
                        $strDashboard = $this->_generateEventDashboard();
                        $strBuffer = preg_replace('/<input type="hidden" name="FORM_FIELDS\[\]" value="(.*)>/sU', $matches[0].$strDashboard, $strBuffer);
                    }
                }
            }

            // Add icon explanation legend to tl_calendar_events_member
            if ('sac_calendar_events_tool' === $inputAdapter->get('do') && 'tl_calendar_events_member' === $inputAdapter->get('table')) {
                $objEvent = $calendarEventsModelAdapter->findByPk($inputAdapter->get('id'));

                if (null !== $objEvent) {
                    if (preg_match('/<table class=\"tl_listing(.*)<\/table>/sU', $strBuffer)) {
                        $controllerAdapter->loadDataContainer('tl_calendar_events_member');
                        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');
                        $strLegend = '';

                        $strLegend .= '<div class="legend-box">';

                        // Event details
                        $strLegend .= '<div class="event-detail-legend">';
                        $strLegend .= '<h3>'.$stringUtilAdapter->substr($objEvent->title, 30, '...').'</h3>';
                        $strLegend .= '<p>'.$calendarEventsHelperAdapter->getEventPeriod($objEvent).'</p>';
                        $strLegend .= '<p><strong>Leiter:</strong><br>'.implode('<br>', $calendarEventsHelperAdapter->getInstructorNamesAsArray($objEvent)).'</p>';
                        $strLegend .= '</div>';

                        $strLegend .= '<div class="subscription-state-legend">';
                        $strLegend .= '<h3>Status der Event-Anmeldung</h3>';
                        $strLegend .= '<ul>';
                        $arrStates = $GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['stateOfSubscription']['options'];

                        foreach ($arrStates as $state) {
                            $strLegend .= sprintf('<li><img src="%s/icons/%s.svg" width="16" height="16"> %s</li>', Bundle::ASSET_DIR, $state, $GLOBALS['TL_LANG']['MSC'][$state]);
                        }
                        $strLegend .= '</ul>';
                        $strLegend .= '</div>';

                        $strLegend .= '<div class="participation-state-legend">';
                        $strLegend .= '<h3>Teilnahmestatus <span style="color:red">(Erst nach der Event-Durchführung auszufüllen!)</span></h3>';
                        $strLegend .= '<ul>';
                        $strLegend .= sprintf('<li><img src="%s/icons/%s.svg" width="16" height="16"> %s</li>', Bundle::ASSET_DIR, 'has-not-participated', 'Hat am Event nicht/noch nicht teilgenommen');
                        $strLegend .= sprintf('<li><img src="%s/icons/%s.svg" width="16" height="16"> %s</li>', Bundle::ASSET_DIR, 'has-participated', 'Hat am Event teilgenommen');
                        $strLegend .= '</ul>';
                        $strLegend .= '</div>';

                        $strLegend .= '</div>';

                        // Add legend to the listing table
                        $strBuffer = preg_replace('/<table class=\"tl_listing(.*)<\/table>/sU', '${0}'.$strLegend, $strBuffer);
                    }
                }
            }

            // Do not show submit container in the e-mail mode of tl_calendar_events_member
            if ('sac_calendar_events_tool' === $inputAdapter->get('do') && 'tl_calendar_events_member' === $inputAdapter->get('table') && ('refuseWithEmail' === $inputAdapter->get('call') || 'accept_with_email' === $inputAdapter->get('call'))) {
                if (preg_match('/<div class=\"tl_formbody_submit(.*)<\/form>/sU', $strBuffer)) {
                    // Remove submit tl_formbody_submit
                    $strBuffer = preg_replace('/<div class=\"tl_formbody_submit(.*)<\/form>/sU', '</form>', $strBuffer);
                }
            }
        }

        return $strBuffer;
    }

    private function _generateEventDashboard(): string
    {
        // Set adapters
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $objEvent = $calendarEventsModelAdapter->findByPk($inputAdapter->get('id'));

        if (null === $objEvent) {
            return '';
        }

        if (!$objEvent->tstamp || '' === $objEvent->title) {
            return '';
        }

        $objCalendar = $objEvent->getRelated('pid');

        if (null === $objCalendar) {
            return '';
        }

        $objTemplate = new BackendTemplate('be_calendar_events_event_dashboard');

        // Use KnpMenu to generate button-menu
        $factory = new MenuFactory();
        $menu = $factory->createItem('Event Dashboard');

        // HOOK: Use hooks to generate the mini dashboard. Soother plugins are able to add items as well.
        if (isset($GLOBALS['TL_HOOKS']['sacEvtOnGenerateEventDashboard']) && \is_array($GLOBALS['TL_HOOKS']['sacEvtOnGenerateEventDashboard'])) {
            foreach ($GLOBALS['TL_HOOKS']['sacEvtOnGenerateEventDashboard'] as $callback) {
                System::importStatic($callback[0])->{$callback[1]}($menu, $objEvent);
            }
        }

        $renderer = new ListRenderer(new Matcher());
        $objTemplate->menu = $renderer->render($menu);

        return $objTemplate->parse();
    }
}
