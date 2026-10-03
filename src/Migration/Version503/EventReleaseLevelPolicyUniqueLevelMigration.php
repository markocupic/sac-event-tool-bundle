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
 * Prepares tl_event_release_level_policy for the unique index (pid, level):
 * - the column "level" becomes nullable (NULL instead of 0 for new, not yet saved records)
 * - level 0 is replaced by NULL, so several unsaved records in the same system do not collide.
 *
 * Duplicate levels (same pid and level > 0) cannot be fixed automatically. The migration reports them;
 * the schema update fails to create the unique index until they are fixed in the back end.
 *
 * @internal
 */
class EventReleaseLevelPolicyUniqueLevelMigration extends AbstractMigration
{
    private const string TABLE = 'tl_event_release_level_policy';

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

        // Runs only once: as soon as the column is nullable, the migration is done
        return isset($columns['level']) && $columns['level']->getNotnull();
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement('ALTER TABLE tl_event_release_level_policy MODIFY level SMALLINT(2) UNSIGNED DEFAULT NULL');
        $this->connection->executeStatement('UPDATE tl_event_release_level_policy SET level = NULL WHERE level = 0');

        $duplicates = $this->connection->fetchAllAssociative(
            'SELECT pid, level, COUNT(*) AS count FROM tl_event_release_level_policy WHERE level IS NOT NULL GROUP BY pid, level HAVING COUNT(*) > 1 ORDER BY pid, level',
        );

        if ([] !== $duplicates) {
            $list = array_map(static fn (array $row): string => \sprintf('release level system (pid) %d: level %d (%dx)', $row['pid'], $row['level'], $row['count']), $duplicates);

            return $this->createResult(false, \sprintf(
                'tl_event_release_level_policy contains duplicate levels, the unique index (pid, level) cannot be created: %s. Fix the levels in the back end and run the migration again.',
                implode('; ', $list),
            ));
        }

        return $this->createResult(true, 'tl_event_release_level_policy: column "level" is now nullable, level 0 has been replaced by NULL.');
    }
}
