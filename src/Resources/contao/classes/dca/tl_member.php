<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
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
        // Delete items from tl_calendar_events_member of a certain member
        Database::getInstance()->prepare('DELETE FROM tl_undo WHERE id=?')->execute($undoId);
        $oMember = MemberModel::findByPk($objMember->id);
        if ($oMember !== null)
        {
            Database::getInstance()->prepare('DELETE FROM tl_calendar_events_member WHERE sacMemberId=?')->execute($oMember->sacMemberId);
        }
    }
}
