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
    private ContaoFramework $framework;
    private SyncSacMemberDatabase $syncSacMemberDatabase;

    public function __construct(ContaoFramework $framework, SyncSacMemberDatabase $syncSacMemberDatabase)
    {
        $this->framework = $framework;
        $this->syncSacMemberDatabase = $syncSacMemberDatabase;

        $this->framework->initialize();
    }

    /**
     * This is the frontend route for the member sync.
     *
     * @Route("/_sync_sac_member_database", name="sac_event_tool_sync_sac_member_database", defaults={"_scope" = "frontend"})
     */
    public function syncDatabaseAction(): JsonResponse
    {
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

    /**
     * Reconstructed anonymized entries from tl_calendar_events_membewr2
     * 24.05.2021.
     *
     * @Route("/_repair", name="repair", defaults={"_scope" = "frontend"})
     */
    public function repair(): JsonResponse
    {
        /**
         * $this->framework->initialize();
         * $arrUsers = [
         * 11360,
         * 14530,
         * 14526,
         * 20057,
         * 18904,
         * 18901,
         * 21575,
         * 21574,
         * 21573,
         * 21572,
         * 21520,
         * 21519,
         * 21375,
         * 21372,
         * 20336,
         * ];.
         *
         * $i = 0;
         *
         * foreach ($arrUsers as $id) {
         * $objEventMember = CalendarEventsMemberModel::findByPk($id);
         *
         * if (null !== $objEventMember) {
         * ++$i;
         * $objTemp = Database::getInstance()
         * ->prepare('SELECT * FROM tl_calendar_events_member2 WHERE id=?')
         * ->limit(1)
         * ->execute($id)
         * ;
         * $objEventMember->emergencyPhoneName = $objTemp->emergencyPhoneName;
         * $objEventMember->save();
         * }
         * }
         */
        $arrJson = ['message' => 'Successfully executed '.$i.' repaira.'];

        return new JsonResponse($arrJson);
    }
}
