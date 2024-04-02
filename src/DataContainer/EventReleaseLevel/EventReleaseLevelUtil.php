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

namespace Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Markocupic\SacEventToolBundle\Event\PublishEventEvent;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class EventReleaseLevelUtil
{
    // Adapters
    private Adapter $calendarEventsModel;
    private Adapter $message;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        // Adapters
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
    }

    public function hasValidEventReleaseLevel(int $eventId, int $eventReleaseLevelId): bool
    {
        $objEvent = $this->calendarEventsModel->findByPk($eventId);

        if (null === $objEvent) {
            throw new \RuntimeException('Event not found.');
        }

        $highestValidEventReleaseModel = EventReleaseLevelPolicyModel::findHighestLevelByEventId($objEvent->id);

        if (0 === $eventReleaseLevelId && null === $highestValidEventReleaseModel) {
            return true;
        }

        if (0 === $eventReleaseLevelId && null !== $highestValidEventReleaseModel) {
            return false;
        }

        $eventReleaseModel = EventReleaseLevelPolicyModel::findByPk($eventReleaseLevelId);

        return $highestValidEventReleaseModel->pid === $eventReleaseModel->pid;
    }

    /**
     * @throws \Exception
     */
    public function publishOrUnpublishEventDependingOnEventReleaseLevel(int $eventId, int $targetEventReleaseLevelId): int
    {
        $objEvent = $this->calendarEventsModel->findByPk($eventId);

        if (null === $objEvent) {
            throw new \Exception('Event not found.');
        }

        $eventReleaseModel = EventReleaseLevelPolicyModel::findByPk($targetEventReleaseLevelId);

        if (!$this->hasValidEventReleaseLevel($eventId, $targetEventReleaseLevelId)) {
            $lowestEventReleaseModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($objEvent->id);

            if (null === $lowestEventReleaseModel) {
                // If no ev.rel.level policy package is assigned to the calendar,
                // we set the ev.rel.level ID to 0
                $objEvent->eventReleaseLevel = 0;
            } else {
                // Set the lowest ev.rel.level ID
                $objEvent->eventReleaseLevel = $lowestEventReleaseModel->id;
            }

            // Unpublish event because evt.rel.level is invalid or 0.
            $objEvent->published = 0;

            if ($objEvent->isModified()) {
                $objEvent->tstamp = time();
                $objEvent->save();
            }

            $this->message->addError(
                sprintf(
                    'Die Freigabestufe für Event "%s (ID: %s)" konnte nicht auf "%s" geändert werden, weil diese Freigabestufe zum Event-Typ ungültig ist.',
                    $objEvent->title,
                    $objEvent->id,
                    null !== $eventReleaseModel ? $eventReleaseModel->title : 'undefined',
                )
            );

            return $objEvent->eventReleaseLevel;
        }

        // No evt.rel.level ID is valid
        // if is no evt.rel.level.package is assigned
        // to the calendar the event belongs to
        if (0 === $targetEventReleaseLevelId) {
            $objEvent->published = 0;

            if ($objEvent->isModified()) {
                $objEvent->tstamp = time();
                $objEvent->save();
            }

            return $targetEventReleaseLevelId;
        }

        $highestEventReleaseModel = EventReleaseLevelPolicyModel::findHighestLevelByEventId($objEvent->id);

        if (null === $highestEventReleaseModel) {
            throw new \RuntimeException(sprintf('Could not determine the highest event release level for the event with ID %d.', $objEvent->id));
        }

        // @todo For some reason this the comparison operator will not work without type casting the id.
        if ((int) $highestEventReleaseModel->id === $targetEventReleaseLevelId) {
            if (!$objEvent->published) {
                $objEvent->published = 1;
                $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['publishedEvent'], $objEvent->id));

                // Dispatch PublishEventEvent
                $event = new PublishEventEvent($this->requestStack->getCurrentRequest(), $objEvent);
                $this->eventDispatcher->dispatch($event);
            }

            if ($objEvent->isModified()) {
                $objEvent->tstamp = time();
                $objEvent->save();
            }
        } else {
            if ($objEvent->published) {
                $objEvent->published = 0;
                $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['unpublishedEvent'], $objEvent->id));
            }

            if ($objEvent->isModified()) {
                $objEvent->tstamp = time();
                $objEvent->save();
            }
        }

        return $targetEventReleaseLevelId;
    }
}
