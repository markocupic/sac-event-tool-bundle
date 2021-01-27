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

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\Config;
use Contao\Database;
use Contao\DataContainer;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\System;
use Contao\UserModel;

/**
 * Class TlUser.
 */
class TlUser extends Backend
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');

        // Import js
        if ('user' === Input::get('do') && 'edit' === Input::get('act') && '' !== Input::get('ref')) {
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicsaceventtool/js/backend_member_autocomplete.js';
        }
    }

    /**
     * Onload callback
     * See readonly fields.
     */
    public function addReadonlyAttributeToSyncedFields(DataContainer $dc): void
    {
        // User profile
        if ('login' === Input::get('do')) {
            if ('login' === Input::get('do')) {
                $id = $this->User->id;
            } else {
                $id = $dc->id;
            }

            if ($id > 0) {
                if (!$this->User->admin) {
                    $objUser = UserModel::findByPk($id);

                    if (null !== $objUser) {
                        if ($objUser->sacMemberId > 0) {
                            $objMember = MemberModel::findOneBySacMemberId($objUser->sacMemberId);

                            if (null !== $objMember) {
                                if (!$objMember->disable) {
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
     * Onload callback.
     */
    public function showReadonlyFieldsInfoMessage(DataContainer $dc): void
    {
        if (empty($dc->id) || !is_numeric($dc->id)) {
            return;
        }

        Message::addInfo('Einige Felder werden mit der Datenbank des Zentralverbandes synchronisiert. Wenn Sie Änderungen machen möchten, müssen Sie diese zuerst dort vornehmen.');
    }

    /**
     * @param $strTable
     * @param $id
     * @param $arrSet
     */
    public function oncreateCallback($strTable, $id, $arrSet): void
    {
        $objUser = UserModel::findByPk($id);

        if (null !== $objUser) {
            // Create Backend Users home directory
            $objCreateDir = System::getContainer()->get('Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUsersHomeDirectory');
            $objCreateDir->createBackendUsersHomeDirectory($objUser);

            if ('extend' !== $arrSet['inherit']) {
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
     * Dynamically add flags to the "singleSRC" field.
     *
     * @param mixed $varValue
     *
     * @return mixed
     */
    public function setSingleSrcFlags($varValue, DataContainer $dc)
    {
        if ($dc->activeRecord) {
            switch ($dc->activeRecord->type) {
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
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_user_role ORDER BY sorting ASC')->execute();

        while ($objDb->next()) {
            $options[$objDb->id] = $objDb->title;
        }

        return $options;
    }
}
