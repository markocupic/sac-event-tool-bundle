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

namespace Markocupic\SacEventToolBundle\Database;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * This controller is responsible for syncing event registration data by updating
 * the "tl_calendar_events_member" table with the most recent contact information
 * and details from the "tl_member" table.
 */
#[Route('/_sync', name: self::class)]
class SyncEventRegistrationDatabase extends AbstractController
{
    private const string STOP_WATCH_EVENT = 'update_event_reg_data';

    private array $syncLog = [
        'processed_registrations' => 0,
        'processed_members' => 0,
        'updates' => 0,
        'log' => [],
        'duration' => 0,
        'with_error' => false,
        'exceptions' => [],
    ];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    public function run(): JsonResponse
    {
        $this->framework->initialize();

        $stopWatchEvent = $this->stopWatchStart();

        $this->syncAll();

        $duration = round($stopWatchEvent->stop()->getDuration() / 1000);
        $this->syncLog['duration'] = $duration;

        if (null !== $this->contaoGeneralLogger) {
            $strText = \sprintf(
                'Successful update of the member data in the event registration table "tl_calendar_events_member": processed: %d, updates: %d, errors: %d, duration: %s.',
                $this->syncLog['processed_registrations'],
                $this->syncLog['updates'],
                \count($this->syncLog['exceptions']),
                $duration.' s',
            );

            $this->contaoGeneralLogger->info($strText);
        }

        return $this->json($this->syncLog);
    }

    public function getSyncLog(): array
    {
        return $this->syncLog;
    }

    public function getUpcomingEventsIds(): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_calendar_events WHERE startDate > ? ORDER BY id',
            [
                time(),
            ],
            [
                Types::INTEGER,
            ],
        );
    }

    /**
     * Retrieves all distinct contaoMemberIds that have a corresponding entry in tl_member.
     */
    public function getContaoMemberIds(): array
    {
        return $this->connection->fetchFirstColumn(
            '
			SELECT
				DISTINCT t1.contaoMemberId
			FROM
				tl_calendar_events_member AS t1
			JOIN
				tl_member AS t2
			ON
				t1.contaoMemberId = t2.id
			',
        );
    }

    public function syncMember(int $memberId): int
    {
        $upcomingEventIds = $this->getUpcomingEventsIds();
        $affectedRows = 0;
        $this->connection->beginTransaction();

        try {
            $affectedRows = $this->processMember($memberId, $upcomingEventIds);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->handleSyncError($exception);
        }

        return $affectedRows;
    }

    private function stopWatchStart(): StopwatchEvent
    {
        return (new Stopwatch())->start(self::STOP_WATCH_EVENT);
    }

    private function syncAll(): void
    {
        $memberIds = $this->getContaoMemberIds();
        $upcomingEventIds = $this->getUpcomingEventsIds();

        $this->logProcessedMembers(\count($memberIds));
        $this->connection->beginTransaction();

        try {
            foreach ($memberIds as $memberId) {
                $this->processMember($memberId, $upcomingEventIds);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->handleSyncError($exception);
        }
    }

    private function processMember(int $memberId, array $upcomingEventIds): int
    {
        $memberData = $this->fetchMemberData($memberId);

        if (empty($memberData)) {
            return 0; // Skip processing if no data
        }

        $registrations = $this->fetchMemberEventRegistrations($memberData['id']);

        return $this->processEventRegistrations($registrations, $memberData, $upcomingEventIds);
    }

    private function processEventRegistrations(array $registrations, array $memberData, array $upcomingEventIds): int
    {
        $affectedRows = 0;

        foreach ($registrations as $registration) {
            $affectedRows += $this->processRegistration($registration, $memberData, $upcomingEventIds);
        }

        return $affectedRows;
    }

    private function logProcessedMembers(int $count): void
    {
        $this->syncLog['processed_members'] = $count;
    }

    private function processRegistration(array $registration, array $memberData, array $upcomingEventIds): int
    {
        ++$this->syncLog['processed_registrations'];

        $updateData = $this->generateUpdateData($memberData, $registration, $upcomingEventIds);

        if (empty($updateData)) {
            return 0;
        }

        $affectedRows = $this->connection->update(
            'tl_calendar_events_member',
            $updateData,
            ['id' => $registration['id']],
            ['id' => Types::INTEGER],
        );

        if (!empty($affectedRows)) {
            ++$this->syncLog['updates'];
            $this->logUpdate($registration, $memberData);

            return $affectedRows;
        }

        return 0;
    }

    private function logUpdate(array $registration, array $memberData): void
    {
        $this->syncLog['log'][] = \sprintf(
            'Update contact data for event registration ID %d with member %s %s.',
            $registration['id'],
            $memberData['firstname'],
            $memberData['lastname'],
        );
    }

    private function handleSyncError(\Throwable $e): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->syncLog['with_error'] = true;
        $this->syncLog['exceptions'][] = $e->getMessage();

            $this->contaoErrorLogger->error(
                \sprintf(
                    'There has been an error while trying to update event registration contact data. Error: %s',
                    $e->getMessage(),
                ),
            );
    }

    private function fetchMemberData(int $memberId): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM tl_member WHERE id = ?',
            [$memberId],
            [Types::INTEGER],
        );
    }

    private function fetchMemberEventRegistrations(int $memberId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_calendar_events_member WHERE contaoMemberId = ? AND anonymized = ?',
            [$memberId, 0],
            [Types::INTEGER, Types::INTEGER],
        );
    }

    private function generateUpdateData(array $memberData, array $registration, array $upcomingEventIds): array
    {
        $updateData = [
            'gender' => $memberData['gender'],
            'firstname' => $memberData['firstname'],
            'lastname' => $memberData['lastname'],
            'street' => $memberData['street'],
            'postal' => $memberData['postal'],
            'city' => $memberData['city'],
            'dateOfBirth' => $memberData['dateOfBirth'],
            'phone' => $memberData['phone'],
        ];

        // Do not override these contact data fields with empty values
        $arrContact = ['email', 'mobile'];

        foreach ($arrContact as $field) {
            if ('' !== $memberData[$field]) {
                $updateData[$field] = $memberData[$field];
            }
        }

        // Update emergencyPhone, emergencyPhoneName, foodHabits from tl_member (if not
        // empty), but only if the related event is in the future!
        if (\in_array($registration['eventId'], $upcomingEventIds, true)) {
            if ('' !== trim($memberData['emergencyPhone']) && '' !== trim($memberData['emergencyPhoneName'])) {
                $updateData['emergencyPhone'] = $memberData['emergencyPhone'];
                $updateData['emergencyPhoneName'] = $memberData['emergencyPhoneName'];
            }

            if ('' !== trim((string) $memberData['foodHabits'])) {
                $updateData['foodHabits'] = $memberData['foodHabits'];
            }
        }

        return $updateData;
    }
}
