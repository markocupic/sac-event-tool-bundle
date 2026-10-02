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
use Contao\UserModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventState;

/**
 * Finds the open post-event tasks of a calendar and assigns them to the recipients.
 *
 * Applies the filters that are common to all tasks:
 * published, not canceled/rescheduled, valid endDate, completion period expired, within lookback.
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

        foreach ($this->findDueEventIds($calendar, $now) as $eventId) {
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

            $openTask = new OpenTask((int) $event->id, $title, (string) $event->eventType, (int) $event->endDate, OpenTask::ROLE_INSTRUCTOR, $tasks);

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
     * IDs of the published events of the calendar that ended within the lookback period
     * and whose completion period has expired. Ordered by endDate.
     *
     * The event type is deliberately not filtered here: that is up to the tasks (supports()).
     *
     * @return list<int>
     */
    protected function findDueEventIds(CalendarModel $calendar, \DateTimeImmutable $now): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('id')
            ->from('tl_calendar_events')
            ->where('pid = :pid')
            ->andWhere('published = 1')
            ->andWhere('eventState NOT IN (:excludedStates)')
            ->andWhere('endDate > 0')
            ->andWhere('endDate <= :dueEndDateMax')
            ->andWhere('endDate >= :lookbackEndDateMin')
            ->orderBy('endDate')
            ->addOrderBy('id')
            ->setParameter('pid', (int) $calendar->id)
            ->setParameter('excludedStates', [EventState::STATE_CANCELED, EventState::STATE_RESCHEDULED], ArrayParameterType::STRING)
            ->setParameter('dueEndDateMax', ReminderSchedule::getDueEndDateMax((int) $calendar->instructorPostEventTaskReminderFirstOffset, $now))
            ->setParameter('lookbackEndDateMin', ReminderSchedule::getLookbackEndDateMin((int) $calendar->instructorPostEventTaskReminderLookback, $now))
        ;

        return array_map('intval', $qb->fetchFirstColumn());
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
