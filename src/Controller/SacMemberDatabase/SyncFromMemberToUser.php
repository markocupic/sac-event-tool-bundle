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

use Markocupic\SacEventToolBundle\SacMemberDatabase\SyncSacMemberDatabase;
use Markocupic\SacEventToolBundle\User\BackendUser\SyncMemberWithUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Mirror/Update tl_user from tl_member
 * Unidirectional sync tl_member -> tl_user.
 */
#[Route('/_sync_from_member_to_user', name: 'sac_event_tool_sync_from_member_to_user')]
class SyncFromMemberToUser extends AbstractController
{
    public function __construct(
        private readonly SyncMemberWithUser $syncMemberWithUser,
        private readonly SyncSacMemberDatabase $syncSacMemberDatabase,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        // Run mirroring
        $this->syncMemberWithUser->syncMemberWithUser();

        // Get the log
        $arrLog = $this->syncMemberWithUser->getSyncLog();

        $arrJson = [
            'message' => 'Successfully executed the db sync.',
            'processed' => $arrLog['processed'],
            'updates' => $arrLog['updates'],
            'duration' => $arrLog['duration'].' s',
            'with_error' => $arrLog['with_error'],
            'exception' => $arrLog['exception'],
            'log' => $arrLog['log'],
        ];

        return new JsonResponse($arrJson);
    }

    public function run(): JsonResponse
    {
        return $this();
    }
}
