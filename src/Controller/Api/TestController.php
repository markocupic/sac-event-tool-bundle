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

namespace Markocupic\SacEventToolBundle\Controller\Api;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Markocupic\ZipBundle\Zip\Zip;

class TestController extends AbstractController
{

	public function __construct(
		private readonly Connection      $connection,
		private readonly ContaoFramework $framework,
		private readonly string          $projectDir,
	)
	{
	}

	#[AsCronJob('12 12 * * *')]
	#[Route('/backup_files1', name: 'sac_pilatus.backup_files1')]
	public function backupFiles1(): Response
	{
		//die('test');
		$this->framework->initialize();

		$dir = Path::join($this->projectDir, 'files/fileadmin/page_assets');
		$strip = Path::join($this->projectDir, 'files');
		$destinaton = Path::join($this->projectDir, '/../backups/sacpilatus_files_page_assets_' . date('Ymd_His') . '.zip');
		(new Zip())
			->ignoreDotFiles(false)
			->stripSourcePath($strip)
			->addDirRecursive($dir)
			->run($destinaton);

		return new Response('Backup successfully created at: ' . $destinaton . '');
	}

	#[AsCronJob('5 12 * * *')]
	#[Route('/backup_files2', name: 'sac_pilatus.backup_files2')]
	public function backupFiles2(): Response
	{
		//die('test');
		$this->framework->initialize();

		$dir1 = Path::join($this->projectDir, 'files/sektion');
		$strip = Path::join($this->projectDir, 'files');
		$destinaton = Path::join($this->projectDir, '/../backups/sacpilatus_files_sektion_' . date('Ymd_His') . '.zip');
		(new Zip())
			->ignoreDotFiles(false)
			->stripSourcePath($strip)
			->addDirRecursive($dir1)
			->run($destinaton);

		return new Response('Backup successfully created at: ' . $destinaton . '');
	}

	#[Route('/restore', name: 'sac_pilatus.restore')]
	public function restore(): Response
	{
		throw new \Exception('Disabled');

		$this->framework->initialize();

		$baks = $this->connection->fetchAllAssociative('SELECT * FROM tl_files_bak WHERE path LIKE "files/fileadmin/page_assets%"');
		$inserts = 0;
		$updates = 0;

		foreach ($baks as $bak) {
			$res = $this->connection->fetchOne('SELECT id FROM tl_files WHERE path = ?', [$bak['path']]);
			if ($res > 0) {
				$set = [
					'uuid' => $bak['uuid'],
					'hash' => $bak['hash'],
					'name' => $bak['name'],
					//'hash' => $bak['hash'],
				];
				$this->connection->update('tl_files', $set, ['id' => $res]);
				$updates++;
			} else {
				$set = $bak;
				unset($set['id']);
				$this->connection->insert('tl_files', $set);
				$inserts++;
			}

			echo $bak['path'] . '<br>';
		}


		return new Response('Updates: ' . $updates . ' Inserts: ' . $inserts . '');
	}


}
