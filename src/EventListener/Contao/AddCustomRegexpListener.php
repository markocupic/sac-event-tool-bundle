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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\MemberModel;
use Contao\Widget;

/**
 * Class AddCustomRegexpListener.
 */
class AddCustomRegexpListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * Constructor.
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * @param $strRegexp
     * @param $varValue
     */
    public function onAddCustomRegexp($strRegexp, $varValue, Widget $objWidget): bool
    {
        // Set adapters
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        // Check for a valid/existent sacMemberId
        if ('sacMemberId' === $strRegexp) {
            if ('' !== trim($varValue)) {
                $objMemberModel = $memberModelAdapter->findOneBySacMemberId(trim($varValue));

                if (null === $objMemberModel) {
                    $objWidget->addError('Field '.$objWidget->label.' should be a valid sac member id.');
                }
            }

            return true;
        }

        // Check for a valid/existent sacMemberId
        if ('sacMemberIdIsUniqueAndValid' === $strRegexp) {
            if (!is_numeric($varValue)) {
                $objWidget->addError('Sac member id must be number >= 0');
            } elseif ('' !== trim($varValue) && $varValue > 0) {
                $objMemberModel = $memberModelAdapter->findOneBySacMemberId(trim($varValue));

                if (null === $objMemberModel) {
                    $objWidget->addError('Field '.$objWidget->label.' should be a valid sac member id.');
                }

                $objUser = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_user WHERE sacMemberId=?')->execute($varValue);

                if ($objUser->numRows > 1) {
                    $objWidget->addError('SAC member id '.$varValue.' is already in use.');
                }
            }

            return true;
        }

        return false;
    }
}
