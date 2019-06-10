<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Controller;
use Contao\System;

/**
 * Class ParseTemplate
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class ParseTemplate
{
    /**
     * @param $objTemplate
     */
    public function checkIfAccountIsActivated($objTemplate)
    {
        // Check if login is allowed, if not replace the default error message
        if (TL_MODE === 'FE')
        {
            if ($objTemplate->getName() === 'mod_login')
            {
                if ($objTemplate->value !== '' && $objTemplate->hasError === true)
                {
                    $objMember = MemberModel::findByUsername($objTemplate->value);
                    if ($objMember !== null)
                    {
                        if (!$objMember->login)
                        {
                            // Redirect to account activation page if it is set
                            $objLoginModule = ModuleModel::findByPk($objTemplate->id);
                            if ($objLoginModule !== null)
                            {
                                if ($objLoginModule->jumpToWhenNotActivated > 0)
                                {
                                    $objPage = PageModel::findByPk($objLoginModule->jumpToWhenNotActivated);
                                    if ($objPage !== null)
                                    {
                                        // Before redirecting store error message in the session flash bag
                                        $session = System::getContainer()->get('session');
                                        $flashBag = $session->getFlashBag();
                                        $flashBag->set('mod_login', $GLOBALS['TL_LANG']['ERR']['memberAccountNotActivated']);

                                        $url = $objPage->getFrontendUrl();
                                        Controller::redirect($url);
                                    }
                                }
                            }
                            $objTemplate->message = $GLOBALS['TL_LANG']['ERR']['memberAccountNotActivated'];
                        }
                    }
                    else
                    {
                        $objTemplate->message = sprintf($GLOBALS['TL_LANG']['ERR']['memberAccountNotFound'], $objTemplate->value);
                    }
                }
            }
        }
    }
}
