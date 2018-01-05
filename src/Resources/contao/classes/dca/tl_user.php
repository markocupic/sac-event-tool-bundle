<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

/**
 * Class tl_user_sac_event_tool
 */
class tl_user_sac_event_tool extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

    /**
     * @param DataContainer $dc
     */
    public function onloadCallback(DataContainer $dc)
    {

        // Sync tl_user with tl_member
        $objUser = $this->Database->prepare('SELECT * FROM tl_user WHERE sacMemberId>?')->execute(0);
        while($objUser->next())
        {
            $objSAC = $this->Database->prepare('SELECT * FROM tl_member WHERE sacMemberId=?')->execute($objUser->sacMemberId);
            if($objSAC->numRows == 1)
            {
                $set = array(
                    'firstname' => $objSAC->firstname != '' ? $objSAC->firstname :  $objUser->firstname,
                    'lastname' => $objSAC->lastname != '' ? $objSAC->lastname :  $objUser->lastname,
                    'dateOfBirth' => $objSAC->dateOfBirth > 0 ? $objSAC->dateOfBirth :  $objUser->dateOfBirth,
                    'email' => $objSAC->email != '' ? $objSAC->email :  $objUser->email,
                    'street' => $objSAC->street != '' ? $objSAC->street :  $objUser->street,
                    'postal' => $objSAC->postal != '' ? $objSAC->postal :  $objUser->postal,
                    'city' => $objSAC->city != '' ? $objSAC->city :  $objUser->city,
                    'country' => $objSAC->country != '' ? $objSAC->country :  $objUser->country,
                    'gender' => $objSAC->gender != '' ? $objSAC->gender :  $objUser->gender,
                );
                $this->Database->prepare('UPDATE tl_user %s WHERE id=?')->set($set)->execute($objUser->id);
            }else{
                $this->Database->prepare('UPDATE tl_user SET sacMemberId=? WHERE id=?')->execute(0, $objUser->id);
            }
        }

        if (!$this->Input->post)
        {

            // sync name with firstname and lastname
            $objUser = $this->Database->prepare('SELECT * FROM tl_user')->execute();
            while ($objUser->next())
            {
                $userModel = UserModel::findByPk($objUser->id);
                $userModel->name = $userModel->firstname . ' ' . $userModel->lastname;
                $userModel->save();
            }

            if (!$this->User->isAdmin)
            {
                // Readonly acces to non admins
                $GLOBALS['TL_DCA']['tl_user']['fields']['name']['eval']['readonly'] = true;
            }
        }

        Message::addInfo('Einige Felder werden mit der Datenbank des Zentralverbandes synchronisiert. Wenn Sie &Auml;nderungen machen möchten, müssen Sie diese zuerst dort vornehmen.');

    }


    /**
     * Dynamically add flags to the "singleSRC" field
     *
     * @param mixed $varValue
     * @param DataContainer $dc
     *
     * @return mixed
     */
    public function setSingleSrcFlags($varValue, DataContainer $dc)
    {
        if ($dc->activeRecord)
        {
            switch ($dc->activeRecord->type)
            {
                case 'avatarSRC':
                    $GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['extensions'] = Config::get('validImageTypes');
                    break;
            }
        }

        return $varValue;
    }

}