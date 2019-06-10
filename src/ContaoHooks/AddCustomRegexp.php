<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\MemberModel;
use Contao\UserModel;
use Contao\Widget;

/**
 * Class AddCustomRegexp
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class AddCustomRegexp
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
     * @param $strRegexp
     * @param $varValue
     * @param Widget $objWidget
     * @return bool
     */
    public function addCustomRegexp($strRegexp, $varValue, Widget $objWidget)
    {
        // Check for a valid/existent sacMemberId
        if ($strRegexp === 'sacMemberId')
        {
            if (trim($varValue) !== '')
            {
                $objMemberModel = MemberModel::findBySacMemberId(trim($varValue));
                if ($objMemberModel === null)
                {
                    $objWidget->addError('Field ' . $objWidget->label . ' should be a valid sac member id.');
                }
            }

            return true;
        }

        // Check for a valid/existent sacMemberId
        if ($strRegexp === 'sacMemberIdIsUniqueAndValid')
        {
            if (!is_numeric($varValue))
            {
                $objWidget->addError('Sac member id must be number >= 0');
            }
            elseif (trim($varValue) !== '' && $varValue > 0)
            {
                $objMemberModel = MemberModel::findBySacMemberId(trim($varValue));
                if ($objMemberModel === null)
                {
                    $objWidget->addError('Field ' . $objWidget->label . ' should be a valid sac member id.');
                }

                $objUser = Database::getInstance()->prepare('SELECT * FROM tl_user WHERE sacMemberId=?')->execute($varValue);
                if ($objUser->numRows > 1)
                {
                    $objWidget->addError('Sac member id ' . $varValue . ' is already in use.');
                }
            }

            return true;
        }

        return false;
    }

}


