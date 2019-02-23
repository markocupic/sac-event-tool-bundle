<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Class tl_member_sac_bundle
 */
class tl_member_sac_bundle extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
        $this->import('Database');

    }


    /**
     * @param DC_Table $objMember
     * @param $undoId
     */
    public function ondeleteCallback(DC_Table $objMember, $undoId)
    {
        // Clear personal data f.ex.
        // Anonymize entries in tl_calendar_events_member
        // Delete avatar directory
        if ($objMember->activeRecord->id > 0)
        {
            if (false === \Markocupic\SacEventToolBundle\ClearPersonalMemberData::clearMemberProfile($objMember->activeRecord->id))
            {
                $arrErrorMsg = sprintf('Das Mitglied mit ID:%s kann nicht gelöscht werden, weil es bei Events noch auf der Buchungsliste steht.', $objMember->activeRecord->id);
                \Contao\Message::add($arrErrorMsg, 'TL_ERROR', TL_MODE);
                \Contao\Controller::redirect('contao?do=member');
            }
        }

    }
}
