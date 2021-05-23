<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
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
 * Class SyncMemberDatabase
 * @package Markocupic\SacEventToolBundle\Controller\SacMemberDatabase
 */
class SyncMemberDatabase extends AbstractController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var SyncSacMemberDatabase
     */
    private $syncSacMemberDatabase;

    /**
     * SyncMemberDatabase constructor.
     * @param ContaoFramework $framework
     * @param SyncSacMemberDatabase $syncSacMemberDatabase
     */
    public function __construct(ContaoFramework $framework, SyncSacMemberDatabase $syncSacMemberDatabase)
    {
        $this->framework = $framework;
        $this->syncSacMemberDatabase = $syncSacMemberDatabase;

        $this->framework->initialize();
    }

    /**
     * This is the frontend route for the member sync
     *
     * @Route("/_sync_sac_member_database", name="sac_event_tool_sync_sac_member_database", defaults={"_scope" = "frontend"})
     */
    public function syncDatabaseAction(): JsonResponse
    {
        $this->syncSacMemberDatabase->run();
        $arrJson = ['message' => 'Successfully executed the db sync.'];

        return new JsonResponse($arrJson);
    }
}
