<?php

declare(strict_types=1);

/*
 * This file is part of Contao Theme SAC Pilatus.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-theme-sac-pilatus
 */

namespace Markocupic\SacEventToolBundle\Migration\Version503;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class CalendarMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_calendar'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_calendar');

        if (!isset($columns['id']) || !isset($columns['notifyoneventpublish']) || !isset($columns['notifyoneventreleaselevelchange']) || !isset($columns['adviceoneventpublish']) || !isset($columns['adviceoneventreleaselevelchange'])) {
            return false;
        }

        $runMigration = false;

        $hasOneA = $this->connection->fetchOne(
            'SELECT id FROM tl_calendar WHERE notifyOnEventPublish != adviceOnEventPublish',
        );

        $hasOneB = $this->connection->fetchOne(
            'SELECT id FROM tl_calendar WHERE notifyOnEventReleaseLevelChange != adviceOnEventReleaseLevelChange',
        );

        if ($hasOneA || $hasOneB) {
            $runMigration = true;
        }

        return $runMigration;
    }

    public function run(): MigrationResult
    {
        // hasOneA
        $this->copyContentFromFieldAtoFieldB('tl_calendar', 'adviceOnEventPublish', 'notifyOnEventPublish');

		// hasOneB
        $this->copyContentFromFieldAtoFieldB('tl_calendar', 'adviceOnEventReleaseLevelChange', 'notifyOnEventReleaseLevelChange');

        return $this->createResult(true);
    }

    protected function copyContentFromFieldAtoFieldB(string $table_name, string $from, string $to): int|string
    {
        return $this->connection->executeStatement(
            "UPDATE $table_name SET $to = $from WHERE $to != $from",
        );
    }
}
