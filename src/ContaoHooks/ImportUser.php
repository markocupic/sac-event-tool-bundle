<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\Input;
use Contao\UserModel;


/**
 * Class ImportUser
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class ImportUser
{


    /**
     * Allow backend users to authenticate with their sacMemberId
     * @param $strUsername
     * @param $strPassword
     * @param $strTable
     * @return bool
     */
    public function allowBackendUserToAuthenticateWithSacMemberId($strUsername, $strPassword, $strTable)
    {
        if ($strTable === 'tl_user')
        {
            if (trim($strUsername) !== '' && is_numeric($strUsername))
            {
                $objUser = UserModel::findBySacMemberId($strUsername);
                if ($objUser !== null)
                {
                    if ($objUser->sacMemberId > 0 && $objUser->sacMemberId == $strUsername)
                    {
                        Input::setPost('username', $objUser->username);
                        return true;
                    }
                }
            }


        }

        return false;
    }

}