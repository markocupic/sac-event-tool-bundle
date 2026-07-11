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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Database\SyncMember\ContaoMemberWriter;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvFileProvider;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvMemberDto;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvMemberReader;
use Markocupic\SacEventToolBundle\Database\SyncMember\SyncLogger;
use Markocupic\SacEventToolBundle\Database\SyncMember\TempMemberTableManager;
use Markocupic\SacEventToolBundle\DataContainer\Util;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * Handles the synchronization of member data by fetching, processing CSV files
 * from an FTP server, and integrating the data into a MySQL database.
 *
 * This class acts as the orchestrator of the sync process:
 * - It resolves the SAC section ids and lets the {@see CsvFileProvider} fetch the CSV dumps.
 * - It imports the parsed rows into a temporary table.
 * - It delegates all writes to the "tl_member" table to the {@see ContaoMemberWriter}.
 * - It guards the whole sync with a lock and a database transaction.
 */
final class SyncMemberDatabase
{
    public const string STOP_WATCH_EVENT = 'sac_database_sync';

    private array|null $sacSectionIds = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly CsvFileProvider $csvFileProvider,
        private readonly CsvMemberReader $csvMemberReader,
        private readonly ContaoMemberWriter $memberWriter,
        private readonly LockFactory $lockFactory,
        private readonly TempMemberTableManager $tempMemberTableManager,
        private readonly Util $util,
        private readonly string $sacevtLocale,
    ) {
    }

    /**
     * Runs the full member sync (lock-guarded).
     *
     * Any error is caught and recorded on the given SyncLogger (exception + error
     * message); this method itself does not throw.
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

    /**
     * @return list<mixed> The SAC section ids to sync
     */
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
     * - Creates a temporary table to store imported data.
     * - Reads the CSV files and inserts the rows into the temporary table.
     * - Delegates the tl_member synchronization (insert/update/disable/passwords) to the member writer.
     * - Ensures database consistency by wrapping everything in a transaction.
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
            $this->importCsvFilesIntoTempTable($files);

            $this->memberWriter->syncFromTempTable($syncLogger);
            $this->memberWriter->disableNonMembers($syncLogger);
            $this->memberWriter->populateMissingPasswords(20);

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

    /**
     * Reads the CSV files and imports every parsed member into the temporary table.
     *
     * @param array<\SplFileInfo> $files
     */
    protected function importCsvFilesIntoTempTable(array $files): void
    {
        foreach ($files as $file) {
            $stream = fopen($file->getRealPath(), 'r');

            foreach ($this->csvMemberReader->readStream($stream, $this->sacevtLocale, 'CH') as $csvMemberDto) {
                $this->upsertTempMember($csvMemberDto);
            }

            fclose($stream);
        }
    }

    /**
     * Inserts the member into the temp table, or, if it already exists (member of
     * several sections), appends the section id to the existing record.
     */
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
     * Correctly format the section ids (the key and the order are important!): e.g. [0
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
}
