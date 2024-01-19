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

namespace Markocupic\SacEventToolBundle\ContaoBackendMaintenance;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
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
        $hasUsers = false;
        $stmt = $this->connection
            ->executeQuery(
                'SELECT username FROM tl_user WHERE username IS NOT NULL AND admin = ? AND inherit = ?',
                [
                    '',
                    'extend',
                ]
            )
        ;

        while (false !== ($userIdentifier = $stmt->fetchOne())) {
            $hasUsers = true;
            $this->maintainBackendUserPermissions->resetBackendUserPermissions($userIdentifier, [], true);
        }

        if ($this->contaoGeneralLogger && true === $hasUsers) {
            $strText = 'Successfully reset backend permissions of all non-admin users.';
            $this->contaoGeneralLogger->info($strText);
        }
    }
}
