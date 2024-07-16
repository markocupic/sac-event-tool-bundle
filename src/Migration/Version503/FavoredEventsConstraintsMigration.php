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

namespace Markocupic\SacEventToolBundle\Migration\Version503;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class FavoredEventsConstraintsMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_member', 'tl_calendar_events', 'tl_favored_events'])) {
            return false;
        }

        $columnsA = $schemaManager->listTableColumns('tl_member');
        $columnsB = $schemaManager->listTableColumns('tl_calendar_events');
        $columnsC = $schemaManager->listTableColumns('tl_favored_events');

        if (!isset($columnsA['id']) || !isset($columnsB['id']) || !isset($columnsC['eventid']) || !isset($columnsC['memberid'])) {
            return false;
        }

        $this->connection->executeStatement('ALTER TABLE `tl_favored_events` ADD CONSTRAINT `ondelete_contao_event` FOREIGN KEY IF NOT EXISTS (`eventId`) REFERENCES `tl_calendar_events`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION');
        $this->connection->executeStatement('ALTER TABLE `tl_favored_events` ADD CONSTRAINT `ondelete_contao_frontend_user` FOREIGN KEY IF NOT EXISTS (`memberId`) REFERENCES `tl_member`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION');

        return false;
    }

    public function run(): MigrationResult
    {
        return $this->createResult(true);
    }
}
