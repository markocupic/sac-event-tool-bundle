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

namespace Markocupic\SacEventToolBundle\Database;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvFileProvider;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvMemberDto;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvMemberReader;
use Markocupic\SacEventToolBundle\Database\SyncMember\SyncLogger;
use Markocupic\SacEventToolBundle\Database\SyncMember\TempMemberTableManager;
use Markocupic\SacEventToolBundle\DataContainer\Util;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * Handles the synchronization of member data by fetching, processing CSV files
 * from an FTP server, and integrating the data into a MySQL database. Provides
 * functionalities such as preparing FTP credentials, managing temporary files,
 * and ensuring secure database operations.
 */
final class SyncMemberDatabase
{
    public const string STOP_WATCH_EVENT = 'sac_database_sync';

    private const int DISABLE_THRESHOLD_PERCENT = 7;

    private array|null $sacSectionIds = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly CsvFileProvider $csvFileProvider,
        private readonly CsvMemberReader $csvMemberReader,
        private readonly LockFactory $lockFactory,
        private readonly PasswordHasherFactory $passwordHasherFactory,
        private readonly TempMemberTableManager $tempMemberTableManager,
        private readonly Util $util,
        private readonly string $sacevtLocale,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function run(SyncLogger $syncLogger): void
    {
        $stopWatchEvent = $this->stopWatchStart();
        $lock = $this->lockFactory->createLock(self::class);
        $lock->acquire(true);

        try {
            $files = $this->csvFileProvider->provide($this->getSectionIds(), $syncLogger);
            $this->syncContaoDatabase($files, $syncLogger);

            $syncLogger->setDuration((int) ceil($stopWatchEvent->stop()->getDuration() / 1000));
        } catch (\Throwable $e) {
            $syncLogger->setException($e);

            if (empty($syncLogger->getErrors())) {
                $syncLogger->addError($e->getMessage());
            }
        } finally {
            $lock->release();
        }
    }

    protected function stopWatchStart(): StopwatchEvent
    {
        return (new Stopwatch())->start(self::STOP_WATCH_EVENT);
    }

    protected function getRandomPasswordHash(): string
    {
        return $this->passwordHasherFactory
            ->getPasswordHasher(FrontendUser::class)
            ->hash(uniqid())
        ;
    }

    protected function getSectionIds(): array
    {
        if (null !== $this->sacSectionIds) {
            return $this->sacSectionIds;
        }

        return $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section');
    }

    /**
     * Syncs the Contao database with the data from the CSV files.
     *
     * This method processes CSV files, extracts SAC member information, and updates
     * the Contao database by inserting new members, updating existing ones, and
     * disabling members that are no longer present in the provided data. It uses a
     * temporary table for intermediate storage before final synchronization.
     *
     * - Creates a temporary table to store imported data.
     * - Reads CSV files from a temporary directory and parses their contents.
     * - Inserts rows into the temporary table.
     * - Synchronizes data between the temporary table and the `tl_member` table in the database:
     *   - Inserts new members.
     *   - Updates existing members.
     *   - Disables members not found in the imported data.
     * - Ensures database consistency by using transactions and locking relevant tables during the operation.
     * - Logs events, such as newly inserted or updated members and errors.
     *
     * @param array<\SplFileInfo> $files
     *
     * @throws Exception
     * @throws \Throwable
     */
    protected function syncContaoDatabase(array $files, SyncLogger $syncLogger): void
    {
        // Create the temporary member database
        $this->tempMemberTableManager->create();

        $this->connection->beginTransaction();

        try {
            foreach ($files as $file) {
                $stream = fopen($file->getRealPath(), 'r');

                foreach ($this->csvMemberReader->readStream($stream, $this->sacevtLocale, 'CH') as $csvMemberDto) {
                    $this->upsertTempMember($csvMemberDto);
                }

                fclose($stream);
            }

            $result = $this->connection->executeQuery('SELECT * FROM '.TempMemberTableManager::TABLE_NAME);

            foreach ($result->iterateAssociative() as $tempRecord) {
                $syncLogger->inkrementCountProcessedRecords();

                unset($tempRecord['id']);

                $sacMemberId = $tempRecord['sacMemberId'];

                // Insert new member
                if (false === $this->connection->fetchOne('SELECT id FROM tl_member WHERE sacMemberId = ?', [$sacMemberId], [Types::INTEGER])) {
                    $tempRecord['dateAdded'] = time();
                    $tempRecord['tstamp'] = time();
                    $tempRecord['isSacMember'] = 1;
                    $tempRecord['login'] = 1;
                    $tempRecord['disable'] = 0;
                    $tempRecord['password'] = $this->getRandomPasswordHash();

                    if ($this->connection->insert('tl_member', $tempRecord)) {
                        $log = \sprintf(
                            'Inserted new SAC-member "%s %s" with SAC-User-ID: %s to tl_member.',
                            $tempRecord['firstname'],
                            $tempRecord['lastname'],
                            $tempRecord['sacMemberId'],
                        );

                        $syncLogger->addInsertMessage($log);
                    }
                } else {
                    // Update member if necessary
                    $tempRecord['login'] = 1;
                    $tempRecord['disable'] = 0;
                    $tempRecord['isSacMember'] = 1;

                    // Update record if there was a change
                    if ($this->connection->update('tl_member', $tempRecord, ['sacMemberId' => $sacMemberId], ['sacMemberId' => Types::INTEGER])) {
                        // update tstamp
                        $this->connection->update('tl_member', ['tstamp' => time()], ['sacMemberId' => $sacMemberId], ['sacMemberId' => Types::INTEGER]);

                        $log = \sprintf(
                            'Updated SAC-member "%s %s" with SAC-User-ID: %s in tl_member.',
                            $tempRecord['firstname'],
                            $tempRecord['lastname'],
                            $tempRecord['sacMemberId'],
                        );

                        $syncLogger->addUpdateMessage($log);
                    }
                }
            }

            // Disable members that could not be found in the CSV files.
            $this->disableAllNonMemberAccounts($syncLogger);

            // Set password where is none.
            $this->populateMissingPasswords(20);

            $this->connection->commit();
        } catch (\Throwable $e) {
            // Transaction rollback
            $this->connection->rollBack();
            $error = 'Error during the database sync process. Starting transaction rollback now. Error message: '.$e->getMessage();
            $syncLogger->addError($error);

            throw $e;
        } finally {
            $this->tempMemberTableManager->drop();
        }
    }

    protected function upsertTempMember(CsvMemberDto $csvMemberDto): void
    {
        $dataMember = $csvMemberDto->toArray();

        if (empty($dataMember['sacMemberId'])) {
            return;
        }

        $dataTempMember = $this->connection->fetchAssociative(
            \sprintf('SELECT id,sectionId FROM %s WHERE sacMemberId = ?', TempMemberTableManager::TABLE_NAME),
            [$dataMember['sacMemberId']],
            [Types::INTEGER],
        );

        if (false === $dataTempMember) {
            // Insert new temp member
            $dataMember['sectionId'] = serialize($this->formatSectionId($dataMember['sectionId']));

            $this->connection->insert(TempMemberTableManager::TABLE_NAME, $dataMember);
        } else {
            // The user is a member of multiple sections and already exists in the temp-table.
            // Then we append the section id only.
            $sectionIds = array_filter(array_unique(array_merge(unserialize($dataTempMember['sectionId']), $dataMember['sectionId'])));

            $set = [
                'sectionId' => serialize($this->formatSectionId($sectionIds)),
            ];

            $this->connection->update(TempMemberTableManager::TABLE_NAME, $set, ['id' => $dataTempMember['id']], ['id' => Types::INTEGER]);
        }
    }

    /**
     * Disables all accounts in tl_member that are not present in the temporary table.
     *
     * The method performs the following actions:
     * - Calculates the total number of members.
     * - Ensures the percentage of members to be disabled does not exceed a predetermined threshold.
     * - Updates the relevant member records to disable them, sets their `isSacMember` attribute to false,
     *   and disables the ability for them to log in.
     * - Records the changes with timestamps and logs the disabled member details.
     *
     * Throws an exception if the disable threshold is exceeded to prevent unintended
     * bulk disabling.
     */
    protected function disableAllNonMemberAccounts(SyncLogger $syncLogger): void
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
            ];

            if ($this->connection->update('tl_member', $set, ['id' => $memberId], ['id' => Types::INTEGER])) {
                $set = [
                    'tstamp' => time(),
                ];

                $this->connection->update('tl_member', $set, ['id' => $memberId], ['id' => Types::INTEGER]);
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
     * Correctly format the section ids (the key and the order is important!): e.g. [0
     * => '4250', 2 => '4252'] -> user is a member of two SAC sektions/ortsgruppen.
     */
    protected function formatSectionId(array $sectionIds): array
    {
        $availableSections = array_map('strval', array_keys($this->util->listSacSections()));

        $filteredSections = array_filter(
            $availableSections,
            static fn (string $sectionId) => \in_array(
                $sectionId,
                $sectionIds,
                true,
            ),
            ARRAY_FILTER_USE_BOTH,
        );

        return array_map('strval', $filteredSections);
    }

    /**
     * Updates the password for members within the specified limit by generating a
     * random hashed password.
     *
     * This method fetches member IDs from the `tl_member` table where the `password`
     * field is empty. The IDs are then iterated over to set a new password and update
     * the timestamp.
     */
    protected function populateMissingPasswords(int $limit = 20): void
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
}
