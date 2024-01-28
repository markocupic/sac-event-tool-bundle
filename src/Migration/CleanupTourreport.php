<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Config\EventExecutionState;
use Markocupic\SacEventToolBundle\Config\EventState;

class CleanupTourreport extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return 'Cleanup Tourreport Migration Jan 2024 and redefine the function of tl_calendar_events.executionState';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_calendar_events'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_calendar_events');

        if (!isset($columns['_migrated']) || !isset($columns['executionstate']) || !isset($columns['eventstate']) || !isset($columns['_executionstate_bak']) || !isset($columns['_eventstate_bak'])) {
            return false;
        }

        $result = $this->connection->fetchOne('SELECT * FROM tl_calendar_events WHERE _migrated = ?', ['']);

        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        try {
            $this->connection->beginTransaction();

            $events = $this->connection->fetchAllAssociative('SELECT * FROM tl_calendar_events WHERE _migrated = ?', ['']);

            foreach ($events as $event) {
                $set = [];

                if (!$event['_migrated']) {
                    $set['_executionState_bak'] = $event['executionState'];
                    $set['_eventState_bak'] = $event['executionState'];
                    $set['_migrated'] = '1';
                    $set['executionState'] = '';

                    $this->connection->update('tl_calendar_events', $set, ['id' => (int) $event['id']]);

                    if ('event_canceled' === $event['executionState']) {
                        $set['eventState'] = EventState::STATE_CANCELED;
                        $set['executionState'] = EventExecutionState::STATE_NOT_EXECUTED_LIKE_PREDICTED;
                        $this->connection->update('tl_calendar_events', $set, ['id' => (int) $event['id']]);
                    }

                    if ('event_rescheduled' === $event['executionState']) {
                        $set['eventState'] = EventState::STATE_RESCHEDULED;
                        $set['executionState'] = EventExecutionState::STATE_EXECUTED_LIKE_PREDICTED;
                        $this->connection->update('tl_calendar_events', $set, ['id' => (int) $event['id']]);
                    }

                    if (EventExecutionState::STATE_EXECUTED_LIKE_PREDICTED === $event['executionState']) {
                        $set['eventState'] = '';
                        $set['executionState'] = EventExecutionState::STATE_EXECUTED_LIKE_PREDICTED;
                        $this->connection->update('tl_calendar_events', $set, ['id' => (int) $event['id']]);
                    }

                    if ('event_adapted' === $event['executionState']) {
                        $set['eventState'] = '';
                        $set['executionState'] = EventExecutionState::STATE_NOT_EXECUTED_LIKE_PREDICTED;
                        $this->connection->update('tl_calendar_events', $set, ['id' => (int) $event['id']]);
                    }
                }
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();

            return new MigrationResult(
                false,
                sprintf('Migration "%s" failed with error message: %s', $this->getName(), $e->getMessage()),
            );
        }

        return new MigrationResult(
            true,
            $this->getName()
        );
    }
}
