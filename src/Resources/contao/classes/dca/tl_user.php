<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Class tl_user_sac_event_tool
 */
class tl_user_sac_event_tool extends \Contao\Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');

        // Import js
        if (\Contao\Input::get('do') === 'user' && \Contao\Input::get('act') === 'edit' && \Contao\Input::get('ref') != '')
        {
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicsaceventtool/js/backend_member_autocomplete.js';
        }
    }

    /**
     * Onload callback
     * See readonly fields
     * @param DataContainer $dc
     */
    public function addReadonlyAttributeToSyncedFields(DataContainer $dc)
    {
        // User profile
        if (\Contao\Input::get('do') === 'login')
        {
            if (\Contao\Input::get('do') === 'login')
            {
                $id = $this->User->id;
            }
            else
            {
                $id = $dc->id;
            }

            if ($id > 0)
            {
                if (!$this->User->admin)
                {
                    $objUser = \Contao\UserModel::findByPk($id);
                    if ($objUser !== null)
                    {
                        if ($objUser->sacMemberId > 0)
                        {
                            $objMember = \Contao\MemberModel::findOneBySacMemberId($objUser->sacMemberId);
                            if ($objMember !== null)
                            {
                                if (!$objMember->disable)
                                {
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['gender']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['firstname']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['lastname']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['name']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['email']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['phone']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['mobile']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['street']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['postal']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['city']['eval']['readonly'] = true;
                                    $GLOBALS['TL_DCA']['tl_user']['fields']['dateOfBirth']['eval']['readonly'] = true;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Onload callback
     * @param DataContainer $dc
     */
    public function showReadonlyFieldsInfoMessage(DataContainer $dc)
    {
        if ($dc->id == '' || !is_numeric($dc->id))
        {
            return;
        }
        
        \Contao\Message::addInfo('Einige Felder werden mit der Datenbank des Zentralverbandes synchronisiert. Wenn Sie Änderungen machen möchten, müssen Sie diese zuerst dort vornehmen.');
    }

    /**
     * @param DataContainer $dc
     */
    public function oncreateCallback($strTable, $id, $arrSet)
    {
        $objUser = \Contao\UserModel::findByPk($id);
        if ($objUser !== null)
        {
            // Create Backend Users home directory
            $objCreateDir = \Contao\System::getContainer()->get('Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUsersHomeDirectory');
            $objCreateDir->createBackendUsersHomeDirectory($objUser);

            if ($arrSet['inherit'] !== 'extend')
            {
                $objUser->inherit = 'extend';
                $objUser->pwChange = '1';
                $defaultPassword = Config::get('SAC_EVT_DEFAULT_BACKEND_PASSWORD');
                $objUser->password = password_hash($defaultPassword, PASSWORD_DEFAULT);
                $objUser->tstamp = 0;
                $objUser->save();
                $this->reload;
            }
        }
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

    /**
     * @return array
     */
    public function optionsCallbackUserRoles()
    {
        $options = [];
        $objDb = \Database::getInstance()->prepare('SELECT * FROM tl_user_role ORDER BY sorting ASC')->execute();
        while ($objDb->next())
        {
            $options[$objDb->id] = $objDb->title;
        }

        return $options;
    }

}
