<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

/**
 * Class tl_content_sac_event_tool
 */
class tl_content_sac_event_tool extends tl_content
{

    /**
     * @param $dc
     */
    public function setPalette(DataContainer $dc)
    {

        if ($dc->id > 0)
        {
            $objDb = $this->Database->prepare("SELECT * FROM tl_content WHERE id=?")->limit(1)->execute($dc->id);
            if ($objDb->numRows)
            {
                // Set palette for contednt element "userPortraitList"
                if ($objDb->type === 'userPortraitList')
                {
                    if ($objDb->userList_selectMode === 'selectUsers')
                    {
                        $GLOBALS['TL_DCA'][$dc->table]['palettes'] = str_replace(',userList_userRoles', '', $GLOBALS['TL_DCA'][$dc->table]['palettes']);
                        $GLOBALS['TL_DCA'][$dc->table]['palettes'] = str_replace(',userList_queryType', '', $GLOBALS['TL_DCA'][$dc->table]['palettes']);
                    }
                    else
                    {
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

        $options = array();
        $objDb = \Database::getInstance()->prepare('SELECT * FROM tl_cabanne_sac')->execute();
        while ($objDb->next())
        {
            $options[$objDb->id] = $objDb->name;
        }

        return $options;

    }

    /**
     * @return array
     */
    public function optionsCallbackUserRoles()
    {

        $options = array();
        $objDb = \Database::getInstance()->prepare('SELECT * FROM tl_user_role ORDER BY sorting ASC')->execute();
        while ($objDb->next())
        {
            $options[$objDb->id] = $objDb->title;
        }

        return $options;

    }
}

