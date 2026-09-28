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

namespace Markocupic\SacEventToolBundle\EventReminder;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;

/**
 * Loads the persons involved in an event (instructors, registration coordinator, participants)
 * from Contao and converts them to framework-independent objects.
 *
 * Deliberately not "readonly" so it can be mocked in unit tests.
 */
class PersonProvider
{
    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
    ) {
    }

    /**
     * All active instructors with an email address, main instructor first.
     *
     * @return list<Person>
     */
    public function getInstructors(CalendarEventsModel $event): array
    {
        $userAdapter = $this->framework->getAdapter(UserModel::class);
        $instructors = [];

        foreach ($this->calendarEventsUtil->getInstructorsAsArray($event) as $userId) {
            $user = $userAdapter->findById($userId);

            if (null !== $user && !empty($user->email)) {
                $instructors[] = Person::fromUserModel($user);
            }
        }

        return $instructors;
    }

    /**
     * The instructor flagged as main instructor in tl_calendar_events_instructor, if any.
     */
    public function getFlaggedMainInstructor(CalendarEventsModel $event): Person|null
    {
        $user = $this->calendarEventsUtil->getMainInstructor($event);

        return null !== $user ? Person::fromUserModel($user) : null;
    }

    /**
     * The registration coordinator (tl_calendar_events.registrationGoesTo), if set and active.
     */
    public function getRegistrationCoordinator(CalendarEventsModel $event): Person|null
    {
        if ($event->registrationGoesTo < 1) {
            return null;
        }

        $user = $this->framework->getAdapter(UserModel::class)->findById($event->registrationGoesTo);

        if (null === $user || $user->disable) {
            return null;
        }

        return Person::fromUserModel($user);
    }

    /**
     * All accepted registrations with an email address, sorted by name.
     *
     * @return list<Participant>
     */
    public function getParticipants(CalendarEventsModel $event): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('firstname', 'lastname', 'email')
            ->from('tl_calendar_events_member')
            ->where('eventId = :eventId')
            ->andWhere('stateOfSubscription = :state')
            ->andWhere('email != :emptyEmail')
            ->orderBy('lastname')
            ->addOrderBy('firstname')
            ->setParameter('eventId', $event->id)
            ->setParameter('state', EventSubscriptionState::SUBSCRIPTION_ACCEPTED)
            ->setParameter('emptyEmail', '')
        ;

        return array_map(
            static fn (array $row): Participant => new Participant((string) $row['firstname'], (string) $row['lastname'], (string) $row['email']),
            $qb->fetchAllAssociative(),
        );
    }
}
