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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

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
	 * @return bool
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function shouldRun(): bool
	{
		$schemaManager = $this->connection->createSchemaManager();

		if (!$schemaManager->tablesExist(['tl_nc_language'])) {
			return false;
		}

		$columns = $schemaManager->listTableColumns('tl_nc_language');

		if (!isset($columns['email_text']) || !isset($columns['id'])) {
			return false;
		}

		$runMigration = false;

		$hasResultA = $this->connection->fetchOne(
			'SELECT id FROM tl_nc_language WHERE email_text LIKE :needle',
			['needle' => '%do=sac_calendar_events_tool%'],
			['needle' => Types::STRING],
		);

		if ($hasResultA) {
			$runMigration = true;
		}

		return $runMigration;
	}

	/**
	 * @return MigrationResult
	 */
	public function run(): MigrationResult
	{

		$this->refactorModuleName();

		return $this->createResult(true);
	}

	/**
	 * @return void
	 * @throws \Doctrine\DBAL\Exception
	 */
	protected function refactorModuleName(): void
	{

		$ids = $this->connection->fetchAllKeyValue(
			"SELECT id,email_text FROM tl_nc_language WHERE email_text LIKE :needle",
			['needle' => "%do=sac_calendar_events_tool%"],
			['needle' => Types::STRING],
		);

		foreach ($ids as $id => $text) {
			$set = [
				'email_text' => str_replace('do=sac_calendar_events_tool', 'do=calendar', $text),
			];

			$this->connection->update('tl_nc_language', $set, ['id' => $id]);
		}
	}
}
