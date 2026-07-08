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

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\DataContainer\Util;
use Markocupic\SacEventToolBundle\String\Formatter\PhoneNumberFormatter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
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
class SyncMemberDatabase
{
    public const string FTP_DB_DUMP_TARGET_PATH = '%s/system/tmp/Adressen_%s.csv';

    public const string FTP_DB_DUMP_END_OF_FILE_STRING = '* * * Dateiende * * *';

    public const string FTP_DB_DUMP_FIELD_DELIMITER = '$';

    public const string STOP_WATCH_EVENT = 'sac_database_sync';

    public const string TEMP_TABLE_NAME = 'tl_member_sync_temp';

    private const int DISABLE_THRESHOLD_PERCENT = 5;

    private string $ftp_hostname;

    private string $ftp_username;

    private string $ftp_password;

    private array $syncLog = [
        'log' => [],
        'processed' => 0,
        'inserts' => 0,
        'updates' => 0,
        'disabled' => 0,
        'duration' => 0,
        'with_error' => false,
        'exception' => '',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly LockFactory $lockFactory,
        private readonly PasswordHasherFactory $passwordHasherFactory,
        private readonly Util $util,
        #[\SensitiveParameter]
        private readonly array $sacevtMemberSyncCredentials,
        private readonly string $projectDir,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
        $this->setFtpOptions();
    }

    /**
     * @throws \Exception
     */
    public function run(): void
    {
        $stopWatchEvent = $this->stopWatchStart();
        $lock = $this->lockFactory->createLock(self::class);
        $lock->acquire(true);

        try {
            $this->resetSyncLog();
            $this->fetchFilesFromFtp();
            $this->syncContaoDatabase();
            $this->syncLog['duration'] = round($stopWatchEvent->stop()->getDuration() / 1000);

            $log = \sprintf(
                'Successfully synced members from SAC Zentralverband database (Bern) to the Contao database (tl_member). Processed: %d, Inserts: %d, Updates: %d, Disabled: %d, Duration: %d s',
                $this->syncLog['processed'],
                $this->syncLog['inserts'],
                $this->syncLog['updates'],
                $this->syncLog['disabled'],
                $this->syncLog['duration'],
            );

            $this->contaoGeneralLogger?->info($log, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_SUCCESS)]);
            $this->syncLog['log'][] = $log;
        } finally {
            $lock->release();
        }
    }

    public function getSyncLog(): array
    {
        return $this->syncLog;
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

    protected function setFtpOptions(): void
    {
        $this->ftp_hostname = (string) $this->sacevtMemberSyncCredentials['hostname'];
        $this->ftp_username = (string) $this->sacevtMemberSyncCredentials['username'];
        $this->ftp_password = (string) $this->sacevtMemberSyncCredentials['password'];
    }

    /**
     * Fetches CSV files from the FTP server and processes them.
     *
     * This method connects to the FTP server using the provided credentials to search
     * for CSV files. For each CSV file found, section IDs are extracted from the
     * filenames and mapped. The files are then copied to the local target path,
     * replacing any existing files. If a required file is missing or cannot be
     * copied, appropriate exceptions are thrown to signal failure.
     *
     * @throws \RuntimeException
     * @throws \Exception
     */
    protected function fetchFilesFromFtp(): void
    {
        $fs = new Filesystem();

        $ftpUrl = \sprintf('ftp://%s:%s@%s/', $this->ftp_username, $this->ftp_password, $this->ftp_hostname);

        $finder = new Finder();
        $finder
            ->files()
            ->in($ftpUrl)
            ->name('*.csv')
        ;

        if (!$finder->hasResults()) {
            throw new \RuntimeException(\sprintf('Could find any CSV files at "%s". Database sync failed.', $this->ftp_hostname));
        }

        $fileMap = [];

        foreach ($finder as $file) {
            // Extract the section id from filename: Adressen_00004250.csv -> 4250
            $sectionId = (int) filter_var($file->getBasename(), FILTER_SANITIZE_NUMBER_INT);

            $fileMap[$sectionId] = $file;
        }

        foreach ($this->getSectionIds() as $sectionId) {
            $sectionIdPadded = str_pad($sectionId, 8, '0', STR_PAD_LEFT);
            $targetPath = \sprintf(static::FTP_DB_DUMP_TARGET_PATH, $this->projectDir, $sectionIdPadded);

            // Delete old file
            if ($fs->exists($targetPath)) {
                $fs->remove($targetPath);
            }

            if (!isset($fileMap[$sectionId])) {
                $errMsg = \sprintf('Could not find the CSV file "%s" at "%s".', basename($targetPath), $this->ftp_hostname);
                $this->contaoErrorLogger?->error($errMsg);
                $this->syncLog['log'][] = $errMsg;

                throw new \Exception($errMsg);
            }

            $splFileInfo = $fileMap[$sectionId];

            try {
                $fs->copy($splFileInfo->getPathname(), $targetPath);
            } catch (FileNotFoundException $e) {
                $errMsg = \sprintf('Could not find the CSV file "%s" at "%s".', basename($targetPath), $this->ftp_hostname);
                $this->contaoErrorLogger?->error($errMsg);
                $this->syncLog['log'][] = $errMsg;

                throw new \Exception($errMsg);
            } catch (\Exception $e) {
                $this->contaoErrorLogger?->error($e->getMessage());

                throw $e;
            }
        }
    }

    /**
     * Retrieves a list of CSV files from the temporary directory and validates their
     * existence, readability, and size.
     *
     * @return array<\SplFileInfo>
     *
     * @throws \RuntimeException
     */
    protected function getCsvFilesFromTempDir(): array
    {
        $files = [];

        foreach ($this->getSectionIds() as $sectionId) {
            $sectionIdPadded = str_pad($sectionId, 8, '0', STR_PAD_LEFT);

            $targetPath = \sprintf(static::FTP_DB_DUMP_TARGET_PATH, $this->projectDir, $sectionIdPadded);

            if (!is_file($targetPath)) {
                throw new \RuntimeException(\sprintf('Could not find the CSV file "%s".', $targetPath));
            }

            $splFileInfo = new \SplFileInfo($targetPath);

            if (!$splFileInfo->isReadable()) {
                throw new \RuntimeException(\sprintf('Could not read the CSV file "%s".', $targetPath));
            }

            if (!str_contains(file_get_contents($targetPath), self::FTP_DB_DUMP_END_OF_FILE_STRING) || $splFileInfo->getSize() < 1000) {
                throw new \RuntimeException(\sprintf('The CSV file "%s" seems to be empty or incomplete.', $targetPath));
            }

            $files[$sectionId] = $splFileInfo;
        }

        return $files;
    }

    protected function getSectionIds(): array
    {
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
     * @throws \RuntimeException
     * @throws \Exception
     */
    protected function syncContaoDatabase(): void
    {
        // Do not place between Connection::beginTransaction() and
        // Connection::commit()/Connection::rollBack() See:
        // https://www.woltlab.com/community/thread/292971-php-8-there-is-no-active-transaction/?postID=1872216#post1872216
        $this->createTempTable();

        $this->connection->beginTransaction();

        try {
            $files = $this->getCsvFilesFromTempDir();

            foreach ($files as $splFileInfo) {
                $stream = fopen($splFileInfo->getRealPath(), 'r');

                if (!$stream) {
                    throw new \RuntimeException(\sprintf('Could not open file "%s".', $splFileInfo->getRealPath()));
                }

                while (!feof($stream)) {
                    if (false !== ($dataLine = fgetcsv($stream, null, static::FTP_DB_DUMP_FIELD_DELIMITER))) {
                        if (empty($dataLine) || empty($dataLine[0])) {
                            continue;
                        }

                        $dataLine[0] = (int) $dataLine[0];

                        // The first column must contain the sac member id (e.g., 134100)
                        if ($dataLine[0] < 1) {
                            continue;
                        }

                        $this->insertOrUpdateTempMember($this->parseLine($dataLine));
                    }
                }
                fclose($stream);
            }

            $tempRecords = $this->connection->fetchAllAssociative('SELECT * FROM '.self::TEMP_TABLE_NAME);

            foreach ($tempRecords as $tempRecord) {
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

                        $this->contaoGeneralLogger?->info($log, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_INSERT_NEW_MEMBER)]);
                        $this->syncLog['log'][] = $log;

                        ++$this->syncLog['inserts'];
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

                        $this->contaoGeneralLogger?->info($log);
                        $this->syncLog['log'][] = $log;

                        ++$this->syncLog['updates'];
                    }
                }
            }

            // Disable members that could not be found in the CSV files.
            $this->disableAllNonMemberAccounts();

            // Set password where is none.
            $this->populateMissingPasswords(20);

            $this->connection->commit();
        } catch (\Exception $e) {
            // Transaction rollback
            $this->connection->rollBack();

            $errMsg = 'Error during the database sync process. Starting transaction rollback now. Error message: '.$e->getMessage();

            $this->contaoErrorLogger?->error($errMsg, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_TRANSACTION_ERROR)]);
            $this->syncLog['log'][] = $errMsg;

            // Throw exception
            throw $e;
        }

        // Drop the temporary table. Do not place between
        // Connection::beginTransaction() and
        // Connection::commit()/Connection::rollBack() See:
        // https://www.woltlab.com/community/thread/292971-php-8-there-is-no-active-transaction/?postID=1872216#post1872216
        $this->dropTempTable();

        $this->syncLog['processed'] = \count($tempRecords);
    }

    /**
     * Parses a line of data and maps it to a structured user array.
     *
     * @See: https://github.com/hitobito/hitobito_sac_cas/blob/6507d29ce25346bbc84b9820c162a1fb384f8460/app/domain/export/tabular/people/sac_mitglieder.rb#L23
     *
     * :id, :layer_navision_id_padded, :last_name, :first_name, :adresszusatz,
     * :address, :postfach, :zip_code, :town, :country, :birthday, :empty,
     * :phone_number_landline, :empty, :phone_number_mobile, :empty, :email, :gender,
     * :empty, :language, :eintrittsjahr, :begünstigt, :ehrenmitglied,
     * :beitragskategorie, :s_info_1, :s_info_2, :s_info_3, :bemerkungen, :saldo,
     * :empty, :anzahl_die_alpen, :anzahl_sektionsbulletin
     */
    protected function parseLine(array $dataLine): array
    {
        $dataLine = array_map(
            static function ($value) {
                if (empty($value) || is_numeric($value) || !\is_string($value)) {
                    return $value;
                }

                return mb_convert_encoding(trim($value), 'UTF-8', 'ISO-8859-1');
            },
            $dataLine,
        );

        $defaultCountry = 'CH';
        $dataMember = [];
        $dataMember['sacMemberId'] = (int) $dataLine[0]; // int
        $dataMember['username'] = (string) $dataLine[0]; // string
        // Remove leading zeros 00004253 -> 4253
        $dataMember['sectionId'] = [ltrim((string) $dataLine[1], '0')]; // array => allow multi membership
        $dataMember['firstname'] = $dataLine[3]; // string
        $dataMember['lastname'] = $dataLine[2]; // string
        $dataMember['addressExtra'] = $dataLine[4]; // string
        $dataMember['street'] = trim($dataLine[5]); // string
        $dataMember['streetExtra'] = $dataLine[6]; // string
        $dataMember['postal'] = $dataLine[7]; // string
        $dataMember['city'] = $dataLine[8]; // string
        $dataMember['country'] = empty($dataLine[9]) ? $defaultCountry : strtoupper($dataLine[9]); // string
        $dataMember['dateOfBirth'] = (string) strtotime($dataLine[10]); // string!
        $dataMember['phone'] = preg_replace('/[^\\d\\+\\s]/', '', (string) $dataLine[12]); // string
        $dataMember['mobile'] = preg_replace('/[^\\d\\+\\s]/', '', (string) $dataLine[14]); // string
        $dataMember['email'] = $dataLine[16]; // string
        $dataMember['gender'] = match ($dataLine[17]) {
            'Weiblich' => 'female',
            'Männlich' => 'male', // Be sure the string has already been converted from ISO-8859-1 to UTF-8
            default => 'other',
        };
        $dataMember['profession'] = $dataLine[18]; // string
        $dataMember['language'] = 'd' === strtolower($dataLine[19]) ? $this->sacevtLocale : strtolower($dataLine[19]); // string
        $dataMember['entryYear'] = $dataLine[20]; // string
        $dataMember['membershipType'] = $dataLine[23]; // string
        $dataMember['sectionInfo1'] = $dataLine[24]; // string
        $dataMember['sectionInfo2'] = $dataLine[25]; // string
        $dataMember['sectionInfo3'] = $dataLine[26]; // string
        $dataMember['sectionInfo4'] = $dataLine[27]; // string
        $dataMember['debit'] = $dataLine[28]; // string
        $dataMember['memberStatus'] = $dataLine[29]; // string

        return $dataMember;
    }

    protected function insertOrUpdateTempMember(array $dataMember): void
    {
        if (empty($dataMember['sacMemberId'])) {
            return;
        }

        $dataTempMember = $this->connection->fetchAssociative(
            \sprintf('SELECT id,sectionId FROM %s WHERE sacMemberId = ?', self::TEMP_TABLE_NAME),
            [$dataMember['sacMemberId']],
            [Types::INTEGER],
        );

        if (false === $dataTempMember) {
            // Insert new temp member
            $dataMember['sectionId'] = serialize($this->formatSectionId($dataMember['sectionId']));
            $dataMember['phone'] = PhoneNumberFormatter::format($dataMember['phone']);
            $dataMember['mobile'] = PhoneNumberFormatter::format($dataMember['mobile']);

            $this->connection->insert(self::TEMP_TABLE_NAME, $dataMember);
        } else {
            // The user is a member of multiple sections and already exists in the temp-table.
            // Then we append the section id only.
            $sectionIds = array_filter(array_unique(array_merge(unserialize($dataTempMember['sectionId']), $dataMember['sectionId'])));

            $set = [
                'sectionId' => serialize($this->formatSectionId($sectionIds)),
            ];

            $this->connection->update(self::TEMP_TABLE_NAME, $set, ['id' => $dataTempMember['id']], ['id' => Types::INTEGER]);
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
    protected function disableAllNonMemberAccounts(): void
    {
        $memberCount = $this->connection->fetchOne('SELECT COUNT(*) FROM tl_member');

        if (0 === $memberCount) {
            return;
        }

        $sql = \sprintf(
            'SELECT m.id FROM tl_member AS m WHERE disable = 0 AND NOT EXISTS (SELECT 1 FROM %s AS t WHERE t.sacMemberId = m.sacMemberId)',
            self::TEMP_TABLE_NAME,
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

                    $this->contaoGeneralLogger?->info($log, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_DISABLE_MEMBER)]);
                    $this->syncLog['log'][] = $log;
                }

                ++$this->syncLog['disabled'];
            }
        }
    }

    protected function resetSyncLog(): void
    {
        // Reset sync log
        $this->syncLog = [
            'log' => [],
            'processed' => 0,
            'inserts' => 0,
            'updates' => 0,
            'disabled' => 0,
            'duration' => 0,
            'with_error' => false,
            'exception' => '',
        ];
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

    protected function dropTempTable(): void
    {
        $schema = $this->connection->createSchemaManager();

        if ($schema->tablesExist(self::TEMP_TABLE_NAME)) {
            $schema->dropTable(self::TEMP_TABLE_NAME);
        }
    }

    protected function createTempTable(): void
    {
        // Drop temp table if exists
        $this->dropTempTable();

        $table = new Table(self::TEMP_TABLE_NAME);

        // Add columns to the table
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->addColumn('sacMemberId', 'integer', ['notnull' => true, 'unsigned' => true, 'default' => 0]);
        $table->addColumn('username', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('sectionId', 'blob', ['notnull' => false, 'length' => 1000]);
        $table->addColumn('firstname', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('lastname', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('addressExtra', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('street', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('streetExtra', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('postal', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('city', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('country', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('dateOfBirth', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('phone', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('mobile', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('email', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('gender', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('profession', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('language', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('entryYear', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('membershipType', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('sectionInfo1', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('sectionInfo2', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('sectionInfo3', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('sectionInfo4', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('debit', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('memberStatus', 'string', ['length' => 256, 'default' => '']);

        // Keys
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['username']);
        $table->addUniqueIndex(['sacMemberId']);

        $this->connection
            ->createSchemaManager()
            ->createTable($table)
        ;
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
