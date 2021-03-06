<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\User\BackendUser;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class SyncMemberWithUser.
 */
class SyncMemberWithUser
{
    /**
     * Log type for sync process.
     */
    const SAC_EVT_LOG_SYNC_MEMBER_WITH_USER = 'SYNC_MEMBER_WITH_USER';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var ?LoggerInterface
     */
    private $logger;

    /**
     * SyncMemberWithUser constructor.
     */
    public function __construct(ContaoFramework $framework, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;

        $this->logger = $logger;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Sync tl_member with tl_user.
     */
    public function syncMemberWithUser(): void
    {
        $objUser = Database::getInstance()->prepare('SELECT * FROM tl_user WHERE sacMemberId>?')->execute(0);

        while ($objUser->next()) {
            $objMember = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE sacMemberId=?')->limit(1)->execute($objUser->sacMemberId);

            if ($objMember->numRows) {
                $set = [
                    'firstname' => $objMember->firstname,
                    'lastname' => $objMember->lastname,
                    'sectionId' => $objMember->sectionId,
                    'dateOfBirth' => $objMember->dateOfBirth,
                    'email' => '' !== $objMember->email ? $objMember->email : 'invalid_'.$objUser->username.'_'.$objUser->sacMemberId.'@noemail.ch',
                    'street' => $objMember->street,
                    'postal' => $objMember->postal,
                    'city' => $objMember->city,
                    'country' => $objMember->country,
                    'gender' => $objMember->gender,
                    'phone' => $objMember->phone,
                    'mobile' => $objMember->mobile,
                ];
                $objUpdateStmt = Database::getInstance()->prepare('UPDATE tl_user %s WHERE id=?')->set($set)->execute($objUser->id);

                if ($objUpdateStmt->affectedRows) {
                    // Log
                    $msg = sprintf('Synced tl_user with tl_member. Updated tl_user (%s %s [SAC Member-ID: %s]).', $objMember->firstname, $objMember->lastname, $objMember->sacMemberId);
                    $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_SYNC_MEMBER_WITH_USER);
                }
            } else {
                Database::getInstance()->prepare('UPDATE tl_user SET sacMemberId=? WHERE id=?')->execute(0, $objUser->id);
            }
        }
    }

    private function log(string $strLogLevel, string $strText, string $strMethod, string $strCategory): void
    {
        if (null !== $this->logger) {
            $this->logger->log(
                $strLogLevel,
                $strText,
                ['contao' => new ContaoContext($strMethod, $strCategory)]
            );
        }
    }
}
