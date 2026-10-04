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

namespace Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Deletes event registrations (tl_calendar_events_member) together with their versions (tl_version),
 * because the versions contain the personal data. The log entry contains only the IDs, no personal data.
 *
 * Used for registrations of events that no longer exist.
 *
 * See docs/features/member-profile-deletion.md
 */
class EventRegistrationRemover
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * @param list<int> $registrationIds
     */
    public function remove(array $registrationIds): void
    {
        if ([] === $registrationIds) {
            return;
        }

        $this->connection->transactional(
            function () use ($registrationIds): void {
                $this->connection->executeStatement(
                    'DELETE FROM tl_calendar_events_member WHERE id IN (?)',
                    [$registrationIds],
                    [ArrayParameterType::INTEGER],
                );

                $this->connection->executeStatement(
                    "DELETE FROM tl_version WHERE fromTable = 'tl_calendar_events_member' AND pid IN (?)",
                    [$registrationIds],
                    [ArrayParameterType::INTEGER],
                );
            },
        );

        $this->contaoGeneralLogger?->info(\sprintf(
            'Deleted %d event registration(s) of deleted events and their versions: IDs %s.',
            \count($registrationIds),
            implode(', ', $registrationIds),
        ));
    }
}
