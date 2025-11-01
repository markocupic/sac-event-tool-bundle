<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Twig\Extension;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\MemberModel;
use Contao\UserModel;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigLoggedInFrontendUserManager extends AbstractExtension
{
    private Adapter $memberAdapter;

    private Adapter $userAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TokenChecker $tokenChecker,
    ) {
        $this->framework->initialize();
        $this->memberAdapter = $this->framework->getAdapter(MemberModel::class);
        $this->userAdapter = $this->framework->getAdapter(UserModel::class);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_logged_in_frontend_user', [$this, 'hasLoggedInFrontendUser']),
            new TwigFunction('get_logged_in_frontend_user', [$this, 'getLoggedInFrontendUser']),
            new TwigFunction('has_backend_account', [$this, 'hasBackendAccount']),
            new TwigFunction('current_user_backend_account', [$this, 'getCurrentUserBackendAccount']),
        ];
    }

    /**
     * Returns true if a Contao frontend member is logged in.
     *
     * Inside your Twig template: {% if has_logged_in_frontend_user() is sames as true
     * %}Frontend user logged in{% endif %}
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function hasLoggedInFrontendUser(): bool
    {
        return $this->tokenChecker->hasFrontendUser();
    }

    /**
     * Returns the logged in Contao member (\Contao\MemberModel) if there is a logged
     * in Contao frontend user or null if there is no logged in Contao frontend user.
     *
     * Inside your Twig template: {% set user = get_logged_in_frontend_user() %} Hi,
     * my name is {{ user.firstname }}
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function getLoggedInFrontendUser(): MemberModel|null
    {
        if ($this->tokenChecker->hasFrontendUser()) {
            if (null !== ($model = $this->memberAdapter->findByUsername($this->tokenChecker->getFrontendUsername()))) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Checks if the current user has an associated backend account.
     *
     *  Inside your Twig template: {% if has_backend_account %}<a href="/contao">Backend</a>{% endif %}
     *
     * @return bool true if a backend account exists, false otherwise
     */
    public function hasBackendAccount(): bool
    {
        return null !== $this->getCurrentUserBackendAccount();
    }

    /**
     * Retrieves the backend account of the currently logged-in frontend user, if any.
     *
     * This method checks whether there is a frontend user logged in. If a frontend
     * user exists, it attempts to find the corresponding backend user account
     * associated with the frontend user's ID. Additional checks are performed to
     * ensure the backend account is active and not disabled.
     *
     * Returns null if no frontend user is logged in, no corresponding backend user is
     * found, or the backend user is disabled.
     *
     * Inside your Twig template: {% set backend_user = current_user_backend_account() %}
     *
     * @return UserModel|null the backend user account model or null if no valid account is found
     */
    public function getCurrentUserBackendAccount(): UserModel|null
    {
        if (!$this->tokenChecker->hasFrontendUser()) {
            return null;
        }

        $frontendUser = $this->getLoggedInFrontendUser();

        if (null === $frontendUser) {
            return null;
        }

        $backendUser = $this->userAdapter->findOneBySacMemberId($frontendUser->username);

        if (null === $backendUser) {
            return null;
        }

        $disabled = ('' !== $backendUser->start && $backendUser->start > time()) || ('' !== $backendUser->stop && $backendUser->stop <= time());

        if ($disabled || $backendUser->disable) {
            return null;
        }

        return $backendUser;
    }
}
