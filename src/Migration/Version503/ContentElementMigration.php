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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

/**
 * @internal
 */
class ContentElementMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_content'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_content');

        if (!isset($columns['customtpl'])) {
            return false;
        }

        $runMigration = false;

        $hasOneA = $this->connection->fetchOne(
            'SELECT id FROM tl_content WHERE customTpl = :template_name',
            [
                'template_name' => 'ce_hyperlink_bootstrap_button',
            ],
            [
                'template_name' => Types::STRING,
            ]
        );

        if ($hasOneA) {
            $runMigration = true;
        }

        return $runMigration;
    }

    public function run(): MigrationResult
    {
        $this->swapTemplate('tl_content', 'customTpl', 'ce_hyperlink_bootstrap_button', 'content_element/hyperlink/bootstrap_button');

        return $this->createResult(true);
    }

    protected function swapTemplate(string $table_name, string $field_name, string $old, string $new): int|string
    {
        return $this->connection->executeStatement(
            "UPDATE $table_name SET $field_name = '$new' WHERE $field_name = :template_name",
            [
                'template_name' => $old,
            ],
            [
                'template_name' => Types::STRING,
            ]
        );
    }
}
