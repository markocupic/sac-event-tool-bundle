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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationCleanup;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\EventRegistrationAnonymizer;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\EventRegistrationRemover;

/**
 * Cleans up tl_calendar_events_member:
 * 1. deletes all registrations whose event no longer exists (tl_calendar_events), including their versions (tl_version)
 * 2. anonymizes registrations of members that no longer exist (tl_member).
 *
 * See docs/features/event-registration-cleanup.md
 */
class EventRegistrationCleanup
{
    private const string COLUMNS = 'r.id, r.eventId, r.eventName, r.firstname, r.lastname, r.sacMemberId, r.contaoMemberId';

    public function __construct(
        private readonly Connection $connection,
        private readonly EventRegistrationAnonymizer $eventRegistrationAnonymizer,
        private readonly EventRegistrationRemover $eventRegistrationRemover,
    ) {
    }

    /**
     * @return array{anonymized: list<array<string, mixed>>, deleted: list<array<string, mixed>>}
     */
    public function run(bool $dryRun = false): array
    {
        $result = [
            'deleted' => $this->findRegistrationsOfDeletedEvents(),
            'anonymized' => $this->findRegistrationsOfDeletedMembers(),
        ];

        if ($dryRun) {
            return $result;
        }

        $this->eventRegistrationRemover->remove(array_map(static fn (array $registration): int => (int) $registration['id'], $result['deleted']));

        foreach ($result['anonymized'] as $registration) {
            $this->eventRegistrationAnonymizer->anonymize((int) $registration['id']);
        }

        return $result;
    }

    /**
     * Registrations of members that no longer exist:
     * - the registration belongs to a member (Contao member ID or SAC member ID set, guests are not included)
     * - no member with the Contao member ID
     * - no member with the SAC member ID (if set).
     *
     * Disabled members (tl_member.disable = 1) still exist.
     * Registrations of deleted events are not included, they are deleted anyway.
     *
     * @return list<array<string, mixed>>
     */
    public function findRegistrationsOfDeletedMembers(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS."
            FROM tl_calendar_events_member AS r
            WHERE r.anonymized = 0
                AND r.tstamp > 0
                AND (r.contaoMemberId > 0 OR (r.sacMemberId <> '' AND r.sacMemberId <> '0'))
                AND NOT EXISTS (SELECT 1 FROM tl_member AS m WHERE m.id = r.contaoMemberId)
                AND NOT (r.sacMemberId <> '' AND r.sacMemberId <> '0' AND EXISTS (SELECT 1 FROM tl_member AS m WHERE m.sacMemberId = r.sacMemberId))
                AND EXISTS (SELECT 1 FROM tl_calendar_events AS e WHERE e.id = r.eventId)
            ORDER BY r.id",
        );
    }

    /**
     * All registrations whose event no longer exists (with or without SAC member ID).
     *
     * @return list<array<string, mixed>>
     */
    public function findRegistrationsOfDeletedEvents(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.'
            FROM tl_calendar_events_member AS r
            WHERE r.tstamp > 0
                AND NOT EXISTS (SELECT 1 FROM tl_calendar_events AS e WHERE e.id = r.eventId)
            ORDER BY r.id',
        );
    }
}
