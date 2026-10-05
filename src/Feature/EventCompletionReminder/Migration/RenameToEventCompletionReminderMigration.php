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

namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * The feature was renamed from "InstructorPostEventTaskReminder" to "EventCompletionReminder".
 * Keeps existing data: renames the log table and the tl_calendar columns and updates the
 * notification type. Must run before the schema diff, otherwise Contao would create new,
 * empty columns and tables.
 *
 * See docs/features/event-completion-reminder.md
 */
class RenameToEventCompletionReminderMigration extends AbstractMigration
{
    private const OLD_TABLE = 'tl_instructor_post_event_task_reminder_log';

    private const NEW_TABLE = 'tl_event_completion_reminder_log';

    private const CALENDAR_COLUMNS = [
        'sendInstructorPostEventTaskReminder' => 'sendEventCompletionReminder',
        'instructorPostEventTaskReminderNotification' => 'eventCompletionReminderNotification',
        'instructorPostEventTaskReminderEventTypes' => 'eventCompletionReminderEventTypes',
        'instructorPostEventTaskReminderFirstOffset' => 'eventCompletionReminderFirstOffset',
        'instructorPostEventTaskReminderInterval' => 'eventCompletionReminderInterval',
    ];

    private const OLD_NOTIFICATION_TYPE = 'instructor_post_event_task_reminder';

    private const NEW_NOTIFICATION_TYPE = 'event_completion_reminder';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        return $this->shouldRenameTable() || [] !== $this->getColumnsToRename() || $this->hasOldNotificationType();
    }

    public function run(): MigrationResult
    {
        if ($this->shouldRenameTable()) {
            $this->connection->executeStatement(\sprintf('RENAME TABLE %s TO %s', self::OLD_TABLE, self::NEW_TABLE));
        }

        foreach ($this->getColumnsToRename() as $old => $new) {
            $this->connection->executeStatement(\sprintf('ALTER TABLE tl_calendar RENAME COLUMN %s TO %s', $old, $new));
        }

        if ($this->hasOldNotificationType()) {
            $this->connection->executeStatement(
                'UPDATE tl_nc_notification SET type = ? WHERE type = ?',
                [self::NEW_NOTIFICATION_TYPE, self::OLD_NOTIFICATION_TYPE],
            );
        }

        return $this->createResult(true);
    }

    private function shouldRenameTable(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->tablesExist([self::OLD_TABLE]) && !$schemaManager->tablesExist([self::NEW_TABLE]);
    }

    /**
     * @return array<string, string> old column => new column
     */
    private function getColumnsToRename(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_calendar'])) {
            return [];
        }

        $columns = array_change_key_case($schemaManager->listTableColumns('tl_calendar'));
        $rename = [];

        foreach (self::CALENDAR_COLUMNS as $old => $new) {
            if (isset($columns[strtolower($old)]) && !isset($columns[strtolower($new)])) {
                $rename[$old] = $new;
            }
        }

        return $rename;
    }

    private function hasOldNotificationType(): bool
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['tl_nc_notification'])) {
            return false;
        }

        return false !== $this->connection->fetchOne('SELECT id FROM tl_nc_notification WHERE type = ?', [self::OLD_NOTIFICATION_TYPE]);
    }
}
