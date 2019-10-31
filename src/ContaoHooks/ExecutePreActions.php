<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\Input;
use Contao\MemberModel;
use Contao\StringUtil;

/**
 * Class ExecutePreActions
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class ExecutePreActions
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * Constructor.
     *
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * @param string $strAction
     */
    public function executePreActions($strAction = '')
    {
        // Autocompleter when registrating event members manually in the backend
        if ($strAction === 'autocompleterLoadMemberDataFromSacMemberId')
        {
            // Output
            $json = array('status' => 'error');
            $objMemberModel = MemberModel::findBySacMemberId(Input::post('sacMemberId'));
            if ($objMemberModel !== null)
            {
                $json = $objMemberModel->row();
                $json['name'] = $json['firstname'] . ' ' . $json['lastname'];
                $json['username'] = str_replace(' ', '', strtolower($json['name']));
                $json['dateOfBirth'] = Date::parse(Config::get('dateFormat'), $json['dateOfBirth']);
                $json['status'] = 'success';
                // Bin to hex otherwise there will be a json error
                $json['avatar'] = $json['avatar'] != '' ? StringUtil::binToUuid($json['avatar']) : '';
                $json['password'] = '';

                $html = '<div>';
                $html .= '<h1>Mitglied gefunden</h1>';
                $html .= sprintf('<div>Sollen die Daten von %s %s &uuml;bernommen werden?</div>', $objMemberModel->firstname, $objMemberModel->lastname);
                $html .= '<button class="tl_button">Ja</button> <button class="tl_button">nein</button>';
                $json['html'] = $html;
            }

            // Send it to the browser
            echo html_entity_decode(json_encode($json));
            exit();
        }

        // editAllNavbarHandler in the Contao backend when using the overrideAll or editAll mode
        if ($strAction === 'editAllNavbarHandler')
        {
            if (Input::post('subaction') == 'loadNavbar')
            {
                $arrJSON = array();
                $arrJSON['navbar'] = '';
                $arrJSON['status'] = 'error';
                $arrJSON['subaction'] = Input::post('subaction');
                if (($objUser = BackendUser::getInstance()) !== null)
                {
                    $objTemplate = new BackendTemplate('edit_all_navbar_helper');
                    $arrJSON['navbar'] = $objTemplate->parse();
                    $arrJSON['status'] = 'success';
                }
                echo html_entity_decode(json_encode($arrJSON));
                exit();
            }

            if (Input::post('subaction') == 'getSessionData')
            {
                $arrJSON = array();
                $arrJSON['session'] = '';
                $arrJSON['status'] = 'error';
                $arrJSON['sessionData'] = array();
                $strTable = Input::get('table');
                $strAction = Input::get('act');
                $strKey = $strAction . $strTable != '' ? '-' .  $strTable: '';


                if (($objUser = BackendUser::getInstance()) !== null)
                {
                    $objDb = Database::getInstance()->prepare('SELECT * FROM tl_user WHERE id=?')->limit(1)->execute($objUser->id);
                    if ($objDb->numRows)
                    {
                        $arrSession = StringUtil::deserialize($objDb->session, true);
                        if (!isset($arrSession['editAllHelper'][$strKey]))
                        {
                            $arrChecked = array();
                        }
                        else
                        {
                            $arrChecked = StringUtil::deserialize($arrSession['editAllHelper'][$strKey], true);
                        }

                        $arrJSON['sessionData'] = $arrChecked;
                        $arrJSON['status'] = 'success';
                    }
                }
                echo html_entity_decode(json_encode($arrJSON));
                exit();
            }

            if (Input::post('subaction') == 'saveSessionData')
            {
                $arrJSON = array();
                $arrJSON['session'] = '';
                $arrJSON['status'] = 'error';
                $strTable = Input::get('table');
                $strAction = Input::get('act');
                $strKey = $strAction . $strTable != '' ? '-' .  $strTable: '';
                if (($objUser = BackendUser::getInstance()) !== null)
                {
                    $objDb = Database::getInstance()->prepare('SELECT * FROM tl_user WHERE id=?')->limit(1)->execute($objUser->id);
                    if ($objDb->numRows)
                    {
                        $arrSession = StringUtil::deserialize($objDb->session, true);
                        $arrSession['editAllHelper'][$strKey] = Input::post('checkedItems');
                        $set = array('session' => serialize($arrSession));
                        Database::getInstance()->prepare('UPDATE tl_user SET session=? WHERE id=?')->set($set)->execute(serialize($arrSession), $objUser->id);
                        $arrJSON['status'] = 'success';
                    }
                }
                echo html_entity_decode(json_encode($arrJSON));
                exit();
            }
        }
    }
}


