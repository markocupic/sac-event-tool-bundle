<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\Input;
use Contao\System;
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

                    if ($objUser->sacMemberId > 0 && $objUser->sacMemberId === $strUsername)
                    {
                        // Used for password recovery
                        /** @var Request $request */
                        $request = System::getContainer()->get('request_stack')->getCurrentRequest();
                        $request->request->set('username', $objUser->username);

                        // Used for backend login
                        Input::setPost('username', $objUser->username);
                        return true;
                    }
                }
            }
        }

        return false;
    }

}