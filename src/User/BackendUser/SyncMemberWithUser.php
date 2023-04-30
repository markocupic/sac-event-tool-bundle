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

use Contao\CoreBundle\Monolog\ContaoContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Config\Log;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Mirror/Update tl_user from tl_member
 * Unidirectional sync tl_member -> tl_user.
 */
class SyncMemberWithUser
{
    private array $syncLog = [
        'log' => [],
        'processed' => 0,
        'updates' => 0,
        'duration' => 0,
        'with_error' => false,
        'exception' => '',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * @throws Exception
     */
    public function syncMemberWithUser(): void
    {
        $stopWatchEvent = (new Stopwatch())->start('SYNC_MEMBER_WITH_USER');
        $this->resetSyncLog();

        $this->connection->beginTransaction();

        try {
            $arrUsers = $this->connection->fetchAllAssociative(
                'SELECT * FROM tl_user WHERE sacMemberId > ?',
                [0],
            );

            foreach ($arrUsers as $arrUser) {
                ++$this->syncLog['processed'];

                $arrMember = $this->connection->fetchAssociative(
                    'SELECT * FROM tl_member WHERE sacMemberId = ?',
                    [$arrUser['sacMemberId']],
                );

                if (false !== $arrMember) {
                    $set = [
                        'firstname' => (string) $arrMember['firstname'],
                        'lastname' => (string) $arrMember['lastname'],
                        'name' => $arrMember['lastname'].' '.$arrMember['firstname'],
                        'sectionId' => $arrMember['sectionId'],
                        'dateOfBirth' => (int) $arrMember['dateOfBirth'],
                        'email' => '' !== $arrMember['email'] ? (string) $arrMember['email'] : 'invalid_'.$arrUser['username'].'_'.$arrUser['sacMemberId'].'@noemail.ch',
                        'street' => (string) $arrMember['street'],
                        'postal' => (string) $arrMember['postal'],
                        'city' => (string) $arrMember['city'],
                        'country' => (string) $arrMember['country'],
                        'gender' => (string) $arrMember['gender'],
                        'phone' => (string) $arrMember['phone'],
                        'mobile' => (string) $arrMember['mobile'],
                    ];

                    if ($this->connection->update('tl_user', $set, ['id' => $arrUser['id']])) {
                        $msg = sprintf(
                            'Synced tl_user with tl_member. Updated tl_user (%s %s [SAC Member-ID: %s]).',
                            $arrMember['firstname'],
                            $arrMember['lastname'],
                            $arrMember['sacMemberId'],
                        );

                        $this->contaoGeneralLogger?->info(
                            $msg,
                            ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_WITH_USER_SYNC_SUCCESS)]
                        );
                        ++$this->syncLog['updates'];
                        $this->syncLog['log'][] = $msg;
                    }
                } else {
                    $set = [
                        'sacMemberId' => 0,
                        'tstamp' => time(),
                    ];

                    if ($this->connection->update('tl_user', $set, ['id' => $arrUser['id']])) {
                        $msg = sprintf(
                            'Updated "%s". Set tl_user.sacMemberId to "0" after syncing tl_member with tl_user. "%s" no longer seems to be a club member.',
                            $arrUser['name'],
                            $arrUser['name'],
                        );

                        $this->contaoGeneralLogger?->info(
                            $msg,
                            ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_WITH_USER_SYNC_SUCCESS)]
                        );

                        $this->syncLog['log'][] = $msg;
                    }
                }
            }
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();

            $this->syncLog['with_error'] = true;
            $this->syncLog['exception'] = $e->getMessage();
        }

        $this->syncLog['duration'] = round($stopWatchEvent->stop()->getDuration() / 1000, 3);
    }

    public function getSyncLog(): array
    {
        return $this->syncLog;
    }

    private function resetSyncLog(): void
    {
        // Reset sync log
        $this->syncLog = [
            'log' => [],
            'processed' => 0,
            'updates' => 0,
            'duration' => 0,
            'with_error' => false,
            'exception' => '',
        ];
    }
}
