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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

/**
 * @internal
 */
class BackendPermissionMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_user', 'tl_user_group'])) {
            return false;
        }

        $columnsA = $schemaManager->listTableColumns('tl_user');
        $columnsB = $schemaManager->listTableColumns('tl_user_group');

        if (!isset($columnsA['id']) || !isset($columnsA['modules']) || !isset($columnsB['id']) || !isset($columnsB['modules'])) {
            return false;
        }

        $runMigration = false;

        $hasA = $this->connection->fetchOne(
            'SELECT id FROM tl_user WHERE modules LIKE :module_name',
            ['module_name' => '%sac_calendar_events_tool%'],
            ['module_name' => Types::STRING],
        );

        $hasB = $this->connection->fetchOne(
            'SELECT id FROM tl_user_group WHERE modules LIKE :module_name',
            ['module_name' => '%sac_calendar_events_tool%'],
            ['module_name' => Types::STRING],
        );

        if ($hasA || $hasB) {
            $runMigration = true;
        }

        return $runMigration;
    }

    public function run(): MigrationResult
    {
        $this->swapModuleAccess('tl_user_group', 'sac_calendar_events_tool', 'calendar');
        $this->swapModuleAccess('tl_user', 'sac_calendar_events_tool', 'calendar');

        return $this->createResult(true);
    }

    protected function swapModuleAccess(string $table_name, string $remove, string $replace = ''): void
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);

        $ids = $this->connection->fetchAllKeyValue(
            "SELECT id,modules FROM $table_name WHERE modules LIKE :module_name",
            ['module_name' => "%$remove%"],
            ['module_name' => Types::STRING],
        );

        foreach ($ids as $id => $modules) {
            $arrModules = $stringUtil->deserialize($modules, true);

            if (false !== ($i = array_search($remove, $arrModules, true))) {
                unset($arrModules[$i]);

                if ($replace && !\in_array($replace, $arrModules, true)) {
                    $arrModules[] = $replace;
                }

                $serialModules = serialize(array_filter(array_unique($arrModules)));
                $this->connection->update($table_name, ['modules' => $serialModules], ['id' => $id], ['id' => Types::INTEGER]);
            }
        }
    }
}
