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

namespace Markocupic\SacEventToolBundle\Feature\BackendUserPermissionReset\EventListener;

use Contao\BackendUser;
use Markocupic\SacEventToolBundle\Feature\BackendUserPermissionReset\BackendUserPermissionReset;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Resets the permissions of the backend user who just logged in,
 * if enabled (sacevt.user.backend.reset_permissions_on_login).
 *
 * Runs after Feature\BackendUserHomeDirectory\EventListener\CreateHomeDirectoryOnLoginListener (priority 10),
 * so the home directory exists and is added as file mount.
 */
#[AsEventListener(priority: 0)]
final readonly class ResetPermissionsOnLoginListener
{
    public function __construct(
        private BackendUserPermissionReset $backendUserPermissionReset,
        private bool $sacevtUserBackendResetPermissionsOnLogin,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        if (!$this->sacevtUserBackendResetPermissionsOnLogin) {
            return;
        }

        $user = $event->getAuthenticatedToken()->getUser();

        if (!$user instanceof BackendUser) {
            return;
        }

        $username = $user->getUserIdentifier();

        if ($this->backendUserPermissionReset->isResettable($username)) {
            $this->backendUserPermissionReset->resetUser($username);
        }
    }
}
