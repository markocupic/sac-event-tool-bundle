<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\User\BackendUser;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class SyncMemberWithUser
 * @package Markocupic\SacEventToolBundle\User\BackendUser
 */
class SyncMemberWithUser
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var ?LoggerInterface
     */
    private $logger;

    /**
     * Log type for sync process
     */
    const SAC_EVT_LOG_SYNC_MEMBER_WITH_USER = 'SYNC_MEMBER_WITH_USER';

    /**
     * SyncMemberWithUser constructor.
     * @param ContaoFramework $framework
     * @param null|LoggerInterface $logger
     */
    public function __construct(ContaoFramework $framework, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;

        $this->logger = $logger;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Sync tl_member with tl_user
     */
    public function syncMemberWithUser()
    {
        // Sync tl_user with tl_member
        $objUser = Database::getInstance()->prepare('SELECT * FROM tl_user WHERE sacMemberId>?')->execute(0);
        while ($objUser->next())
        {
            $count = 0;
            $objSAC = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE sacMemberId=?')->limit(1)->execute($objUser->sacMemberId);
            if ($objSAC->numRows)
            {
                $set = [
                    'firstname'   => $objSAC->firstname,
                    'lastname'    => $objSAC->lastname,
                    'sectionId'   => $objSAC->sectionId,
                    'dateOfBirth' => $objSAC->dateOfBirth,
                    'email'       => $objSAC->email != '' ? $objSAC->email : 'invalid_' . $objUser->username . '_' . $objUser->sacMemberId . '@noemail.ch',
                    'street'      => $objSAC->street,
                    'postal'      => $objSAC->postal,
                    'city'        => $objSAC->city,
                    'country'     => $objSAC->country,
                    'gender'      => $objSAC->gender,
                    'phone'       => $objSAC->phone,
                    'mobile'      => $objSAC->mobile,
                ];
                Database::getInstance()->prepare('UPDATE tl_user %s WHERE id=?')->set($set)->execute($objUser->id);
                $count++;
            }
            else
            {
                Database::getInstance()->prepare('UPDATE tl_user SET sacMemberId=? WHERE id=?')->execute(0, $objUser->id);
            }

            // Log
            $msg = \sprintf('Synced tl_user with tl_member. %s entries/rows affected.', $count);
            $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_SYNC_MEMBER_WITH_USER);
        }
    }

    /**
     * @param string $strLogLevel
     * @param string $strText
     * @param string $strMethod
     * @param string $strCategory
     */
    private function log(string $strLogLevel, string $strText, string $strMethod, string $strCategory): void
    {
        if ($this->logger !== null)
        {
            $this->logger->log(
                $strLogLevel,
                $strText,
                ['contao' => new ContaoContext($strMethod, $strCategory)]
            );
        }
    }

}
