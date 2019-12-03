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
use Contao\Input;
use Contao\UserModel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ImportUserListener
 * @package Markocupic\SacEventToolBundle\EventListener
 */
class ImportUserListener
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
     * ImportUserListener constructor.
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
    }

    /**
     * Allow backend users to authenticate with their sacMemberId
     * @param $strUsername
     * @param $strPassword
     * @param $strTable
     * @return bool
     */
    public function onImportUser($strUsername, $strPassword, $strTable): bool
    {
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $request = $this->requestStack->getCurrentRequest();
        if ($strTable === 'tl_user')
        {
            if (trim($strUsername) !== '' && is_numeric($strUsername))
            {
                $objUser = $userModelAdapter->findBySacMemberId($strUsername);
                if ($objUser !== null)
                {
                    if ($objUser->sacMemberId > 0 && $objUser->sacMemberId === $strUsername)
                    {
                        // Used for password recovery
                        /** @var Request $request */
                        $request->request->set('username', $objUser->username);

                        // Used for backend login
                        $inputAdapter->setPost('username', $objUser->username);
                        return true;
                    }
                }
            }
        }

        return false;
    }

}
