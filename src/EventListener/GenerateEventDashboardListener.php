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

namespace Markocupic\SacEventToolBundle\EventListener;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Event\GenerateEventDashboardEvent;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\RouterInterface;

/**
 * Generates the small button navbar in the event form.
 */
#[AsEventListener]
readonly class GenerateEventDashboardListener
{
    public function __construct(
        private CalendarEventsUtil $calendarEventsUtil,
        private Security $security,
        private ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private RouterInterface $router,
    ) {
    }

    public function __invoke(GenerateEventDashboardEvent $event): void
    {
        $menuItem = $event->getMenuItem();
        $calEvent = $event->getCalendarEvent();
        $request = $event->getRequest();

        $eventId = $calEvent->id;
        $calendarId = $calEvent->getRelated('pid')->id;
        $do = $request->query->get('do');
        $rt = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refId = $request->attributes->get('_contao_referer_id');

        // "Go to event" button
        $href = $this->router->generate(
            'contao_backend',
            ['do' => $do, 'table' => 'tl_calendar_events', 'act' => 'edit', 'id' => $eventId, 'rt' => $rt, 'ref' => $refId],
        );

        $menuItem->addChild('Event', ['uri' => $href])
            ->setLinkAttribute('role', 'button')
            ->setLinkAttribute('class', 'tl_submit')
            ->setLinkAttribute('target', '_blank')
            ->setLinkAttribute('rel', 'noopener')
            // ->setLinkAttribute('accesskey', 'm')
            ->setLinkAttribute('title', 'Event bearbeiten')
        ;

        // "Go to event-list" button
        $href = $this->router->generate(
            'contao_backend',
            ['do' => $do, 'id' => $calendarId, 'table' => 'tl_calendar_events', 'rt' => $rt, 'ref' => $refId],
        );

        $menuItem->addChild('Eventliste', ['uri' => $href])
            ->setLinkAttribute('role', 'button')
            ->setLinkAttribute('class', 'tl_submit')
            ->setLinkAttribute('target', '_blank')
            ->setLinkAttribute('rel', 'noopener')
            // ->setLinkAttribute('accesskey', 'm')
            ->setLinkAttribute('title', 'Eventliste anzeigen')
        ;

        // "Go to event-preview" button
        if (($href = $this->calendarEventsUtil->generateEventPreviewUrl($calEvent)) !== '') {
            $menuItem->addChild('Vorschau', ['uri' => $href])
                ->setLinkAttribute('role', 'button')
                ->setLinkAttribute('class', 'tl_submit')
                ->setLinkAttribute('target', '_blank')
                ->setLinkAttribute('rel', 'noopener')
                ->setLinkAttribute('accesskey', 'p')
                ->setLinkAttribute('title', 'Vorschau anzeigen [ALT + p]')
                ->setLinkAttribute('onclick', 'return confirm(\'Wollen Sie diese Seite wirklich verlassen? Eventuell gemachte Änderungen am Event werden NICHT gespeichert.\')')
            ;
        }

        // "Go to event participant list" button
        if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $eventId)) {
            $href = $this->router->generate(
                'contao_backend',
                ['do' => $do, 'table' => 'tl_calendar_events_member', 'id' => $eventId, 'rt' => $rt, 'ref' => $refId],
            );

            $menuItem->addChild('Teilnehmerliste', ['uri' => $href])
                ->setAttribute('role', 'button')
                ->setLinkAttribute('class', 'tl_submit')
                ->setLinkAttribute('target', '_blank')
                ->setLinkAttribute('rel', 'noopener')
                ->setLinkAttribute('accesskey', 'm')
                ->setLinkAttribute('title', 'Teilnehmerliste anzeigen und bearbeiten [ALT + m]')
            ;
        }

        // Go to "Angaben für Tourrapport erfassen"- & "Tourrapport und
        // Vergütungsformular drucken und einreichen" button
        if ($this->security->isGranted('contao_user.sac_event_tool_permissions', 'can_edit_all_invoice_forms') || $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $eventId)) {
            if (EventType::TOUR === $calEvent->eventType || EventType::LAST_MINUTE_TOUR === $calEvent->eventType) {
                $href = $this->router->generate(
                    'contao_backend',
                    ['do' => $do, 'table' => 'tl_calendar_events', 'act' => 'edit', 'call' => 'writeTourReport', 'id' => $eventId, 'rt' => $rt, 'ref' => $refId],
                );

                $menuItem->addChild('Tourrapport bearbeiten', ['uri' => $href])
                    ->setLinkAttribute('role', 'button')
                    ->setLinkAttribute('class', 'tl_submit')
                    ->setLinkAttribute('target', '_blank')
                    ->setLinkAttribute('rel', 'noopener')
                    ->setLinkAttribute('accesskey', 'r')
                    ->setLinkAttribute('title', 'Tourrapport anzeigen und bearbeiten [ALT + r]')
                ;

                $href = $this->router->generate(
                    'contao_backend',
                    ['do' => $do, 'table' => 'tl_calendar_events_instructor_invoice', 'id' => $eventId, 'rt' => $rt, 'ref' => $refId],
                );

                $menuItem->addChild('Tourrapport und Vergütungsformular drucken und einreichen', ['uri' => $href])
                    ->setAttribute('role', 'button')
                    ->setLinkAttribute('class', 'tl_submit')
                    ->setLinkAttribute('target', '_blank')
                    ->setLinkAttribute('rel', 'noopener')
                    ->setLinkAttribute('accesskey', 'i')
                    ->setLinkAttribute('title', 'Vergütungsformular und Tourrapport anzeigen und drucken [ALT + i]')
                ;
            }
        }
    }
}
