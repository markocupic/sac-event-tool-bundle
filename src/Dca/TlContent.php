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

use Contao\Database;
use Contao\DataContainer;

/**
 * Class TlContent.
 */
class TlContent extends \tl_content
{

    /**
     * @param $dc
     */
    public function setPalette(DataContainer $dc): void
    {
        if ($dc->id > 0) {
            $objDb = Database::getInstance()
                ->prepare('SELECT * FROM tl_content WHERE id=?')
                ->limit(1)
                ->execute($dc->id)
            ;

            if ($objDb->numRows) {
                // Set palette for contednt element "user_portrait_list"
                if ('user_portrait_list' === $objDb->type) {
                    if ('selectUsers' === $objDb->userList_selectMode) {
                        $GLOBALS['TL_DCA'][$dc->table]['palettes'] = str_replace(',userList_userRoles', '', $GLOBALS['TL_DCA'][$dc->table]['palettes']);
                        $GLOBALS['TL_DCA'][$dc->table]['palettes'] = str_replace(',userList_queryType', '', $GLOBALS['TL_DCA'][$dc->table]['palettes']);
                    } else {
                        $GLOBALS['TL_DCA'][$dc->table]['palettes'] = str_replace(',userList_users', '', $GLOBALS['TL_DCA'][$dc->table]['palettes']);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getCabannes()
    {
        $options = [];
        $objDb = Database::getInstance()
            ->prepare('SELECT * FROM tl_cabanne_sac')
            ->execute()
        ;

        while ($objDb->next()) {
            $options[$objDb->id] = $objDb->name;
        }

        return $options;
    }

    /**
     * @return array
     */
    public function optionsCallbackUserRoles()
    {
        $options = [];
        $objDb = Database::getInstance()
            ->prepare('SELECT * FROM tl_user_role ORDER BY sorting ASC')
            ->execute()
        ;

        while ($objDb->next()) {
            $options[$objDb->id] = $objDb->title;
        }

        return $options;
    }

    /**
     * Return all user portrait list templates as array.
     *
     * @return array
     */
    public function getUserListTemplates()
    {
        return $this->getTemplateGroup('ce_user_portrait_list');
    }

    /**
     * Return all user portrait list partial templates as array.
     *
     * @return array
     */
    public function getUserListPartialTemplates()
    {
        return $this->getTemplateGroup('user_portrait_list_partial_');
    }
}
