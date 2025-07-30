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

namespace Markocupic\SacEventToolBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Path;
use Markocupic\ZipBundle\Zip\Zip;

class FileBackupCron extends AbstractController
{

	public function __construct(
		private readonly Connection $connection,
		private readonly string     $projectDir,
	)
	{
	}

	#[AsCronJob('15 13 * * *')]
	public function backupFiles1(): void
	{
		$dir1 = Path::join($this->projectDir, 'files/fileadmin');
		$dir2 = Path::join($this->projectDir, 'files/sektion');

		$strip = Path::join($this->projectDir, 'files');
		$destinaton = Path::join($this->projectDir, '/../backups/sacpilatus_files_page_assets_' . date('Ymd_His') . '.zip');

		(new Zip())
			->ignoreDotFiles(false)
			->stripSourcePath($strip)
			->addDirRecursive($dir1)
			->addDirRecursive($dir2)
			->run($destinaton);
	}

	#[AsCronJob('0 13 * * *')]
	public function backupFiles2(): void
	{
		$dir = Path::join($this->projectDir, 'files/sektion');
		$strip = Path::join($this->projectDir, 'files');
		$destinaton = Path::join($this->projectDir, '/../backups/sacpilatus_files_sektion_' . date('Ymd_His') . '.zip');

		(new Zip())
			->ignoreDotFiles(false)
			->stripSourcePath($strip)
			->addDirRecursive($dir)
			->run($destinaton);
	}

	public function restore(): void
	{
		throw new \Exception('The app is currently disabled');

		$backAll = $this->connection->fetchAllAssociative('SELECT * FROM tl_files_bak WHERE path LIKE "files/fileadmin/page_assets%"');

		foreach ($backAll as $back) {
			$res = $this->connection->fetchOne('SELECT id FROM tl_files WHERE path = ?', [$back['path']]);

			if ($res > 0) {
				$set = [
					'uuid' => $back['uuid'],
					'hash' => $back['hash'],
					'name' => $back['name'],
				];
				$this->connection->update('tl_files', $set, ['id' => $res]);
			} else {
				$set = $back;
				unset($set['id']);
				$this->connection->insert('tl_files', $set);
			}
		}
	}
}
