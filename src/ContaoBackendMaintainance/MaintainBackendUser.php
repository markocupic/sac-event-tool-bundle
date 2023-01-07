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

namespace Markocupic\SacEventToolBundle\ContaoBackendMaintainance;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUserRights;
use Psr\Log\LoggerInterface;

class MaintainBackendUser
{
    private Connection $connection;
    private MaintainBackendUserRights $maintainBackendUserRights;
    private LoggerInterface $contaoGeneralLogger;

    public function __construct(Connection $connection, MaintainBackendUserRights $maintainBackendUserRights, LoggerInterface|null $contaoGeneralLogger)
    {
        $this->connection = $connection;
        $this->maintainBackendUserRights = $maintainBackendUserRights;
        $this->contaoGeneralLogger = $contaoGeneralLogger;
    }

    /**
     * @throws Exception
     */
    public function resetBackendUserRights(): void
    {
        $hasUsers = false;
        $stmt = $this->connection->executeQuery('SELECT username FROM tl_user WHERE admin = ? AND inherit = ?', ['', 'extend']);

        while (false !== ($userIdentifier = $stmt->fetchOne())) {
            $hasUsers = true;
            $this->maintainBackendUserRights->resetBackendUserRights($userIdentifier, [], true);
        }

        if (true === $hasUsers) {
            // Log
            $strText = 'Successfully cleared the user properties of all non-admin backend users.';
            $this->contaoGeneralLogger->info($strText);
        }
    }
}
