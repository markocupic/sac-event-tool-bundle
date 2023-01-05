<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\User;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUsersHomeDirectory;

class PostLoginListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var MaintainBackendUsersHomeDirectory
     */
    private $maintainBackendUsersHomeDirectory;

    /**
     * PostLoginListener constructor.
     */
    public function __construct(ContaoFramework $framework, MaintainBackendUsersHomeDirectory $maintainBackendUsersHomeDirectory)
    {
        $this->framework = $framework;
        $this->maintainBackendUsersHomeDirectory = $maintainBackendUsersHomeDirectory;
        $this->framework->initialize();
    }

    /**
     * Create user directories if they do not exist
     * and remove them if they are no more used.
     */
    public function onPostLogin(User $user): void
    {
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        if ($user instanceof BackendUser) {
            $userModel = $userModelAdapter->findAll();

            if (null !== $userModel) {
                // Create user directories if they do not exist
                while ($userModel->next()) {
                    $this->maintainBackendUsersHomeDirectory->createBackendUsersHomeDirectory($userModel->current());
                }
            }

            // Scan for unused/old directories and remove them
            $this->maintainBackendUsersHomeDirectory->removeUnusedBackendUsersHomeDirectories();
        }
    }
}
