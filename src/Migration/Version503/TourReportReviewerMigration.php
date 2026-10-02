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
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\Connection;

/**
 * @internal
 *
 * Migration: Convert legacy comma-separated user IDs into the new serialized format.
 *
 * In the previous system, user IDs were stored as a serialized, comma-separated
 * list of integers (e.g. "12,58,306"). Only IDs from the `tl_user` table were supported.
 *
 * The new format allows storing both user IDs and user role IDs. Each entry is now
 * represented as a string containing a prefix and an integer value, for example:
 *
 *     user_id:306
 *     user_role_id:58
 *
 * These values are stored inside a serialized array, e.g.:
 *
 *     a:2:{
 *         i:0;s:15:"user_role_id:58";
 *         i:1;s:11:"user_id:306";
 *     }
 *
 * This migration converts all legacy comma-separated ID lists into the new
 * prefixed and serialized representation. Invalid or non-existing user IDs are
 * removed, and empty results are stored as NULL.
 */
class TourReportReviewerMigration extends AbstractMigration
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_user', 'tl_event_organizer'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_user');

        if (!isset($columns['id']) || !isset($columns['email'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_event_organizer');

        if (!isset($columns['id']) || !isset($columns['notifywebmasteroneweventblog'])) {
            return false;
        }

        // Check if there is at least one non-null value
        $hasNonNull = $this->connection->fetchOne(
            'SELECT 1 FROM tl_event_organizer WHERE notifyWebmasterOnNewEventBlog IS NOT NULL LIMIT 1',
        );

        if (false === $hasNonNull) {
            return false;
        }

        // Check if there is at least one legacy value (no prefixes)
        $qb = $this->connection->createQueryBuilder();

        $qb
            ->select('id')
            ->from('tl_event_organizer')
            ->where('notifyWebmasterOnNewEventBlog IS NOT NULL')
            ->andWhere("notifyWebmasterOnNewEventBlog NOT LIKE '%user_id:%'")
            ->andWhere("notifyWebmasterOnNewEventBlog NOT LIKE '%user_role_id:%'")
            ->setMaxResults(1)
        ;

        $hasLegacy = $qb->fetchOne();

        return false !== $hasLegacy;
    }

    public function run(): MigrationResult
    {
        $organizers = $this->connection->fetchAllAssociative('SELECT id,notifyWebmasterOnNewEventBlog FROM tl_event_organizer');

        foreach ($organizers as $organizer) {
            $items = StringUtil::deserialize($organizer['notifyWebmasterOnNewEventBlog'], true);

            if (empty($items)) {
                $this->connection->executeStatement('UPDATE tl_event_organizer SET notifyWebmasterOnNewEventBlog = NULL WHERE id = ?', [$organizer['id']]);
                continue;
            }

            $newItems = [];

            foreach ($items as $item) {
                if (is_numeric($item) && null !== UserModel::findById((int) $item)) {
                    $newItems[] = 'user_id:'.$item;
                }
            }

            if (empty($newItems)) {
                $this->connection->executeStatement('UPDATE tl_event_organizer SET notifyWebmasterOnNewEventBlog = NULL WHERE id = ?', [$organizer['id']]);
                continue;
            }

            $set = [
                'notifyWebmasterOnNewEventBlog' => serialize($newItems),
            ];

            $this->connection->update('tl_event_organizer', $set, ['id' => $organizer['id']]);
        }

        return new MigrationResult(true, 'Migrated notifyWebmasterOnNewEventBlog format.');
    }
}
