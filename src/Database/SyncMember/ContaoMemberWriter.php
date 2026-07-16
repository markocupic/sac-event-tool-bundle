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

namespace Markocupic\SacEventToolBundle\Database\SyncMember;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

/**
 * Writes the synchronized member data into the Contao "tl_member" table.
 *
 * All operations use the injected connection, so they run inside the transaction
 * that the caller (SyncMemberDatabase) has opened.
 */
final class ContaoMemberWriter
{
    private const int DISABLE_THRESHOLD_PERCENT = 7;

    public function __construct(
        private readonly Connection $connection,
        private readonly PasswordHasherFactory $passwordHasherFactory,
    ) {
    }

    /**
     * Inserts new and updates existing members from the temporary table into tl_member.
     */
    public function syncFromTempTable(SyncLogger $syncLogger): void
    {
        $result = $this->connection->executeQuery('SELECT * FROM '.TempMemberTableManager::TABLE_NAME);

        foreach ($result->iterateAssociative() as $tempRecord) {
            $syncLogger->inkrementCountProcessedRecords();

            unset($tempRecord['id']);

            $sacMemberId = $tempRecord['sacMemberId'];

            if (false === $this->connection->fetchOne('SELECT id FROM tl_member WHERE sacMemberId = ?', [$sacMemberId], [Types::INTEGER])) {
                $this->insertMember($tempRecord, $syncLogger);
            } else {
                $this->updateMember($tempRecord, $sacMemberId, $syncLogger);
            }
        }
    }

    /**
     * Disables all accounts in tl_member that are not present in the temporary table.
     *
     * As a safeguard against a broken/incomplete import, the sync is aborted when more
     * than self::DISABLE_THRESHOLD_PERCENT of all members would be disabled.
     *
     * @throws \RuntimeException When the disable threshold is exceeded
     */
    public function disableNonMembers(SyncLogger $syncLogger): void
    {
        $memberCount = $this->connection->fetchOne('SELECT COUNT(*) FROM tl_member');

        if (0 === $memberCount) {
            return;
        }

        $sql = \sprintf(
            'SELECT m.id FROM tl_member AS m WHERE disable = 0 AND NOT EXISTS (SELECT 1 FROM %s AS t WHERE t.sacMemberId = m.sacMemberId)',
            TempMemberTableManager::TABLE_NAME,
        );

        $disabledMemberIds = $this->connection->fetchFirstColumn($sql);

        $percentDisabledAccounts = round(\count($disabledMemberIds) / $memberCount * 100, 1);

        if ($percentDisabledAccounts > self::DISABLE_THRESHOLD_PERCENT) {
            throw new \RuntimeException(\sprintf('Should disable %d%% of the members, which could indicate an error. Aborting sync process.', $percentDisabledAccounts));
        }

        foreach ($disabledMemberIds as $memberId) {
            $set = [
                'disable' => 1,
                'isSacMember' => 0,
                'login' => 0,
                'tstamp' => time(),
            ];

            if ($this->connection->update('tl_member', $set, ['id' => $memberId], ['id' => Types::INTEGER])) {
                $disabledMember = $this->connection->fetchAssociative('SELECT * FROM tl_member WHERE id = ?', [$memberId], [Types::INTEGER]);

                if (false !== $disabledMember) {
                    $log = \sprintf(
                        'Disable SAC-Member "%s %s" SAC-User-ID: %s during the sync process. User not found in the CSV dump from SAC Zentralverband Bern.',
                        $disabledMember['firstname'],
                        $disabledMember['lastname'],
                        $disabledMember['sacMemberId'],
                    );

                    $syncLogger->addDisabledMessage($log);
                }
            }
        }
    }

    /**
     * Sets a random password for members that do not have one yet.
     *
     * @param int $limit Maximum number of members to process in this run
     */
    public function populateMissingPasswords(int $limit = 20): void
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_member WHERE password = ? LIMIT ?',
            [
                '',
                $limit,
            ],
            [
                Types::STRING,
                Types::INTEGER,
            ],
        );

        foreach ($ids as $id) {
            $this->connection->update(
                'tl_member',
                [
                    'password' => $this->getRandomPasswordHash(),
                    'tstamp' => time(),
                ],
                [
                    'id' => $id,
                ],
                [
                    'id' => Types::INTEGER,
                ],
            );
        }
    }

    private function insertMember(array $tempRecord, SyncLogger $syncLogger): void
    {
        $tempRecord['dateAdded'] = time();
        $tempRecord['tstamp'] = time();
        $tempRecord['isSacMember'] = 1;
        $tempRecord['login'] = 1;
        $tempRecord['disable'] = 0;
        $tempRecord['password'] = $this->getRandomPasswordHash();

        if ($this->connection->insert('tl_member', $tempRecord)) {
            $syncLogger->addInsertMessage(\sprintf(
                'Inserted new SAC-member "%s %s" with SAC-User-ID: %s to tl_member.',
                $tempRecord['firstname'],
                $tempRecord['lastname'],
                $tempRecord['sacMemberId'],
            ));
        }
    }

    private function updateMember(array $tempRecord, int $sacMemberId, SyncLogger $syncLogger): void
    {
        $tempRecord['login'] = 1;
        $tempRecord['disable'] = 0;
        $tempRecord['isSacMember'] = 1;

        if ($this->connection->update('tl_member', $tempRecord, ['sacMemberId' => $sacMemberId], ['sacMemberId' => Types::INTEGER])) {
            // Update tstamp only when the record actually changed.
            $this->connection->update('tl_member', ['tstamp' => time()], ['sacMemberId' => $sacMemberId], ['sacMemberId' => Types::INTEGER]);

            $syncLogger->addUpdateMessage(\sprintf(
                'Updated SAC-member "%s %s" with SAC-User-ID: %s in tl_member.',
                $tempRecord['firstname'],
                $tempRecord['lastname'],
                $tempRecord['sacMemberId'],
            ));
        }
    }

    private function getRandomPasswordHash(): string
    {
        return $this->passwordHasherFactory
            ->getPasswordHasher(FrontendUser::class)
            ->hash(uniqid())
        ;
    }
}
