<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Event\PreviewUrlCreateEvent;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PreviewUrlCreateListener
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
     * Adds the calendar ID to the front end preview URL.
     *
     * @throws \RuntimeException
     */
    public function onPreviewUrlCreate(PreviewUrlCreateEvent $event): void
    {
        //#1: if (!$this->framework->isInitialized() || 'calendar' !== $event->getKey()) {
        if (!$this->framework->isInitialized() || 'sac_calendar_events_tool' !== $event->getKey()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \RuntimeException('The request stack did not contain a request');
        }

        // Return on the calendar list page
        if ('tl_calendar_events' === $request->query->get('table') && !$request->query->has('act')) {
            return;
        }

        if (null === ($eventModel = $this->getEventModel($this->getId($event, $request)))) {
            return;
        }

        $event->setQuery('calendar='.$eventModel->id);
    }

    /**
     * @return int|string
     */
    private function getId(PreviewUrlCreateEvent $event, Request $request)
    {
        // Overwrite the ID if the event settings are edited
        if ('tl_calendar_events' === $request->query->get('table') && 'edit' === $request->query->get('act')) {
            return $request->query->get('id');
        }

        return $event->getId();
    }

    /**
     * @param int|string $id
     */
    private function getEventModel($id): ?CalendarEventsModel
    {
        /** @var CalendarEventsModel $adapter */
        $adapter = $this->framework->getAdapter(CalendarEventsModel::class);

        return $adapter->findByPk($id);
    }
}
