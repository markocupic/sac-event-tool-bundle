<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Contao\EventListener;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ParseTemplateListener
 * @package Markocupic\SacEventToolBundle\Contao\EventListener
 */
class ParseTemplateListener
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    /**
     * ParseTemplateListener constructor.
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param ScopeMatcher $scopeMatcher
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function onParseTemplate($objTemplate)
    {
        if ($this->_isFrontend())
        {
            // Check if frontend login is allowed, if not replace the default error message and redirect to account activation page
            if ($objTemplate->getName() === 'mod_login')
            {
                $this->_checkIfFrontenMemberAccountIsActivated($objTemplate);
            }
        }
    }

    /**
     * Check if frontend login is allowed, if not replace the default error message and redirect to account activation page
     * @param $objTemplate
     */
    private function _checkIfFrontenMemberAccountIsActivated($objTemplate)
    {
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        if ($objTemplate->value !== '' && $objTemplate->hasError === true)
        {
            $objMember = $memberModelAdapter->findByUsername($objTemplate->value);
            if ($objMember !== null)
            {
                if (!$objMember->login)
                {
                    // Redirect to account activation page if it is set
                    $objLoginModule = $moduleModelAdapter->findByPk($objTemplate->id);
                    if ($objLoginModule !== null)
                    {
                        if ($objLoginModule->jumpToWhenNotActivated > 0)
                        {
                            $objPage = $pageModelAdapter->findByPk($objLoginModule->jumpToWhenNotActivated);
                            if ($objPage !== null)
                            {
                                // Before redirecting store error message in the session flash bag
                                $session = System::getContainer()->get('session');
                                $flashBag = $session->getFlashBag();
                                $flashBag->set('mod_login', $GLOBALS['TL_LANG']['ERR']['memberAccountNotActivated']);

                                $url = $objPage->getFrontendUrl();
                                $controllerAdapter->redirect($url);
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

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    protected function _isFrontend(): bool
    {
        return $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest());
    }
}
