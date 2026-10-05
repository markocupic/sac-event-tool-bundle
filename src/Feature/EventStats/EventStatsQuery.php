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

namespace Markocupic\SacEventToolBundle\Feature\EventStats;

use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Config\TourguideQualification;

/**
 * Counts events, instructors and registrations per calendar year (event start date).
 *
 * Every method returns the values per year: [2024 => 12, 2025 => 15, ...].
 */
class EventStatsQuery
{
    /**
     * Advertised events: release level FS3 or FS4.
     */
    public const array ADVERTISED_RELEASE_LEVELS = [3, 4];

    /**
     * Published events: release level FS4.
     */
    public const array PUBLISHED_RELEASE_LEVELS = [4];

    /**
     * Tours and courses (no general events, no last minute tours).
     */
    public const array TOURS_AND_COURSES = [EventType::TOUR, EventType::COURSE];

    public const array AGE_GROUPS = ['age_0_20', 'age_21_30', 'age_31_40', 'age_41_60', 'age_61_80', 'age_81_plus', 'age_undefined'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<int>    $years
     * @param array<string> $eventTypes
     *
     * @return array<int, int>
     */
    public function countEvents(array $years, array $eventTypes, int|null $mountainGuide = null, int|null $organizerId = null): array
    {
        $data = [];

        foreach ($years as $year) {
            $qb = $this->createEventQuery($year, self::ADVERTISED_RELEASE_LEVELS, $eventTypes)->select('COUNT(t.id)');

            if (null !== $mountainGuide) {
                $qb->andWhere('t.mountainguide = :mountainGuide')->setParameter('mountainGuide', $mountainGuide);
            }

            if (null !== $organizerId) {
                // tl_calendar_events.organizers is a serialized array of strings
                $qb->andWhere('t.organizers LIKE :organizer')->setParameter('organizer', '%:"'.$organizerId.'";%');
            }

            $data[$year] = (int) $qb->fetchOne();
        }

        return $data;
    }

    /**
     * @param array<int> $years
     *
     * @return array<int, int>
     */
    public function countTours(array $years, string|null $eventState = null, string|null $executionState = null, bool $withTourReport = false): array
    {
        $data = [];

        foreach ($years as $year) {
            $qb = $this->createEventQuery($year, self::ADVERTISED_RELEASE_LEVELS, [EventType::TOUR])->select('COUNT(t.id)');

            if (null !== $eventState) {
                $qb->andWhere('t.eventState = :eventState')->setParameter('eventState', $eventState);
            }

            if (null !== $executionState) {
                $qb->andWhere('t.executionState = :executionState')->setParameter('executionState', $executionState);
            }

            if ($withTourReport) {
                $qb->andWhere("t.executionState != ''");
            }

            $data[$year] = (int) $qb->fetchOne();
        }

        return $data;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getOrganizers(): array
    {
        return $this->connection->fetchAllAssociative('SELECT id, title FROM tl_event_organizer ORDER BY sorting');
    }

    /**
     * Counts the distinct instructors of the advertised tours and courses.
     *
     * @param array<int> $years
     *
     * @return array{all: array<int, int>, mountain_guide: array<int, int>, other: array<int, int>}
     */
    public function countInstructors(array $years): array
    {
        $data = ['all' => [], 'mountain_guide' => [], 'other' => []];

        foreach ($years as $year) {
            $instructors = $this->createEventQuery($year, self::ADVERTISED_RELEASE_LEVELS, self::TOURS_AND_COURSES)
                ->select('DISTINCT u.id, u.leiterQualifikation')
                ->innerJoin('t', 'tl_calendar_events_instructor', 'i', 'i.pid = t.id')
                ->innerJoin('i', 'tl_user', 'u', 'u.id = i.userId')
                ->fetchAllAssociative()
            ;

            $mountainGuides = 0;

            foreach ($instructors as $instructor) {
                $qualifications = array_map('intval', StringUtil::deserialize($instructor['leiterQualifikation'], true));

                if (\in_array(TourguideQualification::MOUNTAIN_GUIDE, $qualifications, true)) {
                    ++$mountainGuides;
                }
            }

            $data['all'][$year] = \count($instructors);
            $data['mountain_guide'][$year] = $mountainGuides;
            $data['other'][$year] = \count($instructors) - $mountainGuides;
        }

        return $data;
    }

    /**
     * Counts the registrations to the advertised tours and courses.
     *
     * @param array<int> $years
     *
     * @return array<int, int>
     */
    public function countRegistrations(array $years, string|null $stateOfSubscription = null): array
    {
        $data = [];

        foreach ($years as $year) {
            $qb = $this->createEventQuery($year, self::ADVERTISED_RELEASE_LEVELS, self::TOURS_AND_COURSES)
                ->select('COUNT(m.id)')
                ->innerJoin('t', 'tl_calendar_events_member', 'm', 'm.eventId = t.id')
            ;

            if (null !== $stateOfSubscription) {
                $qb->andWhere('m.stateOfSubscription = :state')->setParameter('state', $stateOfSubscription);
            }

            $data[$year] = (int) $qb->fetchOne();
        }

        return $data;
    }

    /**
     * Gender and age of the participants (hasParticipated) of the published tours and courses.
     *
     * @param array<int> $years
     *
     * @return array<string, array<int, int>> keys: total, gender_female, gender_male, gender_other and AGE_GROUPS
     */
    public function getParticipantDistribution(array $years): array
    {
        $keys = ['total', 'gender_female', 'gender_male', 'gender_other', ...self::AGE_GROUPS];
        $data = array_fill_keys($keys, array_fill_keys($years, 0));

        foreach ($years as $year) {
            $participants = $this->createEventQuery($year, self::PUBLISHED_RELEASE_LEVELS, self::TOURS_AND_COURSES)
                ->select('m.gender, m.dateOfBirth')
                ->innerJoin('t', 'tl_calendar_events_member', 'm', 'm.eventId = t.id')
                ->andWhere('m.hasParticipated = 1')
                ->fetchAllAssociative()
            ;

            foreach ($participants as $participant) {
                ++$data['total'][$year];

                $genderKey = match ($participant['gender']) {
                    'female' => 'gender_female',
                    'male' => 'gender_male',
                    default => 'gender_other',
                };

                ++$data[$genderKey][$year];

                // dateOfBirth is a timestamp stored as string; '' or 0 means unknown
                $dateOfBirth = (int) $participant['dateOfBirth'];
                $age = 0 !== $dateOfBirth ? $year - (int) date('Y', $dateOfBirth) : null;

                ++$data[self::getAgeGroup($age)][$year];
            }
        }

        return $data;
    }

    /**
     * Age = event year minus year of birth. Null = date of birth unknown.
     */
    public static function getAgeGroup(int|null $age): string
    {
        return match (true) {
            null === $age => 'age_undefined',
            $age >= 81 => 'age_81_plus',
            $age >= 61 => 'age_61_80',
            $age >= 41 => 'age_41_60',
            $age >= 31 => 'age_31_40',
            $age >= 21 => 'age_21_30',
            default => 'age_0_20',
        };
    }

    /**
     * Events of the given types and release levels that start in the given year.
     *
     * @param array<int>    $releaseLevels
     * @param array<string> $eventTypes
     */
    private function createEventQuery(int $year, array $releaseLevels, array $eventTypes): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from('tl_calendar_events', 't')
            ->innerJoin('t', 'tl_event_release_level_policy', 'r', 'r.id = t.eventReleaseLevel')
            ->where('t.startDate BETWEEN :start AND :end')
            ->andWhere('r.level IN (:releaseLevels)')
            ->andWhere('t.eventType IN (:eventTypes)')
            ->setParameter('start', mktime(0, 0, 0, 1, 1, $year))
            ->setParameter('end', mktime(23, 59, 59, 12, 31, $year))
            ->setParameter('releaseLevels', $releaseLevels, ArrayParameterType::INTEGER)
            ->setParameter('eventTypes', $eventTypes, ArrayParameterType::STRING)
        ;
    }
}
