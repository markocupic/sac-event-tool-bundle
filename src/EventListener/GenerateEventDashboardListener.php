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

use Markocupic\SacEventToolBundle\Event\GenerateEventDashboardEvent;
use Markocupic\SacEventToolBundle\Feature\EventOperations\EventOperationsBuilder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Generates the small button navbar in the event form.
 *
 * The buttons are the operations of the EventOperationsBuilder (the same as in
 * the "my events" dashboard on the back end start page). Operations the user is
 * not allowed to execute are displayed greyed out, operations that do not exist
 * for the event (e.g. the tour report of a course) are not displayed.
 */
#[AsEventListener]
readonly class GenerateEventDashboardListener
{
    /**
     * The operations of the navbar in this order.
     */
    private const array OPERATIONS = [
        EventOperationsBuilder::OPERATION_EDIT_EVENT,
        EventOperationsBuilder::OPERATION_EVENT_LISTING,
        EventOperationsBuilder::OPERATION_EVENT_PREVIEW,
        EventOperationsBuilder::OPERATION_REGISTRATION_LIST,
        EventOperationsBuilder::OPERATION_TOUR_REPORT,
        EventOperationsBuilder::OPERATION_PRINT_REPORT,
    ];

    public function __construct(private EventOperationsBuilder $eventOperationsBuilder)
    {
    }

    public function __invoke(GenerateEventDashboardEvent $event): void
    {
        $menuItem = $event->getMenuItem();
        $do = (string) $event->getRequest()->query->get('do', 'calendar');

        $operations = $this->eventOperationsBuilder->build($event->getCalendarEvent(), $do);

        foreach (self::OPERATIONS as $name) {
            $operation = $operations[$name];

            if (!$operation->isAvailable()) {
                continue;
            }

            $title = $operation->title.(null !== $operation->accessKey ? \sprintf(' [ALT + %s]', $operation->accessKey) : '');

            if (!$operation->isEnabled()) {
                // Greyed out: no link, the title explains why
                $menuItem->addChild($operation->label)
                    ->setLabelAttribute('class', 'tl_submit sacevt-op--disabled')
                    ->setLabelAttribute('title', $operation->title)
                ;

                continue;
            }

            $child = $menuItem->addChild($operation->label, ['uri' => $operation->href])
                ->setLinkAttribute('role', 'button')
                ->setLinkAttribute('class', 'tl_submit')
                ->setLinkAttribute('target', '_blank')
                ->setLinkAttribute('rel', 'noopener')
                ->setLinkAttribute('title', $title)
            ;

            if (null !== $operation->accessKey) {
                $child->setLinkAttribute('accesskey', $operation->accessKey);
            }

            if (EventOperationsBuilder::OPERATION_EVENT_PREVIEW === $name) {
                $child->setLinkAttribute('onclick', 'return confirm(\'Wollen Sie diese Seite wirklich verlassen? Eventuell gemachte Änderungen am Event werden NICHT gespeichert.\')');
            }
        }
    }
}
