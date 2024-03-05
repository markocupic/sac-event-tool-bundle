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
use Contao\CoreBundle\Event\PreviewUrlConvertEvent;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Events;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds the front end preview URL to the event.
 */
class PreviewUrlConvertListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContaoFramework $framework,
    ) {
    }

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

    private function getEventModel(Request $request): CalendarEventsModel|null
    {
        //#1: if (!$request->query->has('calendar')) {
        if (!$request->query->has('calendar')) {
            return null;
        }

        /** @var CalendarEventsModel $adapter */
        $adapter = $this->framework->getAdapter(CalendarEventsModel::class);

        //#2: return $adapter->findByPk($request->query->get('calendar'));
        return $adapter->findByPk($request->query->get('calendar'));
    }
}
