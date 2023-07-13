<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
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
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        if ('be_main' === $strTemplate) {
            // Add icon explanation legend to tl_calendar_events_member
            if ('sac_calendar_events_tool' === $inputAdapter->get('do') && 'tl_calendar_events' === $inputAdapter->get('table') && 'edit' === $inputAdapter->get('act')) {
                if (preg_match('/<div class="tl_formbody_edit">/sU', $strBuffer, $matches)) {
                    $strDashboard = $this->_generateEventDashboard();
                    $strBuffer = preg_replace('/<div class="tl_formbody_edit">/sU', $matches[0].$strDashboard, $strBuffer);
                }
            }

            // Add icon explanation legend to tl_calendar_events_member
            if ('sac_calendar_events_tool' === $inputAdapter->get('do') && 'tl_calendar_events_member' === $inputAdapter->get('table')) {
                $objEvent = $calendarEventsModelAdapter->findByPk($inputAdapter->get('id'));

                if (null !== $objEvent) {
                    if (preg_match('/<table class=\"tl_listing(.*)<\/table>/sU', $strBuffer)) {
                        $controllerAdapter->loadDataContainer('tl_calendar_events_member');
                        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

                        $arrEvent = $objEvent->row();
                        $arrEvent['time_span'] = $calendarEventsHelperAdapter->getEventPeriod($objEvent);
                        $arrEvent['instructors'] = $calendarEventsHelperAdapter->getInstructorNamesAsArray($objEvent);

                        $arrRegistration = [];
                        $arrRegistration['states'] = array_diff(EventSubscriptionState::ALL, [EventSubscriptionState::SUBSCRIPTION_STATE_UNDEFINED]);

                        $html = $this->twig->render(
                            '@MarkocupicSacEventTool/CalendarEventsMember/explanations.html.twig',
                            [
                                'event' => $arrEvent,
                                'registration' => $arrRegistration,
                            ]
                        );

                        // Add legend to the listing table
                        $strBuffer = preg_replace('/<table class=\"tl_listing(.*)<\/table>/sU', '${0}'.$html, $strBuffer);
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
