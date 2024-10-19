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

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Knp\Menu\MenuItem;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * Generates the small button bar on the bottom of the event form.
 */
#[AsHook('generateEventDashboard', priority: 100)]
class GenerateEventDashboardListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(MenuItem $menu, CalendarEventsModel $objEvent): void
    {
        $calendarEventsUtilAdapter = $this->framework->getAdapter(CalendarEventsUtil::class);
        $request = $this->requestStack->getCurrentRequest();
        $eventId = $objEvent->id;
        $calendarId = $objEvent->getRelated('pid')->id;
        $strBackendModule = $request->query->get('do');
        $requestToken = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refererId = $request->attributes->get('_contao_referer_id');

        // "Go to event" button
        $href = $this->router->generate(
            'contao_backend',
            ['do' => $strBackendModule, 'table' => 'tl_calendar_events', 'act' => 'edit', 'id' => $eventId, 'rt' => $requestToken, 'ref' => $refererId],
        );

        $menu->addChild('Event', ['uri' => $href])
            ->setLinkAttribute('role', 'button')
            ->setLinkAttribute('class', 'tl_submit')
            ->setLinkAttribute('target', '_blank')
            //->setLinkAttribute('accesskey', 'm')
            ->setLinkAttribute('title', 'Event bearbeiten')
        ;

        // "Go to event-list" button
        $href = $this->router->generate(
            'contao_backend',
            ['do' => $strBackendModule, 'id' => $calendarId, 'table' => 'tl_calendar_events', 'rt' => $requestToken, 'ref' => $refererId],
        );

        $menu->addChild('Eventliste', ['uri' => $href])
            ->setLinkAttribute('role', 'button')
            ->setLinkAttribute('class', 'tl_submit')
            ->setLinkAttribute('target', '_blank')
            //->setLinkAttribute('accesskey', 'm')
            ->setLinkAttribute('title', 'Eventliste anzeigen')
        ;

        // "Go to event-preview" button
        if (($href = $calendarEventsUtilAdapter->generateEventPreviewUrl($objEvent)) !== '') {
            $menu->addChild('Vorschau', ['uri' => $href])
                ->setLinkAttribute('role', 'button')
                ->setLinkAttribute('class', 'tl_submit')
                ->setLinkAttribute('target', '_blank')
                ->setLinkAttribute('accesskey', 'p')
                ->setLinkAttribute('title', 'Vorschau anzeigen [ALT + p]')
                ->setLinkAttribute('onclick', 'return confirm(\'Wollen Sie diese Seite wirklich verlassen? Eventuell gemachte Änderungen am Event werden NICHT gespeichert.\')')
            ;
        }

        // "Go to event participant list" button
        if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $eventId)) {
            $href = $this->router->generate(
                'contao_backend',
                ['do' => $strBackendModule, 'table' => 'tl_calendar_events_member', 'id' => $eventId, 'rt' => $requestToken, 'ref' => $refererId],
            );

            $menu->addChild('Teilnehmerliste', ['uri' => $href])
                ->setAttribute('role', 'button')
                ->setLinkAttribute('class', 'tl_submit')
                ->setLinkAttribute('target', '_blank')
                ->setLinkAttribute('accesskey', 'm')
                ->setLinkAttribute('title', 'Teilnehmerliste anzeigen und bearbeiten [ALT + m]')
            ;
        }

        // Go to "Angaben für Tourrapport erfassen"- & "Tourrapport und Vergütungsformular drucken und einreichen" button
        if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $eventId)) {
            if (EventType::TOUR === $objEvent->eventType || EventType::LAST_MINUTE_TOUR === $objEvent->eventType) {
                $href = $this->router->generate(
                    'contao_backend',
                    ['do' => $strBackendModule, 'table' => 'tl_calendar_events', 'act' => 'edit', 'call' => 'writeTourReport', 'id' => $eventId, 'rt' => $requestToken, 'ref' => $refererId],
                );

                $menu->addChild('Tourrapport bearbeiten', ['uri' => $href])
                    ->setLinkAttribute('role', 'button')
                    ->setLinkAttribute('class', 'tl_submit')
                    ->setLinkAttribute('target', '_blank')
                    ->setLinkAttribute('accesskey', 'r')
                    ->setLinkAttribute('title', 'Tourrapport anzeigen und bearbeiten [ALT + r]')
                ;

                $href = $this->router->generate(
                    'contao_backend',
                    ['do' => $strBackendModule, 'table' => 'tl_calendar_events_instructor_invoice', 'id' => $eventId, 'rt' => $requestToken, 'ref' => $refererId],
                );

                $menu->addChild('Tourrapport und Vergütungsformular drucken und einreichen', ['uri' => $href])
                    ->setAttribute('role', 'button')
                    ->setLinkAttribute('class', 'tl_submit')
                    ->setLinkAttribute('target', '_blank')
                    ->setLinkAttribute('accesskey', 'i')
                    ->setLinkAttribute('title', 'Vergütungsformular und Tourrapport anzeigen und drucken [ALT + i]')
                ;
            }
        }
    }
}
