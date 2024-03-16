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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUserPermissions;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener]
final readonly class ResetBackendUsersPermissionsOnLoginSuccess
{
    public function __construct(
        private Connection $connection,
        private MaintainBackendUserPermissions $maintainBackendUserPermissions,
        private bool $sacevtUserBackendResetPermissionsOnLogin,
    ) {
    }

    /**
     * Clear user properties of the logged in Contao backend user,
     * who inherit rights from one or more group policies.
     * This way we can prevent a policy mess.
     *
     * @throws Exception
     */
    public function __invoke(LoginSuccessEvent $event): void
    {
        if (false === $this->sacevtUserBackendResetPermissionsOnLogin) {
            return;
        }

        $userIdentifier = $event->getUser()->getUserIdentifier();

        if ('contao_backend' === $event->getFirewallName()) {
            $userId = $this->connection
                ->fetchOne(
                    'SELECT id FROM tl_user WHERE username = ? AND admin = ? AND inherit = ?',
                    [
                        $userIdentifier,
                        0,
                        'extend',
                    ],
                )
            ;

            if (false !== $userId) {
                $this->maintainBackendUserPermissions->resetBackendUserPermissions($userIdentifier, [], true);
            }
        }
    }
}
