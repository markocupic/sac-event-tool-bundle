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
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Security\Voter\EventReleaseLevelTransition;
use Markocupic\SacEventToolBundle\Security\Voter\EventReleaseLevelTransitionVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventReleaseLevelUtil
{
    private Adapter $config;

    private Adapter $date;

    private Adapter $message;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventReleaseLevelChangeNotifier $eventReleaseLevelChangeNotifier,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly EventReleaseLevelTimeRules $timeRules,
        private readonly TranslatorInterface $translator,
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
            $dateFormat = $this->config->get('dateFormat');

            $this->message->addInfo($this->translator->trans('MSC.eventReleaseLevelStartDateOutsideValidTimePeriod', [$event->title, $event->id, $targetLevel->level, $this->date->parse($dateFormat, $calendar->validTimePeriodStart), $this->date->parse($dateFormat, $calendar->validTimePeriodStop)], 'contao_default'));
        }
    }

    /**
     * Moves the event to the target level, publishes it on the highest level and
     * unpublishes it on the other levels. Saves the event and notifies the change
     * immediately (see EventReleaseLevelChangeNotifier).
     *
     * Not to be used in the save callback of the edit form: The form may still be
     * invalid and the change must not be saved, see CalendarEvents::saveCallbackEventReleaseLevel().
     *
     * Important! Do not use this method without validating the event release level transition first!
     */
    public function shiftEventReleaseLevel(CalendarEventsModel $event, EventReleaseLevelPolicyModel $targetLevel): void
    {
        $maxLevel = EventReleaseLevelPolicyModel::findMaxLevelByEventId($event->id);
        $previousLevelId = (int) $event->eventReleaseLevel;
        $wasPublished = (bool) $event->published;

        $event->eventReleaseLevel = $targetLevel->id;

        // Only events on the top level are published
        $event->published = null !== $maxLevel && (int) $maxLevel->id === (int) $targetLevel->id ? 1 : 0;

        if (!$event->isModified()) {
            return;
        }

        // Create a new version
        $event->tstamp = time();
        $event->save();

        $versions = new Versions('tl_calendar_events', $event->id);
        $versions->initialize();
        $versions->create();

        $this->eventReleaseLevelChangeNotifier->notify($this->requestStack->getCurrentRequest(), $event, $previousLevelId, $wasPublished);
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
