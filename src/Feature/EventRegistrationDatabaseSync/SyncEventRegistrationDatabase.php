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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationDatabaseSync;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * Syncs event registration data by updating the "tl_calendar_events_member" table
 * with the most recent contact information and details from the "tl_member" table.
 *
 * A service, not a controller: used by the cron, the maintenance module, the command
 * (Command\EventRegistrationDatabaseSyncCommand) and, for a single member, by the
 * event registration and the member profile.
 *
 * The registrations are read together with the member data in one query and streamed
 * row by row. A registration is only updated if at least one field differs.
 *
 * See docs/features/event-registration-database-sync.md
 */
class SyncEventRegistrationDatabase
{
    private const string STOP_WATCH_EVENT = 'update_event_reg_data';

    // Always copied from tl_member
    private const array FIELDS_ALWAYS = ['gender', 'firstname', 'lastname', 'street', 'postal', 'city', 'dateOfBirth', 'phone'];

    // Copied from tl_member only if not empty there
    private const array FIELDS_IF_NOT_EMPTY = ['email', 'mobile'];

    // Copied from tl_member only for upcoming events and only if not empty there
    private const array FIELDS_UPCOMING_EVENTS_ONLY = ['emergencyPhone', 'emergencyPhoneName', 'foodHabits'];

    // Copied from tl_member only if the member has a SAC member ID (> 0).
    // Corrects manually entered values like "00167400" or "370883 SAC Pilatus".
    private const string FIELD_SAC_MEMBER_ID = 'sacMemberId';

    private const array EMPTY_SYNC_LOG = [
        'processed_registrations' => 0,
        'processed_members' => 0,
        'updates' => 0,
        'log' => [],
        'duration' => 0,
        'with_error' => false,
        'exceptions' => [],
    ];

    private array $syncLog = self::EMPTY_SYNC_LOG;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * Syncs the registrations of all members (tl_member) and returns the sync log.
     *
     * @return array{processed_registrations: int, processed_members: int, updates: int, log: array, duration: float|int, with_error: bool, exceptions: array}
     */
    public function run(): array
    {
        $this->framework->initialize();

        // The service is shared: start every run with an empty log
        $this->syncLog = self::EMPTY_SYNC_LOG;

        $stopWatchEvent = $this->stopWatchStart();

        $this->sync(null);

        $duration = round($stopWatchEvent->stop()->getDuration() / 1000);
        $this->syncLog['duration'] = $duration;

        $this->contaoGeneralLogger?->info(\sprintf(
            'Successful update of the member data in the event registration table "tl_calendar_events_member": processed: %d, updates: %d, errors: %d, duration: %s.',
            $this->syncLog['processed_registrations'],
            $this->syncLog['updates'],
            \count($this->syncLog['exceptions']),
            $duration.' s',
        ));

        return $this->syncLog;
    }

    public function getSyncLog(): array
    {
        return $this->syncLog;
    }

    /**
     * Syncs the registrations of a single member.
     *
     * @return int number of updated registrations
     */
    public function syncMember(int $memberId): int
    {
        return $this->sync($memberId);
    }

    /**
     * @return list<int>
     */
    public function getUpcomingEventsIds(): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_calendar_events WHERE startDate > ? ORDER BY id',
            [time()],
            [Types::INTEGER],
        );

        return array_map(intval(...), $ids);
    }

    /**
     * Returns only the fields of the registration that differ from the member data.
     * An empty array means: nothing to update.
     *
     * @param array<string, mixed> $row registration (fields without prefix) and member data (fields with prefix "member_")
     *
     * @return array<string, string>
     */
    public function getChangedFields(array $row, bool $isUpcomingEvent): array
    {
        $target = [];

        foreach (self::FIELDS_ALWAYS as $field) {
            $target[$field] = (string) $row['member_'.$field];
        }

        if ((int) $row['member_'.self::FIELD_SAC_MEMBER_ID] > 0) {
            $target[self::FIELD_SAC_MEMBER_ID] = (string) (int) $row['member_'.self::FIELD_SAC_MEMBER_ID];
        }

        // Do not override these contact data fields with empty values
        foreach (self::FIELDS_IF_NOT_EMPTY as $field) {
            if ('' !== (string) $row['member_'.$field]) {
                $target[$field] = (string) $row['member_'.$field];
            }
        }

        // Emergency contact and food habits only for upcoming events and only if not empty
        if ($isUpcomingEvent) {
            if ('' !== trim((string) $row['member_emergencyPhone']) && '' !== trim((string) $row['member_emergencyPhoneName'])) {
                $target['emergencyPhone'] = (string) $row['member_emergencyPhone'];
                $target['emergencyPhoneName'] = (string) $row['member_emergencyPhoneName'];
            }

            if ('' !== trim((string) $row['member_foodHabits'])) {
                $target['foodHabits'] = (string) $row['member_foodHabits'];
            }
        }

        return array_filter(
            $target,
            static fn (string $value, string $field): bool => (string) $row[$field] !== $value,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function stopWatchStart(): StopwatchEvent
    {
        return (new Stopwatch())->start(self::STOP_WATCH_EVENT);
    }

    /**
     * @param int|null $memberId null: all members
     *
     * @return int number of updated registrations
     */
    private function sync(int|null $memberId): int
    {
        $upcomingEventIds = array_flip($this->getUpcomingEventsIds());
        $memberIds = [];
        $updates = 0;

        $this->connection->beginTransaction();

        try {
            foreach ($this->iterateRegistrations($memberId) as $row) {
                ++$this->syncLog['processed_registrations'];
                $memberIds[(int) $row['member_id']] = true;

                $set = $this->getChangedFields($row, isset($upcomingEventIds[(int) $row['eventId']]));

                if ([] === $set) {
                    continue;
                }

                $this->connection->update('tl_calendar_events_member', $set, ['id' => (int) $row['id']]);

                ++$updates;
                ++$this->syncLog['updates'];
                $this->syncLog['log'][] = \sprintf(
                    'Update contact data for event registration ID %d with member %s %s.',
                    $row['id'],
                    $row['member_firstname'],
                    $row['member_lastname'],
                );
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->handleSyncError($exception);
        }

        $this->syncLog['processed_members'] += \count($memberIds);

        return $updates;
    }

    /**
     * Streams the non-anonymized registrations together with the data of their member.
     * Only the needed columns are selected, so the memory usage stays low.
     *
     * @return iterable<array<string, mixed>>
     */
    private function iterateRegistrations(int|null $memberId): iterable
    {
        $fields = [...self::FIELDS_ALWAYS, self::FIELD_SAC_MEMBER_ID, ...self::FIELDS_IF_NOT_EMPTY, ...self::FIELDS_UPCOMING_EVENTS_ONLY];

        $columns = ['r.id', 'r.eventId', 'm.id AS member_id'];

        foreach ($fields as $field) {
            $columns[] = 'r.'.$field;
            $columns[] = \sprintf('m.%s AS member_%s', $field, $field);
        }

        $sql = \sprintf(
            'SELECT %s FROM tl_calendar_events_member r INNER JOIN tl_member m ON m.id = r.contaoMemberId WHERE r.anonymized = 0',
            implode(', ', $columns),
        );

        $params = [];

        if (null !== $memberId) {
            $sql .= ' AND r.contaoMemberId = ?';
            $params[] = $memberId;
        }

        return $this->connection->iterateAssociative($sql.' ORDER BY r.id', $params);
    }

    private function handleSyncError(\Throwable $e): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->syncLog['with_error'] = true;
        $this->syncLog['exceptions'][] = $e->getMessage();

        $this->contaoErrorLogger?->error(
            \sprintf(
                'There has been an error while trying to update event registration contact data. Error: %s',
                $e->getMessage(),
            ),
        );
    }
}
