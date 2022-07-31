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

namespace Markocupic\SacEventToolBundle\Subscriber;

use Contao\BackendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUserRights;
use Markocupic\SwissAlpineClubContaoLoginClientBundle\Event\PreInteractiveLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PreSingleSignOnLoginSubscriber implements EventSubscriberInterface
{
    private Connection $connection;
    private MaintainBackendUserRights $maintainBackendUserRights;
    private bool $resetUserRightOnSsoLogin;

    public function __construct(Connection $connection, MaintainBackendUserRights $maintainBackendUserRights, bool $resetUserRightOnSsoLogin)
    {
        $this->connection = $connection;
        $this->maintainBackendUserProperties = $maintainBackendUserRights;
        $this->resetUserRightOnSsoLogin = $resetUserRightOnSsoLogin;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreInteractiveLoginEvent::NAME => ['resetBackendUserRights', 100],
        ];
    }

    /**
     * Clear user properties of the logged in Contao backend user, who inherit rights from one or more group policies.
     * This way we can prevent a policy mess.
     *
     * @throws Exception
     */
    public function resetBackendUserRights(PreInteractiveLoginEvent $event): void
    {
        if (false === $this->resetUserRightOnSsoLogin) {
            return;
        }

        $userIdentifier = $event->getUserIdentifier();

        if (BackendUser::class === $event->getUserClass()) {
            $arrUserData = $this->connection
                ->fetchAssociative(
                    'SELECT * FROM tl_user WHERE username = ? AND admin = ? AND inherit = ?',
                    [$userIdentifier, '', 'extend'],
                )
            ;

            if (false !== $arrUserData) {
                $this->maintainBackendUserProperties->resetBackendUserRights($userIdentifier, [], true);
            }
        }
    }
}
