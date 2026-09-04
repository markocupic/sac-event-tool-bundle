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

namespace Markocupic\SacEventToolBundle\Database\SyncMember;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Table;

/**
 * Manages the temporary table (tl_member_sync_temp) that buffers the imported
 * member data while the sync is running.
 */
final class TempMemberTableManager
{
    public const string TABLE_NAME = 'tl_member_sync_temp';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Drops a leftover temp table and (re)creates a fresh one.
     *
     * @throws Exception
     */
    public function create(): void
    {
        $this->drop();

        $table = new Table(self::TABLE_NAME);

        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('sacMemberId', 'integer', ['notnull' => true, 'unsigned' => true, 'default' => 0]);
        $table->addColumn('username', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('sectionId', 'blob', ['notnull' => false, 'length' => 1000]);
        $table->addColumn('firstname', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('lastname', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('addressExtra', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('street', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('poBox', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('postal', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('city', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('country', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('dateOfBirth', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('phone', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('mobile', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('email', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('gender', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('profession', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('language', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('entryYear', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('honoraryMember', 'integer', ['notnull' => true, 'unsigned' => true, 'default' => 0]);
        $table->addColumn('membershipType', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('sectionInfo1', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('sectionInfo2', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('sectionInfo3', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('debit', 'string', ['length' => 256, 'default' => '']);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['username']);
        $table->addUniqueIndex(['sacMemberId']);

        $this->connection
            ->createSchemaManager()
            ->createTable($table)
        ;
    }

    /**
     * Drops the temp table if it exists.
     *
     * @throws Exception
     */
    public function drop(): void
    {
        $schema = $this->connection->createSchemaManager();

        if ($schema->tablesExist([self::TABLE_NAME])) {
            $schema->dropTable(self::TABLE_NAME);
        }
    }
}
