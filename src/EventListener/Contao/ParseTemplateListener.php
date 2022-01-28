<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ParseTemplateListener.
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
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * @param $objTemplate
     */
    public function onParseTemplate($objTemplate): void
    {
        if ($this->_isFrontend()) {
            // Check if frontend login is allowed, if not replace the default error message and redirect to account activation page
            if ('mod_login' === $objTemplate->getName()) {
                $this->_checkIfFrontenMemberAccountIsActivated($objTemplate);
            }
        }
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request.
     */
    protected function _isFrontend(): bool
    {
        return null !== $this->requestStack->getCurrentRequest() ? $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest()) : false;
    }

    /**
     * Check if frontend login is allowed, if not replace the default error message and redirect to account activation page.
     *
     * @param $objTemplate
     */
    private function _checkIfFrontenMemberAccountIsActivated($objTemplate): void
    {
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        if ('' !== $objTemplate->value && true === $objTemplate->hasError) {
            $objMember = $memberModelAdapter->findByUsername($objTemplate->value);

            if (null !== $objMember) {
                if (!$objMember->login) {
                    // Redirect to account activation page if it is set
                    $objLoginModule = $moduleModelAdapter->findByPk($objTemplate->id);

                    if (null !== $objLoginModule) {
                        if ($objLoginModule->jumpToWhenNotActivated > 0) {
                            $objPage = $pageModelAdapter->findByPk($objLoginModule->jumpToWhenNotActivated);

                            if (null !== $objPage) {
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
            } else {
                $objTemplate->message = sprintf($GLOBALS['TL_LANG']['ERR']['memberAccountNotFound'], $objTemplate->value);
            }
        }
    }
}
