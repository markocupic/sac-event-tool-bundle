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
use Markocupic\SacEventToolBundle\Security\Voter\EventReleaseLevelTransition;
use Markocupic\SacEventToolBundle\Security\Voter\EventReleaseLevelTransitionVoter;
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
        private readonly EventReleaseLevelTimeRules $timeRules,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        $this->config = $this->framework->getAdapter(Config::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->date = $this->framework->getAdapter(Date::class);
    }

    /**
     * The release level must belong to the release level policy package of the
     * event. 0 is only valid if no package is assigned to the event.
     */
    public function hasValidEventReleaseLevel(CalendarEventsModel $event, int $eventReleaseLevelId): bool
    {
        $maxLevel = EventReleaseLevelPolicyModel::findMaxLevelByEventId($event->id);

        if (0 === $eventReleaseLevelId) {
            return null === $maxLevel;
        }

        $level = EventReleaseLevelPolicyModel::findById($eventReleaseLevelId);

        return null !== $maxLevel && null !== $level && $maxLevel->pid === $level->pid;
    }

    public function validateEventReleaseLevelTransition(CalendarEventsModel $event, int $targetEventReleaseLevelId): void
    {
        $calendar = $event->getRelated('pid');

        if (null === $calendar) {
            throw new \Exception(\sprintf('Could not find the parent calendar for event "%s" (ID: %d).', $event->title, $event->id));
        }

        $currentLevel = EventReleaseLevelPolicyModel::findById($event->eventReleaseLevel);

        if (null === $currentLevel) {
            throw new \Exception(\sprintf('Could not find the current event release level for event "%s" (ID %d).', $event->title, $event->id));
        }

        $targetLevel = EventReleaseLevelPolicyModel::findById($targetEventReleaseLevelId);

        if (!$this->hasValidEventReleaseLevel($event, $targetEventReleaseLevelId)) {
            throw new EventReleaseLevelTransitionException('Invalid event release level assigned!', EventReleaseLevelTransitionException::LEVEL_ERROR, 'ERR.selectedEventReleaseLevelIsNotCompatibleWithTheEventType', [$event->title, $event->id, null !== $targetLevel ? 'FS '.$targetLevel->level : 'undefined']);
        }

        // Accept 0 if we have no event release level policy package assigned to the event.
        if (0 === $targetEventReleaseLevelId) {
            return;
        }

        if (null === EventReleaseLevelPolicyModel::findMinLevelByEventId($event->id)) {
            throw new \RuntimeException(\sprintf('Could not determine the initial (lowest) event release level for the event "%s" (ID: %d).', $event->title, $event->id));
        }

        if (null === EventReleaseLevelPolicyModel::findMaxLevelByEventId($event->id)) {
            throw new \RuntimeException(\sprintf('Could not determine the maximum event release level for the event "%s" (ID: %d).', $event->title, $event->id));
        }

        $violation = $this->timeRules->getViolation($event, $targetLevel);

        if (!$this->security->isGranted(EventReleaseLevelTransitionVoter::CAN_SWITCH_TO_EVENT_RELEASE_LEVEL, new EventReleaseLevelTransition($event, $targetLevel))) {
            throw $this->createTransitionDeniedException($event, $targetLevel, $calendar, $violation);
        }

        // Admins are not bound to the time rules, but get a warning.
        if (EventReleaseLevelTimeRuleViolation::StartDateOutsideValidTimePeriod === $violation) {
            $this->message->addInfo(\sprintf('Event "%s" (ID %d) should not be promoted to FS %d because its start date falls outside the configured time period.', $event->title, $event->id, $targetLevel->level));
        }
    }

    /**
     * Important! Do not use this method without validating the event release level transition first!
     */
    public function shiftEventReleaseLevel(CalendarEventsModel $event, EventReleaseLevelPolicyModel $targetLevel, string $direction = 'up'): void
    {
        if ('up' !== $direction && 'down' !== $direction) {
            throw new \InvalidArgumentException('Invalid direction given! Must be "up" or "down".');
        }

        $maxLevel = EventReleaseLevelPolicyModel::findMaxLevelByEventId($event->id);
        $currentLevel = EventReleaseLevelPolicyModel::findById($event->eventReleaseLevel);
        $event->eventReleaseLevel = $targetLevel->id;

        $wasPublished = $event->published;

        if ($event->isModified()) {
            $this->eventDispatcher->dispatch(new ChangeEventReleaseLevelEvent($this->requestStack->getCurrentRequest(), $event, $direction));

            $this->contaoGeneralLogger?->info(
                \sprintf(
                    'Event release level for event with ID %d ["%s"] has been %s from "%s" to "%s".',
                    $event->id,
                    $event->title,
                    'up' === $direction ? 'upgraded' : 'downgraded',
                    $currentLevel?->title,
                    $targetLevel->title,
                ),
            );
        }

        // Only events on the top level are published
        $event->published = $maxLevel?->id === $targetLevel->id ? 1 : 0;

        if (!$wasPublished && $event->published) {
            $this->message->addInfo($this->translator->trans('MSC.publishedEvent', [$event->id], 'contao_default'));
            $this->eventDispatcher->dispatch(new PublishEventEvent($this->requestStack->getCurrentRequest(), $event));
        }

        if ($wasPublished && !$event->published) {
            $this->message->addInfo($this->translator->trans('MSC.unpublishedEvent', [$event->id], 'contao_default'));
        }

        // Create a new version
        if ($event->isModified()) {
            $event->tstamp = time();
            $event->save();

            $versions = new Versions('tl_calendar_events', $event->id);
            $versions->initialize();
            $versions->create();
        }
    }

    /**
     * The message tells the user why the transition has been denied.
     */
    private function createTransitionDeniedException(CalendarEventsModel $event, EventReleaseLevelPolicyModel $targetLevel, object $calendar, EventReleaseLevelTimeRuleViolation|null $violation): EventReleaseLevelTransitionException
    {
        $dateFormat = $this->config->get('dateFormat');
        $datimFormat = $this->config->get('datimFormat');

        return match ($violation) {
            EventReleaseLevelTimeRuleViolation::StartDateOutsideValidTimePeriod => new EventReleaseLevelTransitionException(
                \sprintf('Can not upgrade release level of event with ID %d. Event start date must be between %s and %s.', $event->id, $this->date->parse($dateFormat, $calendar->validTimePeriodStart), $this->date->parse($dateFormat, $calendar->validTimePeriodStop)),
                EventReleaseLevelTransitionException::LEVEL_ERROR,
                'ERR.eventReleaseLevelUpgradeFailedEventStartDateMustBeWithinSpecifiedTimePeriod',
                [$event->title, $event->id, $targetLevel->level, $this->date->parse($dateFormat, $calendar->validTimePeriodStart), $this->date->parse($dateFormat, $calendar->validTimePeriodStop)],
            ),
            EventReleaseLevelTimeRuleViolation::MaxLevelLocked => new EventReleaseLevelTransitionException(
                'Event release level transition not allowed before '.$this->date->parse($datimFormat, $calendar->maxEventReleaseLevelTimeLimit),
                EventReleaseLevelTransitionException::LEVEL_ERROR,
                'ERR.pushingEventReleaseLevelNotAllowedBeforeDate',
                [$event->title, $event->id, $this->date->parse($datimFormat, $calendar->maxEventReleaseLevelTimeLimit), $targetLevel->level],
            ),
            null => new EventReleaseLevelTransitionException(
                \sprintf('Missing permissions to change the release level of event with ID %d to FS %d.', $event->id, $targetLevel->level),
                EventReleaseLevelTransitionException::LEVEL_ERROR,
                'ERR.missingPermissionsToChangeEventReleaseLevel',
                [$event->title, $event->id, $targetLevel->level],
            ),
        };
    }
}
