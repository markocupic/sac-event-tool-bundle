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
 * tl_member.sacMemberId and tl_user.sacMemberId get a unique index (see the DCA files).
 * A unique index allows NULL several times, but not 0. Therefore "no SAC member id" is
 * stored as NULL instead of 0:
 *
 * 1. The column is converted to int(10) unsigned NULL default NULL.
 * 2. 0 becomes NULL.
 * 3. Duplicate SAC member ids (> 0) are listed in the migration result. They have to be
 *    resolved manually, otherwise the unique index cannot be created by the schema update.
 *
 * @internal
 */
class SacMemberIdNullableMigration extends AbstractMigration
{
    public const array TABLES = ['tl_member', 'tl_user'];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        foreach (self::TABLES as $table) {
            if ($this->needsMigration($table)) {
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $messages = [];
        $duplicates = [];

        foreach (self::TABLES as $table) {
            if (!$this->needsMigration($table)) {
                continue;
            }

            $this->connection->executeStatement(\sprintf('ALTER TABLE %s MODIFY sacMemberId INT(10) UNSIGNED NULL DEFAULT NULL', $table));
            $count = $this->connection->executeStatement(\sprintf('UPDATE %s SET sacMemberId = NULL WHERE sacMemberId = 0', $table));

            $messages[] = \sprintf('%s: column "sacMemberId" is now nullable, %d value(s) 0 replaced by NULL.', $table, $count);

            foreach ($this->findDuplicates($table) as $row) {
                $duplicates[] = \sprintf('%s.sacMemberId %s (IDs %s)', $table, $row['sacMemberId'], $row['ids']);
            }
        }

        if ([] !== $duplicates) {
            $messages[] = \sprintf(
                'Duplicate SAC member ids found. Please resolve them manually, otherwise the unique index cannot be created: %s.',
                implode('; ', $duplicates),
            );

            return $this->createResult(false, implode(' ', $messages));
        }

        return $this->createResult(true, implode(' ', $messages));
    }

    /**
     * The column still is NOT NULL or still contains 0.
     */
    private function needsMigration(string $table): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$table])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns($table);

        if (!isset($columns['sacmemberid'])) {
            return false;
        }

        if ($columns['sacmemberid']->getNotnull()) {
            return true;
        }

        return false !== $this->connection->fetchOne(\sprintf('SELECT id FROM %s WHERE sacMemberId = 0 LIMIT 1', $table));
    }

    /**
     * @return array<array{sacMemberId: int|string, ids: string}>
     */
    private function findDuplicates(string $table): array
    {
        return $this->connection->fetchAllAssociative(
            \sprintf('SELECT sacMemberId, GROUP_CONCAT(id ORDER BY id) AS ids FROM %s WHERE sacMemberId > 0 GROUP BY sacMemberId HAVING COUNT(id) > 1 ORDER BY sacMemberId', $table),
        );
    }
}
