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

namespace Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Event\ChangeEventReleaseLevelEvent;
use Markocupic\SacEventToolBundle\Event\PublishEventEvent;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Notifies a change of the release level of an event: back end messages
 * (published/unpublished), the events ChangeEventReleaseLevelEvent and
 * PublishEventEvent (e-mails to the recipients of the calendar) and a log entry.
 *
 * Why notifications of the edit form are deferred:
 * Contao only saves the edit form if all fields are valid. In the modes editAll
 * and overrideAll, all records are saved in one database transaction, which is
 * rolled back if one of the records is invalid. Messages and e-mails cannot be
 * rolled back. Therefore, changes made in the edit form are only notified at the
 * end of the request (kernel.response) and only if the new release level has
 * actually been persisted.
 */
class EventReleaseLevelChangeNotifier
{
    private Adapter $calendarEventsModel;

    private Adapter $eventReleaseLevelPolicyModel;

    private Adapter $message;

    /**
     * @var array<int, array{previousLevelId: int, levelId: int, wasPublished: bool}>
     */
    private array $deferredChanges = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->eventReleaseLevelPolicyModel = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
    }

    /**
     * Notifies the change at the end of the request, if the event is still on the
     * release level $levelId then.
     */
    public function deferNotification(int $eventId, int $previousLevelId, int $levelId, bool $wasPublished): void
    {
        $this->deferredChanges[$eventId] = [
            'previousLevelId' => $previousLevelId,
            'levelId' => $levelId,
            'wasPublished' => $wasPublished,
        ];
    }

    /**
     * Notifies the change immediately. The new release level and the published state
     * must already be set on the event.
     */
    public function notify(Request $request, CalendarEventsModel $event, int $previousLevelId, bool $wasPublished): void
    {
        $levelId = (int) $event->eventReleaseLevel;

        if ($levelId !== $previousLevelId) {
            $previousLevel = $this->eventReleaseLevelPolicyModel->findById($previousLevelId);
            $level = $this->eventReleaseLevelPolicyModel->findById($levelId);
            $isUpgrade = null === $previousLevel || null === $level || (int) $level->level > (int) $previousLevel->level;

            $this->eventDispatcher->dispatch(new ChangeEventReleaseLevelEvent($request, $event, $isUpgrade ? 'up' : 'down'));

            $this->contaoGeneralLogger?->info(
                \sprintf(
                    'Event release level for event with ID %d ["%s"] has been %s from "%s" to "%s".',
                    $event->id,
                    $event->title,
                    $isUpgrade ? 'upgraded' : 'downgraded',
                    $previousLevel?->title,
                    $level?->title,
                ),
            );
        }

        $isPublished = (bool) $event->published;

        if (!$wasPublished && $isPublished) {
            $this->message->addInfo($this->translator->trans('MSC.publishedEvent', [$event->id], 'contao_default'));
            $this->eventDispatcher->dispatch(new PublishEventEvent($request, $event));
        }

        if ($wasPublished && !$isPublished) {
            $this->message->addInfo($this->translator->trans('MSC.unpublishedEvent', [$event->id], 'contao_default'));
        }
    }

    /**
     * Runs before the session is saved (priority -1000), so the back end messages
     * are shown on the next page.
     */
    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function notifyDeferredChanges(ResponseEvent $responseEvent): void
    {
        if (!$responseEvent->isMainRequest() || [] === $this->deferredChanges) {
            return;
        }

        $deferredChanges = $this->deferredChanges;
        $this->deferredChanges = [];

        foreach ($deferredChanges as $eventId => $change) {
            $levelId = $this->connection->fetchOne('SELECT eventReleaseLevel FROM tl_calendar_events WHERE id = ?', [$eventId], [Types::INTEGER]);

            // The change has not been persisted (e.g. the transaction has been rolled back)
            if (false === $levelId || (int) $levelId !== $change['levelId']) {
                continue;
            }

            $event = $this->calendarEventsModel->findById($eventId);

            if (null === $event) {
                continue;
            }

            // The model may still contain the values from before the form was saved
            $event->refresh();

            $this->notify($responseEvent->getRequest(), $event, $change['previousLevelId'], $change['wasPublished']);
        }
    }
}
