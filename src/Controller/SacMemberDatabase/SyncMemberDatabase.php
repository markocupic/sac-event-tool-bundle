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

namespace Markocupic\SacEventToolBundle\Controller\SacMemberDatabase;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\SacMemberDatabase\SyncSacMemberDatabase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Mirror/Update tl_member from SAC Member Database Zentralverband Bern
 * Unidirectional sync
 * SAC Member Database Zentralverband Bern -> tl_member.
 */
class SyncMemberDatabase extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly SyncSacMemberDatabase $syncSacMemberDatabase,
    ) {
    }

    #[Route('/_sync_sac_member_database', name: 'sac_event_tool_sync_sac_member_database')]
    public function syncDatabaseAction(): JsonResponse
    {
        // Run database sync
        $this->syncSacMemberDatabase->run();

        // Get the log
        $arrLog = $this->syncSacMemberDatabase->getSyncLog();

        $arrJson = [
            'message' => 'Successfully executed the db sync.',
            'processed' => $arrLog['processed'],
            'inserts' => $arrLog['inserts'],
            'updates' => $arrLog['updates'],
            'log' => $arrLog['log'],
            'duration' => $arrLog['duration'].' s',
            'with_error' => $arrLog['with_error'],
            'exception' => $arrLog['exception'],
        ];

        return new JsonResponse($arrJson);
    }
}
