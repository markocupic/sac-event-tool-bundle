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
use Contao\Input;
use Contao\UserModel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ImportUserListener.
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
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
    }

    /**
     * Allow backend users to authenticate with their sacMemberId.
     *
     * @param $strUsername
     * @param $strPassword
     * @param $strTable
     */
    public function onImportUser($strUsername, $strPassword, $strTable): bool
    {
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $request = $this->requestStack->getCurrentRequest();

        if ('tl_user' === $strTable) {
            if ('' !== trim($strUsername) && is_numeric($strUsername)) {
                $objUser = $userModelAdapter->findOneBySacMemberId($strUsername);

                if (null !== $objUser) {
                    if ($objUser->sacMemberId > 0 && $objUser->sacMemberId === $strUsername) {
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
