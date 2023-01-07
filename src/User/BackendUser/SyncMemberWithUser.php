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

namespace Markocupic\SacEventToolBundle\User\BackendUser;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Markocupic\SacEventToolBundle\Config\Log;
use Psr\Log\LoggerInterface;

/**
 * Sync tl_member with tl_user.
 */
class SyncMemberWithUser
{
    private ContaoFramework $framework;
    private LoggerInterface|null $contaoGeneralLogger;

    public function __construct(ContaoFramework $framework, LoggerInterface $contaoGeneralLogger = null)
    {
        $this->framework = $framework;
        $this->contaoGeneralLogger = $contaoGeneralLogger;
    }

    public function syncMemberWithUser(): void
    {
        $this->framework->initialize();

        $objUser = Database::getInstance()
            ->prepare('SELECT * FROM tl_user WHERE sacMemberId > ?')
            ->execute(0)
        ;

        while ($objUser->next()) {
            $objMember = Database::getInstance()
                ->prepare('SELECT * FROM tl_member WHERE sacMemberId=?')
                ->limit(1)
                ->execute($objUser->sacMemberId)
            ;

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

                $objUpdateStmt = Database::getInstance()
                    ->prepare('UPDATE tl_user %s WHERE id=?')
                    ->set($set)
                    ->execute($objUser->id)
                ;

                if ($objUpdateStmt->affectedRows) {
                    $msg = sprintf(
                        'Synced tl_user with tl_member. Updated tl_user (%s %s [SAC Member-ID: %s]).',
                        $objMember->firstname,
                        $objMember->lastname,
                        $objMember->sacMemberId
                    );

                    $this->contaoGeneralLogger?->info(
                        $msg,
                        ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_WITH_USER_SYNC_SUCCESS)]
                    );
                }
            } else {
                Database::getInstance()
                    ->prepare('UPDATE tl_user SET sacMemberId = ? WHERE id = ?')
                    ->execute(0, $objUser->id)
                ;
            }
        }
    }
}
