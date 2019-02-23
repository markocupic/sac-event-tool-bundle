<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\Config;

/**
 * Class ReplaceInsertTags
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class ReplaceInsertTags
{

    /**
     * @param $strTag
     * @return bool|string
     */
    public function replaceInsertTags($strTag)
    {
        // Replace external link
        // {{external_link::http://google.ch::more}}
        if (strpos($strTag, 'external_link') !== false)
        {

            $elements = explode('::', $strTag);
            if (is_array($elements) && count($elements) > 1)
            {
                $href = $elements[1];
                $label = $href;
                if (isset($elements[2]) && $elements[2] != '')
                {
                    if (trim($elements) != '')
                    {
                        $label = $elements[2];
                    }
                }
                return sprintf('<a href="%s" target="_blank">%s</a>', $href, $label);
            }
        }




        return false;
    }

}


