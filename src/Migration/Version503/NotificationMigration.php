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
use Doctrine\DBAL\Exception;

/**
 * @internal
 */
class NotificationMigration extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_nc_language', 'tl_nc_notification'])) {
            return false;
        }

        $columnsA = $schemaManager->listTableColumns('tl_nc_language');
        $columnsB = $schemaManager->listTableColumns('tl_nc_notification');

        if (!isset($columnsA['id']) || !isset($columnsA['email_text']) || !isset($columnsB['id']) || !isset($columnsB['type'])) {
            return false;
        }

        $runMigration = false;

        $hasResultA = $this->connection->fetchOne(
            "SELECT id FROM tl_nc_language WHERE email_text LIKE '%do=sac_calendar_events_tool%'",
        );

        $hasResultB = $this->connection->fetchOne(
            "SELECT id FROM tl_nc_notification WHERE type = 'receipt_event_registration'",
        );

        $hasResultC = $this->connection->fetchOne(
            "SELECT id FROM tl_nc_notification WHERE type = 'accept_event_participation'",
        );

        if ($hasResultA || $hasResultB || $hasResultC) {
            $runMigration = true;
        }

        return $runMigration;
    }

    public function run(): MigrationResult
    {
        $this->refactorModuleName();
        $this->renameNotificationType();

        return $this->createResult(true);
    }

    /**
     * @throws Exception
     */
    protected function refactorModuleName(): void
    {
        $ids = $this->connection->fetchAllKeyValue(
            "SELECT id,email_text FROM tl_nc_language WHERE email_text LIKE '%do=sac_calendar_events_tool%'",
        );

        foreach ($ids as $id => $text) {
            $set = [
                'email_text' => str_replace('do=sac_calendar_events_tool', 'do=calendar', $text),
            ];

            $this->connection->update('tl_nc_language', $set, ['id' => $id]);
        }
    }

    /**
     * @throws Exception
     */
    protected function renameNotificationType(): void
    {
        $setA = [
            'type' => 'event_registration',
        ];

        $this->connection->update('tl_nc_notification', $setA, ['type' => 'receipt_event_registration']);

        $setB = [
            'type' => 'onchange_state_of_subscription',
        ];

        $this->connection->update('tl_nc_notification', $setB, ['type' => 'accept_event_participation']);
    }
}
