<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\PageModel;

/**
 * Class ReplaceInsertTagsListener
 * @package Markocupic\SacEventToolBundle\EventListener
 */
class ReplaceInsertTagsListener
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * ReplaceInsertTagsListener constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * @param $strTag
     * @return bool|string
     */
    public function onReplaceInsertTags($strTag)
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $frontendUserAdapter = $this->framework->getAdapter(FrontendUser::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        // Trim whitespaces
        $strTag = $strTag != '' ? trim($strTag) : $strTag;

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
                    $label = $elements[2];
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
                    $objUser = $memberModelAdapter->findByPk($frontendUserAdapter->getInstance()->id);
                    if ($objUser !== null)
                    {
                        $strUrl = getAvatar($objUser->id, 'FE');
                        $strInsertTag = sprintf('{{picture::%s?size=%s&alt=&s}}', $strUrl, $size, $objUser->firstname . ' ' . $objUser->lastname);
                        return $controllerAdapter->replaceInsertTags($strInsertTag);
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
                $objPage = $pageModelAdapter->findByIdOrAlias($elements[1]);
                if ($objPage !== null)
                {
                    $strLocation = sprintf('%s%s', $objPage->getFrontendUrl(), $params);
                    $controllerAdapter->redirect($strLocation);
                }
            }
        }

        return false;
    }

}


