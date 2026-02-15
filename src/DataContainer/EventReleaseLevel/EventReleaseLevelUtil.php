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
use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Message;
use Contao\Versions;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel\Exception\EventReleaseLevelTransitionException;
use Markocupic\SacEventToolBundle\Event\ChangeEventReleaseLevelEvent;
use Markocupic\SacEventToolBundle\Event\PublishEventEvent;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventReleaseLevelUtil
{
    private Adapter $config;

    private Adapter $date;

    private Adapter $message;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        $this->config = $this->framework->getAdapter(Config::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->date = $this->framework->getAdapter(Date::class);
    }

    public function hasValidEventReleaseLevel(CalendarEventsModel $objEvent, int $eventReleaseLevelId): bool
    {
        $maxEventReleaseModel = EventReleaseLevelPolicyModel::findMaxLevelByEventId($objEvent->id);

        if (0 === $eventReleaseLevelId && null === $maxEventReleaseModel) {
            return true;
        }

        if (0 === $eventReleaseLevelId && null !== $maxEventReleaseModel) {
            return false;
        }

        $eventReleaseModel = EventReleaseLevelPolicyModel::findById($eventReleaseLevelId);

        return $maxEventReleaseModel->pid === $eventReleaseModel->pid;
    }

    public function validateEventReleaseLevelTransition(CalendarEventsModel $objEvent, int $targetEventReleaseLevelId): void
    {
        $calendar = $objEvent->getRelated('pid');

        $err = \sprintf(
            'Could not find the parent calendar for event "%s" (ID: %d).',
            $objEvent->title,
            $objEvent->id,
        );

        if (null === $calendar) {
            throw new \Exception($err);
        }

        $currentEventReleaseModel = EventReleaseLevelPolicyModel::findById($objEvent->eventReleaseLevel);

        if (null === $currentEventReleaseModel) {
            throw new \Exception(\sprintf('Could not find the current event release level for event "%s" (ID %d).', $objEvent->title, $objEvent->id));
        }

        $targetEventReleaseModel = EventReleaseLevelPolicyModel::findById($targetEventReleaseLevelId);

        if (!$this->hasValidEventReleaseLevel($objEvent, $targetEventReleaseLevelId)) {
            $err = [
                'Invalid event release level assigned!',
                'TL_ERROR',
                'ERR.selectedEventReleaseLevelIsNotCompatibleWithTheEventType',
                [
                    $objEvent->title,
                    $objEvent->id,
                    null !== $targetEventReleaseModel ? 'FS '.$targetEventReleaseModel->level : 'undefined',
                ],
            ];

            throw new EventReleaseLevelTransitionException(...$err);
        }

        // Accept 0 if we have no event release level policy package assigned to the event.
        if (0 === $targetEventReleaseLevelId) {
            return;
        }

        // Check if we can determine the initial event release level
        $minEventReleaseModel = EventReleaseLevelPolicyModel::findMinLevelByEventId($objEvent->id);

        if (null === $minEventReleaseModel) {
            $except = \sprintf(
                'Could not determine the initial (lowest) event release level for the event "%s" (ID: %d).',
                $objEvent->title,
                $objEvent->id,
            );

            throw new \RuntimeException($except);
        }

        // Check if we can determine the highest event release level
        $maxEventReleaseModel = EventReleaseLevelPolicyModel::findMaxLevelByEventId($objEvent->id);

        if (null === $maxEventReleaseModel) {
            $except = \sprintf(
                'Could not determine the maximum event release level for the event "%s" (ID: %d).',
                $objEvent->title,
                $objEvent->id,
            );

            throw new \RuntimeException($except);
        }

        // Do not allow non-admins to upgrade the release level above the initial level if a time period is defined in the calendar
        if ($minEventReleaseModel->id !== $targetEventReleaseLevelId && $objEvent->eventReleaseLevel !== $targetEventReleaseLevelId) {
            if ($targetEventReleaseModel->level > $currentEventReleaseModel->level) {
                if ($calendar->enableEventStartDateValidation && ($objEvent->startDate < $calendar->validTimePeriodStart || $objEvent->startDate > $calendar->validTimePeriodStop)) {
                    if (!$this->security->isGranted('ROLE_ADMIN')) {
                        $err = [
                            \sprintf('Can not upgrade release level of event with ID %d. Event start date must be between %s and %s.', $objEvent->id, $this->date->parse($this->config->get('dateFormat'), $calendar->validTimePeriodStart), $this->date->parse($this->config->get('dateFormat'), $calendar->validTimePeriodStop)),
                            'TL_ERROR',
                            'ERR.eventReleaseLevelUpgradeFailedEventStartDateMustBeWithinSpecifiedTimePeriod',
                            [
                                $objEvent->title,
                                $objEvent->id,
                                $targetEventReleaseModel->level,
                                $this->date->parse($this->config->get('dateFormat'), $calendar->validTimePeriodStart),
                                $this->date->parse($this->config->get('dateFormat'), $calendar->validTimePeriodStop),
                            ],
                        ];

                        throw new EventReleaseLevelTransitionException(...$err);
                    }
                    // Show a warning to admins only!
                    $this->message->addInfo(\sprintf('Event "%s" (ID %d) should not be promoted to FS %d because its start date falls outside the configured time period.', $objEvent->title, $objEvent->id, $targetEventReleaseModel->level));
                }
            }
        }

        // Do not allow non-admins to shift the event release level to the top level.
        if ($maxEventReleaseModel->id === $targetEventReleaseLevelId) {
            if (
                !$this->security->isGranted('ROLE_ADMIN')
                && $calendar->enableMaxEventReleaseLevelProtection
                && time() < $calendar->maxEventReleaseLevelTimeLimit
            ) {
                $objEvent->published = 0;

                if ($objEvent->isModified()) {
                    $objEvent->save();
                }

                $err = [
                    'Event release level transition not allowed before '.$this->date->parse($this->config->get('datimFormat'), $calendar->maxEventReleaseLevelTimeLimit),
                    'TL_ERROR',
                    'ERR.pushingEventReleaseLevelNotAllowedBeforeDate',
                    [
                        $objEvent->title,
                        $objEvent->id,
                        $this->date->parse($this->config->get('datimFormat'), $calendar->maxEventReleaseLevelTimeLimit),
                        $targetEventReleaseModel->level,
                    ],
                ];

                throw new EventReleaseLevelTransitionException(...$err);
            }
        }
    }

    /**
     * Important! Do not use this method without validating the event release level transition first!
     */
    public function shiftEventReleaseLevel(CalendarEventsModel $objEvent, EventReleaseLevelPolicyModel $targetEventReleaseLevelModel, string $direction = 'up'): void
    {
        if ('up' !== $direction && 'down' !== $direction) {
            throw new \InvalidArgumentException('Invalid direction given! Must be "up" or "down".');
        }

        $maxEventReleaseLevelModel = EventReleaseLevelPolicyModel::findMaxLevelByEventId($objEvent->id);
        $currentEventReleaseLevelModel = EventReleaseLevelPolicyModel::findById($objEvent->eventReleaseLevel);
        $objEvent->eventReleaseLevel = $targetEventReleaseLevelModel->id;

        $isPublished = $objEvent->published;

        if ($objEvent->isModified()) {
            // Dispatch the ChangeEventReleaseLevelEvent event
            $event = new ChangeEventReleaseLevelEvent($this->requestStack->getCurrentRequest(), $objEvent, $direction);
            $this->eventDispatcher->dispatch($event);

            // System log
            $this->contaoGeneralLogger?->info(
                \sprintf(
                    'Event release level for event with ID %d ["%s"] has been %s from "%s" to "%s".',
                    $objEvent->id,
                    $objEvent->title,
                    'up' === $direction ? 'upgraded' : 'downgraded',
                    $currentEventReleaseLevelModel->title,
                    $targetEventReleaseLevelModel->title,
                ),
            );
        }

        if ($maxEventReleaseLevelModel->id === $targetEventReleaseLevelModel->id) {
            $objEvent->published = 1;
        } else {
            $objEvent->published = 0;
        }

        if (!$isPublished && $objEvent->published) {
            $msg = $this->translator->trans('MSC.publishedEvent', [$objEvent->id], 'contao_default');
            $this->message->addInfo($msg);

            // Dispatch PublishEventEvent
            $event = new PublishEventEvent($this->requestStack->getCurrentRequest(), $objEvent);
            $this->eventDispatcher->dispatch($event);
        }

        if ($isPublished && !$objEvent->published) {
            $msg = $this->translator->trans('MSC.unpublishedEvent', [$objEvent->id], 'contao_default');
            $this->message->addInfo($msg);
        }

        // Create a new version
        if ($objEvent->isModified()) {
            $objEvent->tstamp = time();
            $objEvent->save();
            $objVersions = new Versions('tl_calendar_events', $objEvent->id);
            $objVersions->initialize();
            $objVersions->create();
        }
    }
}
