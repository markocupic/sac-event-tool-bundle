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
use Doctrine\DBAL\Types\IntegerType;

/**
 * Converts tl_calendar_events_member.sacMemberId from varchar to int (like tl_member and tl_user).
 *
 * 1. Values that are not a plain number are corrected: the first number in the value is used
 *    ("00167400" → 167400, "370883 SAC Pilatus" → 370883, "320052." → 320052). Values without
 *    a number become 0. Every correction is listed in the migration result.
 * 2. Empty values ('' = no SAC member) become 0.
 * 3. The column is converted to int(10) unsigned NOT NULL default 0.
 *
 * @internal
 */
class EventRegistrationSacMemberIdToIntMigration extends AbstractMigration
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

        // Runs only once: as soon as the column is an integer, the migration is done
        return isset($columns['sacmemberid']) && !$columns['sacmemberid']->getType() instanceof IntegerType;
    }

    public function run(): MigrationResult
    {
        $corrections = [];

        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, sacMemberId FROM tl_calendar_events_member WHERE sacMemberId <> '' AND sacMemberId NOT REGEXP '^[0-9]+$' ORDER BY id",
        );

        foreach ($rows as $row) {
            $sacMemberId = self::toSacMemberId((string) $row['sacMemberId']);

            $this->connection->update(self::TABLE, ['sacMemberId' => (string) $sacMemberId], ['id' => (int) $row['id']]);

            $corrections[] = \sprintf('ID %d: "%s" → %d', $row['id'], $row['sacMemberId'], $sacMemberId);
        }

        $this->connection->executeStatement("UPDATE tl_calendar_events_member SET sacMemberId = '0' WHERE sacMemberId = ''");
        $this->connection->executeStatement('ALTER TABLE tl_calendar_events_member MODIFY sacMemberId INT(10) UNSIGNED NOT NULL DEFAULT 0');

        $message = 'tl_calendar_events_member: column "sacMemberId" converted to int, empty values replaced by 0.';

        if ([] !== $corrections) {
            $message .= \sprintf(' Corrected %d value(s): %s.', \count($corrections), implode('; ', $corrections));
        }

        return $this->createResult(true, $message);
    }

    /**
     * The first number in the value, without leading zeros. 0 if there is no number.
     */
    public static function toSacMemberId(string $value): int
    {
        if (!preg_match('/\d+/', $value, $matches)) {
            return 0;
        }

        return (int) $matches[0];
    }
}
