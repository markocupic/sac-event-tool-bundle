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

namespace Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventState;

/**
 * Finds the open post-event tasks of a calendar and assigns them to the recipients.
 *
 * Applies the filters that are common to all tasks:
 * event type selected in the calendar, published, not canceled, completion period expired.
 * Rescheduled events are only checked if a new date has been entered; their end date
 * is shifted accordingly (see ReminderSchedule::getEffectiveEndDate()).
 * All events of the calendar are checked, no matter how long ago they ended.
 * Whether a task applies to an event type and whether it is still open
 * is decided by the task building blocks (via TaskEvaluator).
 *
 * Recipients of an event: all instructors (main and assistant instructors)
 * plus the registration coordinator (tl_calendar_events.registrationGoesTo),
 * only active users with an email address. If the coordinator is also an instructor
 * of the same event, the event is listed only once (role "instructor").
 *
 * Deliberately not "readonly" so it can be mocked in unit tests.
 */
class OpenTaskProvider
{
    /**
     * @var array<int, UserModel|null> cache: userId => valid recipient or null
     */
    private array $recipients = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly TaskEvaluator $taskEvaluator,
    ) {
    }

    /**
     * @return array<int, list<OpenTask>> open tasks of the calendar, keyed by tl_user.id
     */
    public function getOpenTasksByRecipient(CalendarModel $calendar, \DateTimeImmutable $now): array
    {
        $this->framework->initialize();

        // The service lives on in a messenger worker: always use fresh user data
        $this->recipients = [];

        $eventAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $openTasksByRecipient = [];

        foreach ($this->findDueEvents($calendar, $now) as $eventId => $effectiveEndDate) {
            $event = $eventAdapter->findById($eventId);

            if (null === $event) {
                continue;
            }

            $tasks = $this->taskEvaluator->getOpenTasks($event);

            if (empty($tasks)) {
                continue;
            }

            // Contao stores the title input-encoded (e.g. "&amp;"); the templates escape it themselves
            $title = html_entity_decode((string) $event->title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Rescheduled events: the shifted end date is shown in the notification
            $openTask = new OpenTask((int) $event->id, $title, (string) $event->eventType, $effectiveEndDate, OpenTask::ROLE_INSTRUCTOR, $tasks);

            foreach ($this->getRecipientRoles($event) as $userId => $role) {
                $openTasksByRecipient[$userId][] = $openTask->withRole($role);
            }
        }

        return $openTasksByRecipient;
    }

    /**
     * @return list<OpenTask>
     */
    public function getOpenTasks(int $userId, CalendarModel $calendar, \DateTimeImmutable $now): array
    {
        return $this->getOpenTasksByRecipient($calendar, $now)[$userId] ?? [];
    }

    /**
     * @return list<int>
     */
    public function getRecipientIdsWithOpenTasks(CalendarModel $calendar, \DateTimeImmutable $now): array
    {
        return array_keys($this->getOpenTasksByRecipient($calendar, $now));
    }

    /**
     * Returns the user if he is a valid recipient: exists, not disabled, has an email address.
     */
    public function getRecipient(int $userId): UserModel|null
    {
        if (\array_key_exists($userId, $this->recipients)) {
            return $this->recipients[$userId];
        }

        $this->framework->initialize();

        $user = $this->framework->getAdapter(UserModel::class)->findById($userId);

        if (null === $user || $user->disable || '' === trim((string) $user->email)) {
            $user = null;
        }

        return $this->recipients[$userId] = $user;
    }

    /**
     * All published events of the calendar whose completion period has expired,
     * with the end date that counts (shifted for rescheduled events). Ordered by that end date.
     * There is no lookback limit: open tasks are reminded until they are done.
     *
     * The event type is deliberately not filtered here: that is up to the tasks (supports()).
     *
     * @return array<int, int> eventId => effective end date
     */
    protected function findDueEvents(CalendarModel $calendar, \DateTimeImmutable $now): array
    {
        // Event types selected in the calendar (tl_calendar.instructorPostEventTaskReminderEventTypes)
        $eventTypes = array_values(array_filter(StringUtil::deserialize($calendar->instructorPostEventTaskReminderEventTypes, true)));

        if (empty($eventTypes)) {
            return [];
        }

        $dueEndDateMax = ReminderSchedule::getDueEndDateMax((int) $calendar->instructorPostEventTaskReminderFirstOffset, $now);
        $dueEvents = [];

        foreach ($this->fetchCandidateEvents($calendar, $eventTypes, $dueEndDateMax) as $row) {
            $effectiveEndDate = ReminderSchedule::getEffectiveEndDate(
                (int) $row['startDate'],
                (int) $row['endDate'],
                (string) $row['eventState'],
                null !== $row['rescheduledEventDate'] ? (int) $row['rescheduledEventDate'] : null,
            );

            if (null === $effectiveEndDate || $effectiveEndDate > $dueEndDateMax) {
                continue;
            }

            $dueEvents[(int) $row['id']] = $effectiveEndDate;
        }

        // Stable sort: events with the same end date keep their ID order
        asort($dueEvents);

        return $dueEvents;
    }

    /**
     * Candidates for findDueEvents(), read from the database:
     * one of the given event types, published and not canceled; normal events only if their end date is due,
     * rescheduled events only if a new date has been entered (their due date is calculated in PHP).
     *
     * @param list<string> $eventTypes
     *
     * @return list<array{id: int|string, startDate: int|string, endDate: int|string, eventState: string, rescheduledEventDate: int|string|null}>
     */
    protected function fetchCandidateEvents(CalendarModel $calendar, array $eventTypes, int $dueEndDateMax): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('id', 'startDate', 'endDate', 'eventState', 'rescheduledEventDate')
            ->from('tl_calendar_events')
            ->where('pid = :pid')
            ->andWhere('eventType IN (:eventTypes)')
            ->andWhere('published = 1')
            ->andWhere('eventState != :canceled')
            ->andWhere(
                $qb->expr()->or(
                    'eventState != :rescheduled AND endDate > 0 AND endDate <= :dueEndDateMax',
                    'eventState = :rescheduled AND rescheduledEventDate > 0',
                ),
            )
            ->orderBy('id')
            ->setParameter('pid', (int) $calendar->id)
            ->setParameter('eventTypes', $eventTypes, ArrayParameterType::STRING)
            ->setParameter('canceled', EventState::STATE_CANCELED)
            ->setParameter('rescheduled', EventState::STATE_RESCHEDULED)
            ->setParameter('dueEndDateMax', $dueEndDateMax)
        ;

        return $qb->fetchAllAssociative();
    }

    /**
     * Valid recipients of an event: userId => role.
     * Instructors first; the registration coordinator only if he is not an instructor of the event.
     *
     * @return array<int, string>
     */
    protected function getRecipientRoles(CalendarEventsModel $event): array
    {
        $roles = [];

        $instructorIds = $this->connection->fetchFirstColumn(
            'SELECT userId FROM tl_calendar_events_instructor WHERE pid = ? ORDER BY isMainInstructor DESC',
            [(int) $event->id],
        );

        foreach ($instructorIds as $userId) {
            $userId = (int) $userId;

            if (null !== $this->getRecipient($userId)) {
                $roles[$userId] = OpenTask::ROLE_INSTRUCTOR;
            }
        }

        $coordinatorId = (int) $event->registrationGoesTo;

        if ($coordinatorId > 0 && !isset($roles[$coordinatorId]) && null !== $this->getRecipient($coordinatorId)) {
            $roles[$coordinatorId] = OpenTask::ROLE_REGISTRATION_COORDINATOR;
        }

        return $roles;
    }
}
