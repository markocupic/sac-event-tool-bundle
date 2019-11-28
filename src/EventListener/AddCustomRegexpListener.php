<?php
declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\MemberModel;
use Contao\Widget;

/**
 * Class AddCustomRegexpListener
 * @package Markocupic\SacEventToolBundle\EventListener
 */
class AddCustomRegexpListener
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
    public function onAddCustomRegexp($strRegexp, $varValue, Widget $objWidget): bool
    {
        // Set adapters
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        // Check for a valid/existent sacMemberId
        if ($strRegexp === 'sacMemberId')
        {
            if (trim($varValue) !== '')
            {
                $objMemberModel = $memberModelAdapter->findBySacMemberId(trim($varValue));
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
                $objMemberModel = $memberModelAdapter->findBySacMemberId(trim($varValue));
                if ($objMemberModel === null)
                {
                    $objWidget->addError('Field ' . $objWidget->label . ' should be a valid sac member id.');
                }

                $objUser = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_user WHERE sacMemberId=?')->execute($varValue);
                if ($objUser->numRows > 1)
                {
                    $objWidget->addError('SAC member id ' . $varValue . ' is already in use.');
                }
            }

            return true;
        }

        return false;
    }

}


