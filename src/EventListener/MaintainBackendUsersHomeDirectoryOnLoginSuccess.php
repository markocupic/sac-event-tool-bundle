<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUsersHomeDirectory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener]
final readonly class MaintainBackendUsersHomeDirectoryOnLoginSuccess
{
    public function __construct(
        private ContaoFramework $framework,
        private MaintainBackendUsersHomeDirectory $maintainBackendUsersHomeDirectory,
    ) {
    }

    /**
     * Create user directories if they do not exist
     * and
     * Scan for unused/old directories and remove them.
     *
     * @throws \Exception
     */
    public function __invoke(LoginSuccessEvent $event): void
    {
        $this->maintainBackendUsersHomeDirectory($event);
    }

    /**
     * @throws \Exception
     */
    public function maintainBackendUsersHomeDirectory(LoginSuccessEvent $event): void
    {
        $token = $event->getAuthenticatedToken();

        $user = $token->getUser();

        if (!$user instanceof BackendUser) {
            return;
        }

        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        $userModel = $userModelAdapter->findAll();

        if (null !== $userModel) {
            // Create user directories if they do not exist
            while ($userModel->next()) {
                $this->maintainBackendUsersHomeDirectory->createBackendUsersHomeDirectory($userModel->current());
            }
        }

        // Scan for unused/old directories and remove them
        $this->maintainBackendUsersHomeDirectory->removeUnusedBackendUserHomeDirectories();
    }
}
