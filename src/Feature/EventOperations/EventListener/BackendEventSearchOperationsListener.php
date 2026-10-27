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

namespace Markocupic\SacEventToolBundle\Feature\EventOperations\EventListener;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Markocupic\ContaoBackendEventSearch\Event\BuildSearchHitOperationsEvent;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperationsBuilder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Twig\Environment as Twig;

/**
 * Shows the operations of the "my events" dashboard (same icons, colors, permissions
 * and dropdown menu on small screens) in the results of the back end event search
 * (markocupic/contao-backend-event-search).
 *
 * The event is given as a string, so the Event-Tool works without the event search.
 */
#[AsEventListener(event: 'Markocupic\ContaoBackendEventSearch\Event\BuildSearchHitOperationsEvent')]
readonly class BackendEventSearchOperationsListener
{
    public function __construct(
        private ContaoFramework $framework,
        private EventOperationsBuilder $eventOperationsBuilder,
        private Twig $twig,
    ) {
    }

    public function __invoke(object $event): void
    {
        // The event search is an optional dependency. The class name must be a string,
        // so that PHPStan recognizes the check (see "discovering symbols" in the docs).
        if (!class_exists('Markocupic\ContaoBackendEventSearch\Event\BuildSearchHitOperationsEvent')) {
            return;
        }

        if (!$event instanceof BuildSearchHitOperationsEvent) {
            return;
        }

        $this->framework->initialize();

        $calendarEvent = $this->framework->getAdapter(CalendarEventsModel::class)->findById($event->getEventId());

        if (null === $calendarEvent) {
            return;
        }

        $allOperations = $this->eventOperationsBuilder->build($calendarEvent, $event->getBackendModule());

        $operations = [];

        foreach (EventOperationsBuilder::MENU_OPERATIONS as $name) {
            $operations['sacevt-op--'.$name] = $allOperations[$name]->toArray();
        }

        // Contao stores the title with encoded entities (Twig escapes it again)
        $stringUtil = $this->framework->getAdapter(StringUtil::class);
        $title = $stringUtil->restoreBasicEntities($stringUtil->decodeEntities((string) $calendarEvent->title));

        $event->setOperationsHtml($this->twig->render('@MarkocupicSacEventTool/Backend/EventOperations/operations_menu.html.twig', [
            'operations' => $operations,
            'menu_title' => $title,
            'with_portal' => true,
        ]));

        // The event title links to the edit form, if the user may open it (same as in the dashboard)
        $editOperation = $allOperations[EventOperationsBuilder::OPERATION_EDIT_EVENT];
        $event->setEditUrl($editOperation->href, $editOperation->title);
    }
}
