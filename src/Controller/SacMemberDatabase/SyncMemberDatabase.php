<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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

class SyncMemberDatabase extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly SyncSacMemberDatabase $syncSacMemberDatabase,
    ) {
    }

    /**
     * This is the frontend route for the member sync.
     *
     * @Route("/_sync_sac_member_database", name="sac_event_tool_sync_sac_member_database", defaults={"_scope" = "frontend"})
     */
    public function syncDatabaseAction(): JsonResponse
    {
        $this->framework->initialize();

        // Run database sync
        $this->syncSacMemberDatabase->run();

        // Set password if there isn't one.
        $count = 0;

        $arrJson = [
            'message' => 'Successfully executed the db sync.',
            'password updates' => (string) $count,
        ];

        return new JsonResponse($arrJson);
    }
}
