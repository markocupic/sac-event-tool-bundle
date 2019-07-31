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
use Contao\Controller;

use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\PageModel;

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

        // {{member_avatar::###pictureSizeID###}}
        // Return picture of logged in member
        if (strpos($strTag, 'member_avatar') !== false)
        {
            $elements = explode('::', $strTag);
            if (is_array($elements) && count($elements) > 1)
            {
                $size = $elements[1];
                if (FE_USER_LOGGED_IN)
                {
                    $objUser = MemberModel::findByPk(FrontendUser::getInstance()->id);
                    if ($objUser !== null)
                    {
                        $strUrl = getAvatar($objUser->id, 'FE');
                        $strInsertTag = sprintf('{{picture::%s?size=%s&alt=&s}}', $strUrl, $size, $objUser->firstname . ' ' . $objUser->lastname);
                        return Controller::replaceInsertTags($strInsertTag);
                    }
                }
            }
        }

        // Redirect to an internal page
        // {{redirect::###pageIdOrAlias###::###params###}}
        // {{redirect::konto-aktivieren}}
        // {{redirect::some-page-alias::?foo=bar&var=bla}}
        if (strpos($strTag, 'redirect') !== false)
        {
            $elements = explode('::', $strTag);
            if (is_array($elements) && count($elements) > 1)
            {
                $params = '';
                if (isset($elements[2]))
                {
                    $params = $elements[2];
                }
                $objPage = PageModel::findByIdOrAlias($elements[1]);
                if ($objPage !== null)
                {
                    $strLocation = sprintf('%s%s', $objPage->getFrontendUrl(), $params);
                    Controller::redirect($strLocation);
                }
            }
        }

        return false;
    }

}


