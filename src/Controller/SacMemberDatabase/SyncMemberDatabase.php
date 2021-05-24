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

use Contao\CalendarEventsMemberModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Markocupic\SacEventToolBundle\SacMemberDatabase\SyncSacMemberDatabase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SyncMemberDatabase.
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
     */
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
        $this->syncSacMemberDatabase->run();
        $arrJson = ['message' => 'Successfully executed the db sync.'];

        return new JsonResponse($arrJson);
    }

    /**
     * Reconstructed anonymized entries from tl_calendar_events_membewr2
     * 24.05.2021
     *
     * @Route("/_repair", name="repair", defaults={"_scope" = "frontend"})
     */
    public function repair(): JsonResponse
    {
        /**
        $this->framework->initialize();
        $arrUsers = [
            11360,
            14530,
            14526,
            20057,
            18904,
            18901,
            21575,
            21574,
            21573,
            21572,
            21520,
            21519,
            21375,
            21372,
            20336,
        ];

        $i = 0;

        foreach ($arrUsers as $id) {
            $objEventMember = CalendarEventsMemberModel::findByPk($id);

            if (null !== $objEventMember) {
                ++$i;
                $objTemp = Database::getInstance()
                    ->prepare('SELECT * FROM tl_calendar_events_member2 WHERE id=?')
                    ->limit(1)
                    ->execute($id)
                ;
                $objEventMember->emergencyPhoneName = $objTemp->emergencyPhoneName;
                $objEventMember->save();
            }
        }
        **/
        $arrJson = ['message' => 'Successfully executed '.$i.' repaira.'];

        return new JsonResponse($arrJson);
    }
}
