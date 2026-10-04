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

namespace Markocupic\SacEventToolBundle\Feature\BackendUserHomeDirectory\EventListener;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Feature\BackendUserHomeDirectory\BackendUserHomeDirectory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Creates the home directory of the backend user who just logged in (only this user).
 * All other users are handled by the daily cron (Cron\BackendUserHomeDirectoryCron).
 *
 * Priority 10: runs before Feature\BackendUserPermissionReset\EventListener\ResetPermissionsOnLoginListener,
 * which adds the home directory as file mount.
 */
#[AsEventListener(priority: 10)]
final readonly class CreateHomeDirectoryOnLoginListener
{
    public function __construct(
        private ContaoFramework $framework,
        private BackendUserHomeDirectory $backendUserHomeDirectory,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getAuthenticatedToken()->getUser();

        if (!$user instanceof BackendUser) {
            return;
        }

        $userModel = $this->framework->getAdapter(UserModel::class)->findByUsername($user->getUserIdentifier());

        if (null !== $userModel) {
            $this->backendUserHomeDirectory->create($userModel);
        }
    }
}
