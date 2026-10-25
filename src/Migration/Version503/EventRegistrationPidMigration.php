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
 * tl_calendar_events_member has the parent table tl_calendar_events (ptable). The parent
 * record is stored in pid, which has the same value as eventId. Contao 5.7 needs pid to
 * build the back link, the "save and close" redirect and the breadcrumb.
 *
 * Copies eventId to pid for all registrations where they differ. Runs after the schema
 * update has created the pid column.
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
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns(self::TABLE);

        if (!isset($columns['pid'], $columns['eventid'])) {
            return false;
        }

        return false !== $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE pid <> eventId LIMIT 1');
    }

    public function run(): MigrationResult
    {
        $count = $this->connection->executeStatement('UPDATE tl_calendar_events_member SET pid = eventId WHERE pid <> eventId');

        return $this->createResult(true, \sprintf('tl_calendar_events_member: pid set to eventId for %d registration(s).', $count));
    }
}
