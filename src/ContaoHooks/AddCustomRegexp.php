<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\MemberModel;
use Contao\Widget;


/**
 * Class AddCustomRegexp
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class AddCustomRegexp
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;


    /**
     * Constructor.
     *
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
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

        return false;
    }


}


