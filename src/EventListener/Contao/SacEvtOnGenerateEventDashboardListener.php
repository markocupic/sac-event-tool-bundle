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

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Contao\System;
use Knp\Menu\MenuItem;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Symfony\Component\Security\Core\Security;

class SacEvtOnGenerateEventDashboardListener
{
    private ContaoFramework $framework;
    private Security $security;

    public function __construct(ContaoFramework $framework, Security $security)
    {
        $this->framework = $framework;
        $this->security = $security;
    }

    public function onGenerateEventDashboardListener(MenuItem $menu, CalendarEventsModel $objEvent): void
    {
        // Set adapters
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $backendUserAdapter = $this->framework->getAdapter(BackendUser::class);

        $objUser = $backendUserAdapter->getInstance();
        $container = System::getContainer();
        $requestToken = $container->get('contao.csrf.token_manager')->getToken($container->getParameter('contao.csrf_token_name'))->getValue();

        $objCalendar = $objEvent->getRelated('pid');

        // Get the refererId
        $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');

        // Get the backend module name
        $module = $inputAdapter->get('do');

        // Go to event button
        $href = sprintf('contao/main.php?do=%s&id=%s&table=tl_calendar_events&act=%s&rt=%s&ref=%s', $module, $objEvent->id, 'edit', $requestToken, $refererId);
        $menu->addChild('Event', ['uri' => $href])
            ->setLinkAttribute('role', 'button')
            ->setLinkAttribute('class', 'tl_submit')
            ->setLinkAttribute('target', '_blank')
            //->setLinkAttribute('accesskey', 'm')
            ->setLinkAttribute('title', 'Event bearbeiten')
        ;

        // Go to event list button
        $href = sprintf('contao/main.php?do=%s&table=tl_calendar_events&id=%s&rt=%s&ref=%s', $module, $objCalendar->id, $requestToken, $refererId);
        $menu->addChild('Eventliste', ['uri' => $href])
            ->setLinkAttribute('role', 'button')
            ->setLinkAttribute('class', 'tl_submit')
            ->setLinkAttribute('target', '_blank')
            //->setLinkAttribute('accesskey', 'm')
            ->setLinkAttribute('title', 'Eventliste anzeigen')
        ;

        // Go to event preview button
        if (($href = $calendarEventsHelperAdapter->generateEventPreviewUrl($objEvent)) !== '') {
            $menu->addChild('Vorschau', ['uri' => $href])
                ->setLinkAttribute('role', 'button')
                ->setLinkAttribute('class', 'tl_submit')
                ->setLinkAttribute('target', '_blank')
                ->setLinkAttribute('accesskey', 'p')
                ->setLinkAttribute('title', 'Vorschau anzeigen [ALT + p]')
            ;
        }

        // Go to event participant list button
        if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $objEvent->id) || $objEvent->registrationGoesTo === $objUser->id) {
            $href = sprintf('contao/main.php?do=%s&table=tl_calendar_events_member&id=%s&rt=%s&ref=%s', $module, $inputAdapter->get('id'), $requestToken, $refererId);
            $menu->addChild('Teilnehmerliste', ['uri' => $href])
                ->setAttribute('role', 'button')
                ->setLinkAttribute('class', 'tl_submit')
                ->setLinkAttribute('target', '_blank')
                ->setLinkAttribute('accesskey', 'm')
                ->setLinkAttribute('title', 'Teilnehmerliste anzeigen und bearbeiten [ALT + m]')
            ;
        }

        // Go to "Angaben für Tourrapport erfassen"- & "Tourrapport und Vergütungsformular drucken" button
        if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $objEvent->id) || $objEvent->registrationGoesTo === $objUser->id) {
            if ('tour' === $objEvent->eventType || 'lastMinuteTour' === $objEvent->eventType) {
                $href = $controllerAdapter->addToUrl('call=writeTourReport&rt='.$requestToken, true);
                $menu->addChild('Tourenrapport bearbeiten', ['uri' => $href])
                    ->setLinkAttribute('role', 'button')
                    ->setLinkAttribute('class', 'tl_submit')
                    ->setLinkAttribute('target', '_blank')
                    ->setLinkAttribute('accesskey', 'r')
                    ->setLinkAttribute('title', 'Tourenrapport anzeigen und bearbeiten [ALT + r]')
                ;

                $href = sprintf('contao/main.php?do=%s&table=tl_calendar_events_instructor_invoice&id=%s&rt=%s&ref=%s', $module, $inputAdapter->get('id'), $requestToken, $refererId);
                $menu->addChild('Vergütungsformular und Tourenrapport drucken', ['uri' => $href])
                    ->setAttribute('role', 'button')
                    ->setLinkAttribute('class', 'tl_submit')
                    ->setLinkAttribute('target', '_blank')
                    ->setLinkAttribute('accesskey', 'i')
                    ->setLinkAttribute('title', 'Vergütungsformular und Tourenrapport anzeigen und drucken [ALT + i]')
                ;
            }
        }
    }
}
