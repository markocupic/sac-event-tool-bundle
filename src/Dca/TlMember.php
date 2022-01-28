<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\Controller;
use Contao\DataContainer;
use Contao\MemberModel;
use Contao\Message;
use Contao\System;

/**
 * Class TlMember.
 */
class TlMember extends Backend
{
    /**
     * @param $undoId
     */
    public function ondeleteCallback(DataContainer $objMember, $undoId): void
    {
        // Clear personal data f.ex.
        // Anonymize entries in tl_calendar_events_member
        // Delete avatar directory
        if ($objMember->activeRecord->id > 0) {
            $objClearFrontendUserData = System::getContainer()->get('Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData');

            $memberModel = MemberModel::findByPk($objMember->activeRecord->id);

            if (null !== $memberModel) {
                if (false === $objClearFrontendUserData->clearMemberProfile((int) $memberModel->id)) {
                    $arrErrorMsg = sprintf('Das Mitglied mit ID:%s kann nicht gelöscht werden, weil es bei Events noch auf der Buchungsliste steht.', $objMember->activeRecord->id);
                    Message::add($arrErrorMsg, 'TL_ERROR', TL_MODE);
                    Controller::redirect('contao?do=member');
                }
            }
        }
    }
}
