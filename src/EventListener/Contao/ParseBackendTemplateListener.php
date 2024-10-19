<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendTemplate;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Contao\System;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\MenuFactory;
use Knp\Menu\Renderer\ListRenderer;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Twig\Environment as Twig;

/**
 * Generates the event member dashboard.
 */
#[AsHook('parseBackendTemplate', priority: 100)]
class ParseBackendTemplateListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Twig $twig,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(string $strBuffer, string $strTemplate): string
    {
        // Set adapters
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        $calendarEventsUtilAdapter = $this->framework->getAdapter(CalendarEventsUtil::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        if ('be_main' === $strTemplate) {
            // Add icon explanation legend to tl_calendar_events_member
            if ('calendar' === $inputAdapter->get('do') && 'tl_calendar_events' === $inputAdapter->get('table') && 'edit' === $inputAdapter->get('act')) {
                if (preg_match('/<div class="tl_formbody_edit">/sU', $strBuffer, $matches)) {
                    $strDashboard = $this->_generateEventDashboard();
                    $strBuffer = preg_replace('/<div class="tl_formbody_edit">/sU', $matches[0].$strDashboard, $strBuffer);
                }
            }

            // Add icon explanation legend to tl_calendar_events_member
            if ('calendar' === $inputAdapter->get('do') && 'tl_calendar_events_member' === $inputAdapter->get('table')) {
                $objEvent = $calendarEventsModelAdapter->findByPk($inputAdapter->get('id'));

                if (null !== $objEvent) {
                    if (preg_match('/<table class=\"tl_listing(.*)<\/table>/sU', $strBuffer)) {
                        $controllerAdapter->loadDataContainer('tl_calendar_events_member');
                        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

                        $arrEvent = $objEvent->row();
                        $arrEvent['time_span'] = $calendarEventsUtilAdapter->getEventPeriod($objEvent);
                        $arrEvent['instructors'] = $calendarEventsUtilAdapter->getInstructorNamesAsArray($objEvent);

                        $arrRegistration = [];
                        $arrRegistration['states'] = array_diff(EventSubscriptionState::ALL, [EventSubscriptionState::SUBSCRIPTION_STATE_UNDEFINED]);

                        $html = $this->twig->render(
                            '@MarkocupicSacEventTool/Backend/CalendarEventsMember/explanations.html.twig',
                            [
                                'event' => $arrEvent,
                                'registration' => $arrRegistration,
                            ]
                        );

                        // Add legend to the listing table
                        $strBuffer = preg_replace('/<table class=\"tl_listing(.*)<\/table>/sU', '${0}'.$html, $strBuffer);
                    }

                    // Show a pop-up window if the participant is not confirmed and the instructor tries to change the participation status.
                    if (preg_match_all('/<a href=\"\/contao\?do=calendar\&amp;id=(\\d+)&amp;table=tl_calendar_events_member&amp;act=toggle&amp;field=hasParticipated(.*)\"(.*)onclick="(.*)">(.*)<\/a>/sU', $strBuffer, $matches)) {
                        foreach (array_keys($matches[0]) as $k) {
                            $regId = $matches[1][$k];

                            $registration = $calendarEventsMemberModelAdapter->findByPk($regId);
                            $allowedSubscriptionStates = [EventSubscriptionState::SUBSCRIPTION_ACCEPTED];

                            if (null !== $registration) {
                                if (\in_array($registration->stateOfSubscription, $allowedSubscriptionStates, true)) {
                                    continue;
                                }

                                $onClickAttr = sprintf("if(window.confirm('Der Anmeldestatus dieser Person hat nicht den Status &laquo;BESTÄTIGT&raquo;. Bist du sicher, dass du den Teilnahmestatus ändern willst?')){%s}else{return false}", $matches[4][$k]);
                                $strLink = $matches[0][$k];
                                $strLinkNew = str_replace(
                                    'onclick="'.$matches[4][$k].'"',
                                    sprintf('onclick="%s"', $onClickAttr),
                                    $strLink,
                                );

                                $strBuffer = str_replace($strLink, $strLinkNew, $strBuffer);
                            }
                        }
                    }
                }
            }
        }

        return $strBuffer;
    }

    /**
     * @throws \Exception
     */
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

        // HOOK: Use hooks to generate the mini dashboard. So other plugins are able to add items as well.
        if (isset($GLOBALS['TL_HOOKS']['generateEventDashboard']) && \is_array($GLOBALS['TL_HOOKS']['generateEventDashboard'])) {
            foreach ($GLOBALS['TL_HOOKS']['generateEventDashboard'] as $callback) {
                System::importStatic($callback[0])->{$callback[1]}($menu, $objEvent);
            }
        }

        $renderer = new ListRenderer(new Matcher());
        $objTemplate->menu = $renderer->render($menu);

        return $objTemplate->parse();
    }
}
