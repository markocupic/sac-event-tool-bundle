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

namespace Markocupic\SacEventToolBundle\ContaoBackendMaintenance;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUserPermissions;
use Psr\Log\LoggerInterface;

class MaintainBackendUser
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MaintainBackendUserPermissions $maintainBackendUserPermissions,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * @throws Exception
     */
    public function resetBackendUserPermissions(): void
    {
        $intProcessed = 0;

        $arrUsers = $this->connection
            ->fetchAllAssociative(
                'SELECT username FROM tl_user WHERE admin = ? AND inherit = ?',
                [
                    false,
                    'extend',
                ],
                [
                    Types::BOOLEAN,
                    Types::STRING,
                ]
            )
        ;

        foreach ($arrUsers as $user) {
            $userIdentifier = $user['username'];

            if (empty($userIdentifier)) {
                continue;
            }

            ++$intProcessed;
            $this->maintainBackendUserPermissions->resetBackendUserPermissions($userIdentifier, [], true);
        }

        if (null !== $this->contaoGeneralLogger && $intProcessed) {
            $strText = 'Successfully reset backend permissions of all non-admin backend users.';
            $this->contaoGeneralLogger->info($strText);
        }
    }
}
