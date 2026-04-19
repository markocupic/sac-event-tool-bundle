<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\MenuFactory;
use Knp\Menu\Renderer\ListRenderer;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Event\GenerateEventDashboardEvent;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment as Twig;

/**
 * Generates the event member dashboard.
 */
#[AsHook('parseBackendTemplate', priority: 100)]
readonly class ParseBackendTemplateListener
{
    public function __construct(
        private CalendarEventsUtil $calendarEventsUtil,
        private ContaoFramework $framework,
        private EventDispatcherInterface $eventDispatcher,
        private RequestStack $requestStack,
        private Twig $twig,
    ) {
    }

    public function __invoke(string $buffer, string $template): string
    {
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $do = $inputAdapter->get('do');
        $table = $inputAdapter->get('table');
        $act = $inputAdapter->get('act');

        if ('be_main' !== $template || 'calendar' !== $do) {
            return $buffer;
        }

        // Add icon explanation legends to tl_calendar_events_member
        if ('tl_calendar_events' === $table && 'edit' === $act) {
            if (preg_match('/<div class="tl_formbody_edit">/sU', $buffer, $matches)) {
                $dashboard = $this->generateButtonNavbar();
                $buffer = preg_replace('/<div class="tl_formbody_edit">/sU', $matches[0].$dashboard, $buffer);
            }

            return $buffer;
        }

        // Add icon explanation legend to tl_calendar_events_member
        if ('tl_calendar_events_member' === $table) {
            $calEvent = $calendarEventsModelAdapter->findById($inputAdapter->get('id'));

            if (null === $calEvent) {
                return $buffer;
            }

            if (preg_match('/<table class=\"tl_listing(.*)<\/table>/sU', $buffer)) {
                $controllerAdapter->loadDataContainer('tl_calendar_events_member');
                $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

                $dataEvent = $calEvent->row();
                $dataEvent['time_span'] = $this->calendarEventsUtil->getEventPeriod($calEvent);
                $dataEvent['instructors'] = $this->calendarEventsUtil->getInstructorNamesAsArray($calEvent);

                $registration = [
                    'states' => array_diff(EventSubscriptionState::ALL, [EventSubscriptionState::SUBSCRIPTION_STATE_UNDEFINED]),
                ];

                $html = $this->twig->render('@MarkocupicSacEventTool/Backend/CalendarEventsMember/explanations.html.twig', [
                    'event' => $dataEvent,
                    'registration' => $registration,
                ]);

                // Add legend to the listing table
                $buffer = preg_replace('/<table class=\"tl_listing(.*)<\/table>/sU', '${0}'.$html, $buffer);
            }

            // Show a pop-up window if the participant is not confirmed and the instructor tries to change the participation status.
            if (preg_match_all('/<a href=\"\/contao\?do=calendar\&amp;id=(\\d+)&amp;table=tl_calendar_events_member&amp;act=toggle&amp;field=hasParticipated(.*)\"(.*)onclick="(.*)">(.*)<\/a>/sU', $buffer, $matches)) {
                foreach (array_keys($matches[0]) as $k) {
                    $regId = $matches[1][$k];

                    $registration = $calendarEventsMemberModelAdapter->findById($regId);

                    if (null === $registration) {
                        continue;
                    }

                    $allowedSubscriptionStates = [EventSubscriptionState::SUBSCRIPTION_ACCEPTED];

                    if (\in_array($registration->stateOfSubscription, $allowedSubscriptionStates, true)) {
                        continue;
                    }

                    $onClickAttr = \sprintf("if(window.confirm('Der Anmeldestatus dieser Person hat nicht den Status &laquo;BESTÄTIGT&raquo;. Bist du sicher, dass du den Teilnahmestatus ändern willst?')){%s}else{return false}", $matches[4][$k]);
                    $strLink = $matches[0][$k];
                    $strLinkNew = str_replace(
                        'onclick="'.$matches[4][$k].'"',
                        \sprintf('onclick="%s"', $onClickAttr),
                        $strLink,
                    );

                    $buffer = str_replace($strLink, $strLinkNew, $buffer);
                }
            }
        }

        return $buffer;
    }

    private function generateButtonNavbar(): string
    {
        // Set adapters
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $calEvent = $calendarEventsModelAdapter->findById($inputAdapter->get('id'));

        if (null === $calEvent || !$calEvent->tstamp || '' === $calEvent->title) {
            return '';
        }

        $calendar = $calEvent->getRelated('pid');

        if (null === $calendar) {
            return '';
        }

        $factory = new MenuFactory();
        $menuItem = $factory->createItem('Event Dashboard');

        $event = new GenerateEventDashboardEvent($menuItem, $calEvent, $this->requestStack->getCurrentRequest());

        // Use event listeners to generate the mini dashboard. So other plugins are able to add items as well.
        $this->eventDispatcher->dispatch($event);

        $renderer = new ListRenderer(new Matcher());

        return $this->twig->render('@MarkocupicSacEventTool/Backend/CalendarEvents/event_dashboard.html.twig', [
            'menu' => $renderer->render($event->getMenuItem()),
        ]);
    }
}
