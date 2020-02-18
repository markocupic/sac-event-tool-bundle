<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Event\PreviewUrlConvertEvent;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Events;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PreviewUrlConvertListener
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ContaoFramework
     */
    private $framework;

    public function __construct(RequestStack $requestStack, ContaoFramework $framework)
    {
        $this->requestStack = $requestStack;
        $this->framework = $framework;
    }

    /**
     * Adds the front end preview URL to the event.
     */
    public function onPreviewUrlConvert(PreviewUrlConvertEvent $event): void
    {
        if (!$this->framework->isInitialized()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || null === ($eventModel = $this->getEventModel($request))) {
            return;
        }

        /** @var Events $eventsAdapter */
        $eventsAdapter = $this->framework->getAdapter(Events::class);

        $event->setUrl($request->getSchemeAndHttpHost().'/'.$eventsAdapter->generateEventUrl($eventModel));
    }

    private function getEventModel(Request $request): ?CalendarEventsModel
    {
        //#1: if (!$request->query->has('calendar')) {
        if (!$request->query->has('sac_calendar_events_tool')) {
            return null;
        }

        /** @var CalendarEventsModel $adapter */
        $adapter = $this->framework->getAdapter(CalendarEventsModel::class);

        //#2: return $adapter->findByPk($request->query->get('calendar'));
        return $adapter->findByPk($request->query->get('sac_calendar_events_tool'));
    }
}
