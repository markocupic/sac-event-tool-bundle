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

    /**
     * @throws \Exception
     */
    public function handleEventReleaseLevelAndPublishUnpublish(int $eventId, int $targetEventReleaseLevelId): int
    {
        $hasError = false;

        $objEvent = $this->calendarEventsModel->findByPk($eventId);

        if (null === $objEvent) {
            throw new \Exception('Event not found.');
        }

        $highestEventReleaseModel = EventReleaseLevelPolicyModel::findHighestLevelByEventId($objEvent->id);

        if (null !== $highestEventReleaseModel) {
            // Display a message in the backend if the event has been published or unpublished.
            // @todo For some reason this the comparison operator will not work without type casting the id.
            if ((int) $highestEventReleaseModel->id === $targetEventReleaseLevelId) {
                if (!$objEvent->published) {
                    $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['publishedEvent'], $objEvent->id));
                }

                $objEvent->published = true;
                $objEvent->save();
                $request = $this->requestStack->getCurrentRequest();

                // Dispatch PublishEventEvent

                $event = new PublishEventEvent($request, $objEvent);
                $this->eventDispatcher->dispatch($event);
            } else {
                $eventReleaseModel = EventReleaseLevelPolicyModel::findByPk($targetEventReleaseLevelId);
                $lowestEventReleaseModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($objEvent->id);

                if (null !== $eventReleaseModel) {
                    if ((int) $eventReleaseModel->pid !== (int) $lowestEventReleaseModel->pid) {
                        $hasError = true;

                        if ($objEvent->eventReleaseLevel > 0) {
                            $targetEventReleaseLevelId = $objEvent->eventReleaseLevel;
                            $this->message->addError(sprintf('Die Freigabestufe für Event "%s (ID: %s)" konnte nicht auf "%s" geändert werden, weil diese Freigabestufe zum Event-Typ ungültig ist. ', $objEvent->title, $objEvent->id, $eventReleaseModel->title));
                        } else {
                            $targetEventReleaseLevelId = $lowestEventReleaseModel->id;
                            $this->message->addError(sprintf('Die Freigabestufe für Event "%s (ID: %s)" musste auf "%s" korrigiert werden, weil eine zum Event-Typ ungültige Freigabestufe gewählt wurde. ', $objEvent->title, $objEvent->id, $lowestEventReleaseModel->title));
                        }
                    }
                }

                if ($objEvent->published) {
                    $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['unpublishedEvent'], $objEvent->id));
                }

                $objEvent->published = false;
                $objEvent->save();
            }

            if (!$hasError) {
                // Display a message in the backend.
                $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'], $objEvent->id, EventReleaseLevelPolicyModel::findByPk($targetEventReleaseLevelId)->level));
            }
        }

        return $targetEventReleaseLevelId;
    }
}
