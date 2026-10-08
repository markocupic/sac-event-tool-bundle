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

namespace Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventType;

/**
 * Finds the events a SAC member has participated in during the last HISTORY_YEARS years.
 *
 * Counted are registrations with the same SAC member ID and hasParticipated = 1 of
 * published, not canceled events of all event types whose start date lies between the
 * history start and now.
 */
class ParticipantEventHistoryQuery
{
    /**
     * Number of years shown in the history (also used in the hint of the registration form).
     */
    public const int HISTORY_YEARS = 5;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Start of the history: today 00:00, same calendar date HISTORY_YEARS years ago
     * (08.10.2026 → 08.10.2021; 29.02. → 01.03.).
     */
    public static function getHistoryStart(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->setTime(0, 0)->modify(\sprintf('-%d years', self::HISTORY_YEARS));
    }

    /**
     * IDs of the events (newest first). Several registrations to the same event count once.
     *
     * @return list<int>
     */
    public function findEventIds(int $sacMemberId, \DateTimeImmutable $now): array
    {
        if ($sacMemberId < 1) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('DISTINCT e.id', 'e.startDate')
            ->from('tl_calendar_events', 'e')
            ->innerJoin('e', 'tl_calendar_events_member', 'm', 'm.eventId = e.id')
            ->where('m.sacMemberId = :sacMemberId')
            ->andWhere('m.hasParticipated = 1')
            ->andWhere('e.published = 1')
            ->andWhere('e.eventState != :canceled')
            ->andWhere('e.eventType IN (:eventTypes)')
            ->andWhere('e.startDate >= :historyStart')
            ->andWhere('e.startDate <= :now')
            ->orderBy('e.startDate', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setParameter('sacMemberId', $sacMemberId, ParameterType::INTEGER)
            ->setParameter('canceled', EventState::STATE_CANCELED)
            ->setParameter('eventTypes', EventType::ALL, ArrayParameterType::STRING)
            ->setParameter('historyStart', self::getHistoryStart($now)->getTimestamp(), ParameterType::INTEGER)
            ->setParameter('now', $now->getTimestamp(), ParameterType::INTEGER)
        ;

        return array_map('intval', array_column($qb->fetchAllAssociative(), 'id'));
    }
}
