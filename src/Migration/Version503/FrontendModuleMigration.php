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
class FrontendModuleMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_module'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_module');

        if (!isset($columns['id']) || !isset($columns['type'])) {
            return false;
        }

        $runMigration = false;

        $hasOneA = $this->connection->fetchOne(
            'SELECT id FROM tl_module WHERE type = "pilatus_export_2021"',
        );

        if ($hasOneA) {
            $runMigration = true;
        }

        return $runMigration;
    }

    public function run(): MigrationResult
    {
        // hasOneA
        $this->renameModule('tl_module', 'pilatus_export_2021', 'pilatus_export');

        return $this->createResult(true);
    }

    protected function renameModule(string $table_name, string $module_type_old, string $module_type_new): int|string
    {
        return $this->connection->executeStatement("UPDATE $table_name SET type = '$module_type_new' WHERE type = '$module_type_old'");
    }
}
