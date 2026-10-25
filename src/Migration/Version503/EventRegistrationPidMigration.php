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

namespace Markocupic\SacEventToolBundle\Migration\Version503;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * tl_calendar_events_member has the parent table tl_calendar_events (ptable). The event is
 * stored in pid, which replaces the former column eventId. Contao 5.7 needs pid to build the
 * back link, the "save and close" redirect and the breadcrumb.
 *
 * As long as the column eventId exists:
 * 1. Adds the column pid if it does not exist yet. This happens here and not in the schema
 *    update, so the values are copied before "contao:migrate --with-deletes" drops eventId
 *    in the same run.
 * 2. Copies eventId to pid for all registrations where they differ.
 *
 * Afterwards the schema update drops eventId (contao:migrate --with-deletes).
 *
 * @internal
 */
class EventRegistrationPidMigration extends AbstractMigration
{
    private const string TABLE = 'tl_calendar_events_member';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        $columns = $this->getColumns();

        if (!isset($columns['eventid'])) {
            return false;
        }

        if (!isset($columns['pid'])) {
            return true;
        }

        return false !== $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE pid <> eventId LIMIT 1');
    }

    public function run(): MigrationResult
    {
        $messages = [];

        if (!isset($this->getColumns()['pid'])) {
            $this->connection->executeStatement('ALTER TABLE tl_calendar_events_member ADD pid INT UNSIGNED DEFAULT 0 NOT NULL');
            $messages[] = 'tl_calendar_events_member: column "pid" added.';
        }

        $count = $this->connection->executeStatement('UPDATE tl_calendar_events_member SET pid = eventId WHERE pid <> eventId');
        $messages[] = \sprintf('tl_calendar_events_member: pid set to eventId for %d registration(s). The column "eventId" can now be dropped (contao:migrate --with-deletes).', $count);

        return $this->createResult(true, implode(' ', $messages));
    }

    /**
     * @return array<string, mixed> lower case column names
     */
    private function getColumns(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            return [];
        }

        return $schemaManager->listTableColumns(self::TABLE);
    }
}
