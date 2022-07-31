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

namespace Markocupic\SacEventToolBundle\ContaoBackendMaintainance;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUserProperties;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class MaintainBackendUser
{
    private ContaoFramework $framework;
    private Connection $connection;
    private MaintainBackendUserProperties $maintainBackendUserProperties;
    private LoggerInterface $logger;

    public function __construct(ContaoFramework $framework, Connection $connection, MaintainBackendUserProperties $maintainBackendUserProperties, LoggerInterface|null $logger)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->maintainBackendUserProperties = $maintainBackendUserProperties;
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     */
    public function clearBackendUserRights(): void
    {
        $stmt = $this->connection->executeQuery('SELECT username FROM tl_user WHERE admin = ? AND inherit = ?', ['', 'extend']);

        while (false !== ($userIdentifier = $stmt->fetchOne())) {
            $this->maintainBackendUserProperties->clearBackendUserRights($userIdentifier, ['filemounts']);

            // Log
            $strText = 'Successfully cleared the user properties of all non-admin backend users.';
            $this->logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]);
        }
    }
}
