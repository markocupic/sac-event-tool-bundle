<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\System;
use Contao\MemberModel;
use Contao\Message;
use Contao\Controller;
use Contao\DataContainer;

/**
 * Class TlMember
 * @package Markocupic\SacEventToolBundle\Dca
 */
class TlMember extends Backend
{

    /**
     * @param DataContainer $objMember
     * @param $undoId
     */
    public function ondeleteCallback(DataContainer $objMember, $undoId)
    {
        // Clear personal data f.ex.
        // Anonymize entries in tl_calendar_events_member
        // Delete avatar directory
        if ($objMember->activeRecord->id > 0)
        {
            $objClearFrontendUserData = System::getContainer()->get('Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData');

            $memberModel = MemberModel::findByPk($objMember->activeRecord->id);
            if ($memberModel !== null)
            {
                if (false === $objClearFrontendUserData->clearMemberProfile((int)$memberModel->id))
                {
                    $arrErrorMsg = sprintf('Das Mitglied mit ID:%s kann nicht gelöscht werden, weil es bei Events noch auf der Buchungsliste steht.', $objMember->activeRecord->id);
                    Message::add($arrErrorMsg, 'TL_ERROR', TL_MODE);
                    Controller::redirect('contao?do=member');
                }
            }
        }
    }
}
