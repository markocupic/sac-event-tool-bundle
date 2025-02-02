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
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * Handles the synchronization of member data by fetching, processing CSV files from an FTP server,
 * and integrating the data into a MySQL database. Provides functionalities such as preparing FTP
 * credentials, managing temporary files, and ensuring secure database operations.
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
        $this->resetSyncLog();
        $this->fetchFilesFromFtp();
        $this->syncContaoDatabase();
        $this->syncLog['duration'] = round($stopWatchEvent->stop()->getDuration() / 1000);

        $log = sprintf(
            'Successfully synced members from SAC Zentralverband database (Bern) to the Contao database (tl_member). Processed: %d, Inserts: %d, Updates: %d, Disabled: %d, Duration: %d s',
            $this->syncLog['processed'],
            $this->syncLog['inserts'],
            $this->syncLog['updates'],
            $this->syncLog['disabled'],
            $this->syncLog['duration'],
        );

        $this->contaoGeneralLogger?->info($log, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_SUCCESS)]);
        $this->syncLog['log'][] = $log;
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
     * This method connects to the FTP server using the provided credentials to search for
     * CSV files. For each CSV file found, section IDs are extracted from the filenames
     * and mapped. The files are then copied to the local target path, replacing
     * any existing files. If a required file is missing or cannot be copied, appropriate
     * exceptions are thrown to signal failure.
     *
     * @throws \RuntimeException
     * @throws \Exception
     */
    protected function fetchFilesFromFtp(): void
    {
        $fs = new Filesystem();

        $ftpUrl = sprintf('ftp://%s:%s@%s/', $this->ftp_username, $this->ftp_password, $this->ftp_hostname);

        $finder = new Finder();
        $finder
            ->files()
            ->in($ftpUrl)
            ->name('*.csv')
        ;

        if (!$finder->hasResults()) {
            throw new \RuntimeException(sprintf('Could not load CSV from "%s". Database sync failed.', $this->ftp_hostname));
        }

        $fileMap = [];

        foreach ($finder as $file) {
            // Extract the section id from filename: Adressen_00004250.csv -> 4250
            $sectionId = (int) filter_var($file->getBasename(), FILTER_SANITIZE_NUMBER_INT);

            $fileMap[$sectionId] = $file;
        }

        foreach ($this->getSectionIds() as $sectionId) {
            $sectionIdPadded = str_pad($sectionId, 8, '0', STR_PAD_LEFT);
            $targetPath = sprintf(static::FTP_DB_DUMP_TARGET_PATH, $this->projectDir, $sectionIdPadded);

            // Delete old file
            if ($fs->exists($targetPath)) {
                $fs->remove($targetPath);
            }

            if (!isset($fileMap[$sectionId])) {
                $errMsg = sprintf('Could not find the CSV file "%s" at "%s".', basename($targetPath), $this->ftp_hostname);
                $this->contaoErrorLogger?->error($errMsg);
                $this->syncLog['log'][] = $errMsg;

                throw new \Exception($errMsg);
            }

            $objSplFile = $fileMap[$sectionId];

            try {
                $fs->copy($objSplFile->getPathname(), $targetPath);
            } catch (FileNotFoundException $e) {
                $errMsg = sprintf('Could not find the CSV file "%s" at "%s".', basename($targetPath), $this->ftp_hostname);
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
     * Retrieves a list of CSV files from the temporary directory
     * and validates their existence, readability, and size.
     *
     * @throws \RuntimeException
     *
     * @return array<\SplFileObject>
     */
    protected function getCsvFilesFromTempDir(): array
    {
        $arrFiles = [];

        foreach ($this->getSectionIds() as $sectionId) {
            $sectionIdPadded = str_pad($sectionId, 8, '0', STR_PAD_LEFT);

            $targetPath = sprintf(static::FTP_DB_DUMP_TARGET_PATH, $this->projectDir, $sectionIdPadded);

            if (!is_file($targetPath)) {
                throw new \RuntimeException(sprintf('Could not find the CSV file "%s".', $targetPath));
            }

            $objSplFile = new \SplFileObject($targetPath);

            if (!$objSplFile->isReadable()) {
                throw new \RuntimeException(sprintf('Could not read the CSV file "%s".', $targetPath));
            }

            if (!str_contains(file_get_contents($targetPath), self::FTP_DB_DUMP_END_OF_FILE_STRING) || $objSplFile->getSize() < 1000) {
                throw new \RuntimeException(sprintf('The CSV file "%s" seems to be empty or incomplete.', $targetPath));
            }

            $arrFiles[$sectionId] = $objSplFile;
        }

        return $arrFiles;
    }

    protected function getSectionIds(): array
    {
        return $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section');
    }

    /**
     * Syncs the Contao database with the data from the CSV files.
     *
     * This method processes CSV files, extracts SAC member information, and updates the Contao database
     * by inserting new members, updating existing ones, and disabling members that are no longer present
     * in the provided data. It uses a temporary table for intermediate storage before final synchronization.
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
        // Do not place between Connection::beginTransaction() and Connection::commit()/Connection::rollBack()
        // See: https://www.woltlab.com/community/thread/292971-php-8-there-is-no-active-transaction/?postID=1872216#post1872216
        $this->createTempTable();

        $this->connection->beginTransaction();

        try {
            $arrFiles = $this->getCsvFilesFromTempDir();

            foreach ($arrFiles as $objSplFile) {
                $stream = fopen($objSplFile->getRealPath(), 'r');

                if (!$stream) {
                    throw new \RuntimeException(sprintf('Could not open file "%s".', $objSplFile->getRealPath()));
                }

                while (!feof($stream)) {
                    if (false !== ($arrLine = fgetcsv($stream, null, static::FTP_DB_DUMP_FIELD_DELIMITER))) {
                        if (empty($arrLine) || empty($arrLine[0])) {
                            continue;
                        }

                        $arrLine[0] = (int) ($arrLine[0]);

                        // First column must contain the sac member id (e.g. 134100)
                        if ($arrLine[0] < 1) {
                            continue;
                        }

                        $this->insertOrUpdateTempMember($this->parseLine($arrLine));
                    }
                }
                fclose($stream);
            }

            $arrAllTemp = $this->connection->fetchAllAssociative('SELECT * FROM '.self::TEMP_TABLE_NAME);

            foreach ($arrAllTemp as $rowTemp) {
                unset($rowTemp['id']);

                $sacMemberId = $rowTemp['sacMemberId'];

                // Map phone numbers
                $rowTemp['mobile'] = !empty($rowTemp['phoneMobile']) ? $rowTemp['phoneMobile'] : $rowTemp['phoneMain'];
                $rowTemp['phone'] = $rowTemp['phonePrivate'];
                $rowTemp['fax'] = $rowTemp['phoneFax'];

                unset($rowTemp['phoneMobile'], $rowTemp['phoneMain'], $rowTemp['phonePrivate'], $rowTemp['phoneFax']);

                // Insert new member
                if (false === $this->connection->fetchOne('SELECT id FROM tl_member WHERE sacMemberId = ?', [$sacMemberId], [Types::INTEGER])) {
                    $rowTemp['dateAdded'] = time();
                    $rowTemp['tstamp'] = time();
                    $rowTemp['isSacMember'] = 1;
                    $rowTemp['login'] = 1;
                    $rowTemp['disable'] = 0;
                    $rowTemp['password'] = $this->getRandomPasswordHash();

                    if ($this->connection->insert('tl_member', $rowTemp)) {
                        $log = sprintf(
                            'Inserted new SAC-member "%s %s" with SAC-User-ID: %s to tl_member.',
                            $rowTemp['firstname'],
                            $rowTemp['lastname'],
                            $rowTemp['sacMemberId'],
                        );

                        $this->contaoGeneralLogger?->info($log, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_INSERT_NEW_MEMBER)]);
                        $this->syncLog['log'][] = $log;

                        ++$this->syncLog['inserts'];
                    }
                } else {
                    // Update member if necessary
                    $rowTemp['login'] = 1;
                    $rowTemp['disable'] = 0;
                    $rowTemp['isSacMember'] = 1;

                    // Update record if there was a change
                    if ($this->connection->update('tl_member', $rowTemp, ['sacMemberId' => $sacMemberId], ['sacMemberId' => Types::INTEGER])) {
                        // update tstamp
                        $this->connection->update('tl_member', ['tstamp' => time()], ['sacMemberId' => $sacMemberId], ['sacMemberId' => Types::INTEGER]);

                        $log = sprintf(
                            'Updated SAC-member "%s %s" with SAC-User-ID: %s in tl_member.',
                            $rowTemp['firstname'],
                            $rowTemp['lastname'],
                            $rowTemp['sacMemberId'],
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
            $this->setPassword(20);

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

        // Drop the temporary table.
        // Do not place between Connection::beginTransaction() and Connection::commit()/Connection::rollBack()
        // See: https://www.woltlab.com/community/thread/292971-php-8-there-is-no-active-transaction/?postID=1872216#post1872216
        $this->dropTempTable();

        $this->syncLog['processed'] = \count($arrAllTemp);
    }

    /**
     * Parses a line of data and maps it to a structured user array.
     *
     * @See: https://github.com/hitobito/hitobito_sac_cas/blob/6507d29ce25346bbc84b9820c162a1fb384f8460/app/domain/export/tabular/people/sac_mitglieder.rb#L23
     * 0 - id,
     * 1 - layer_navision_id_padded,
     * 2 - last_name,
     * 3 - first_name,
     * 4 - adresszusatz,
     * 5 - address,
     * 6 - postfach,
     * 7 - zip_code,
     * 8 - town,
     * 9 - country,
     * 10 - birthday,
     * 11 - phone_number_main,
     * 12 - phone_number_privat,
     * 13 - empty, # 1 empty col
     * 14 - phone_number_mobil,
     * 15 - phone_number_fax,
     * 16 - email,
     * 17 - gender,
     * 18 - empty, # 1 empty col
     * 19 - language,
     * 20 - eintrittsjahr,
     * 21 - begünstigt,
     * 22 - ehrenmitglied,
     * 23 - beitragskategorie,
     * 24 - s_info_1,
     * 25 - s_info_2,
     * 26 - s_info_3,
     * 27 - bemerkungen,
     * 28 - saldo,
     * 29 - empty, # 1 empty col
     * 30 - anzahl_die_alpen,
     * 31 - anzahl_sektionsbulletin
     */
    protected function parseLine(array $arrLine): array
    {
        $arrLine = array_map(
            static function ($value) {
                if (empty($value) || is_numeric($value) || \is_array($value) || !\is_string($value)) {
                    return $value;
                }

                return mb_convert_encoding(trim($value), 'UTF-8', 'ISO-8859-1');
            },
            $arrLine
        );

        $defaultCountry = 'CH';
        $rowUser = [];
        $rowUser['sacMemberId'] = (int) $arrLine[0]; // int
        $rowUser['username'] = (string) ($arrLine[0]); // string
        // Remove leading zeros 00004253 -> 4253
        $rowUser['sectionId'] = [ltrim((string) $arrLine[1], '0')]; // array => allow multi membership
        $rowUser['firstname'] = $arrLine[3]; // string
        $rowUser['lastname'] = $arrLine[2]; // string
        $rowUser['addressExtra'] = $arrLine[4]; // string
        $rowUser['street'] = trim($arrLine[5]); // string
        $rowUser['streetExtra'] = $arrLine[6]; // string
        $rowUser['postal'] = $arrLine[7]; // string
        $rowUser['city'] = $arrLine[8]; // string
        $rowUser['country'] = empty($arrLine[9]) ? $defaultCountry : strtoupper($arrLine[9]); // string
        $rowUser['dateOfBirth'] = (string) strtotime($arrLine[10]); // string!
        $rowUser['phoneMain'] = preg_replace('/[^\\d\\+\\s]/', '', (string) $arrLine[11]); // string
        $rowUser['phonePrivate'] = preg_replace('/[^\\d\\+\\s]/', '', (string) $arrLine[12]); // string
        $rowUser['phoneMobile'] = preg_replace('/[^\\d\\+\\s]/', '', (string) $arrLine[14]); // string
        $rowUser['phoneFax'] = preg_replace('/[^\\d\\+\\s]/', '', (string) $arrLine[15]); // string
        $rowUser['email'] = $arrLine[16]; // string
        $rowUser['gender'] = match ($arrLine[17]) {
            'Weiblich' => 'female',
            'Männlich' => 'male', // Be sure the string has already been converted from ISO-8859-1 to UTF-8
            'Andere' => 'other',
            default => 'other',
        };
        $rowUser['profession'] = $arrLine[18]; // string
        $rowUser['language'] = 'd' === strtolower($arrLine[19]) ? $this->sacevtLocale : strtolower($arrLine[19]); // string
        $rowUser['entryYear'] = $arrLine[20]; // string
        $rowUser['membershipType'] = $arrLine[23]; // string
        $rowUser['sectionInfo1'] = $arrLine[24]; // string
        $rowUser['sectionInfo2'] = $arrLine[25]; // string
        $rowUser['sectionInfo3'] = $arrLine[26]; // string
        $rowUser['sectionInfo4'] = $arrLine[27]; // string
        $rowUser['debit'] = $arrLine[28]; // string
        $rowUser['memberStatus'] = $arrLine[29]; // string

        return $rowUser;
    }

    protected function insertOrUpdateTempMember(array $arrData): void
    {
        if (empty($arrData['sacMemberId'])) {
            return;
        }

        $existingMember = $this->connection
            ->fetchAssociative(
                sprintf('SELECT id,sectionId FROM %s WHERE sacMemberId = ?', self::TEMP_TABLE_NAME),
                [$arrData['sacMemberId']],
                [Types::INTEGER],
            )
        ;

        if (false === $existingMember) {
            // Insert new temp member
            $arrData['sectionId'] = serialize($this->formatSectionId($arrData['sectionId']));
            $this->connection->insert(self::TEMP_TABLE_NAME, $arrData);
        } else {
            // The user is a member of multiple sections and already exists in the temp table
            // Then we append the section id only.
            $arrSectionIds = array_filter(array_unique(array_merge(unserialize($existingMember['sectionId']), $arrData['sectionId'])));
            $set = [
                'sectionId' => serialize($this->formatSectionId($arrSectionIds)),
            ];

            $this->connection->update(self::TEMP_TABLE_NAME, $set, ['id' => $existingMember['id']], ['id' => Types::INTEGER]);
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
     * Throws an exception if the disable threshold is exceeded to prevent unintended bulk disabling.
     */
    protected function disableAllNonMemberAccounts(): void
    {
        $totalMembers = $this->connection->fetchOne('SELECT COUNT(*) FROM tl_member');

        if (0 === $totalMembers) {
            return;
        }

        $sql = sprintf(
            'SELECT id FROM tl_member WHERE disable = 0 AND sacMemberId NOT IN (SELECT sacMemberId FROM %s)',
            self::TEMP_TABLE_NAME,
        );

        $disabledMemberIds = $this->connection->fetchFirstColumn($sql);

        $percentDisabledAccounts = round(\count($disabledMemberIds) / $totalMembers * 100, 1);

        if ($percentDisabledAccounts > self::DISABLE_THRESHOLD_PERCENT) {
            throw new \RuntimeException(sprintf('Should disable %d%% of the members, which could indicate an error. Aborting sync process.', $percentDisabledAccounts));
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
                $rowDisabledMember = $this->connection->fetchAssociative('SELECT * FROM tl_member WHERE id = ?', [$memberId], [Types::INTEGER]);

                if (false !== $rowDisabledMember) {
                    $log = sprintf(
                        'Disable SAC-Member "%s %s" SAC-User-ID: %s during the sync process. User not found in the CSV dump from SAC Zentralverband Bern.',
                        $rowDisabledMember['firstname'],
                        $rowDisabledMember['lastname'],
                        $rowDisabledMember['sacMemberId']
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
     * Correctly format the section ids (the key and the order is important!):
     * e.g. [0 => '4250', 2 => '4252']
     * -> user is member of two SAC Sektionen/Ortsgruppen.
     */
    protected function formatSectionId(array $arrValue): array
    {
        $availableSections = array_map('strval', array_keys($this->util->listSacSections()));

        $filteredSections = array_filter(
            $availableSections,
            static fn (string $sectionId) => \in_array(
                $sectionId,
                $arrValue,
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
        $table->addColumn('phoneMain', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('phonePrivate', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('phoneMobile', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('phoneFax', 'string', ['length' => 256, 'default' => '']);
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
     * Updates the password for members within the specified limit by generating a random hashed password.
     *
     * This method fetches member IDs from the `tl_member` table where the `password` field is empty.
     * The IDs are then iterated over to set a new password and update the timestamp.
     */
    protected function setPassword(int $limit = 20): void
    {
        $arrIds = $this->connection->fetchFirstColumn(
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

        foreach ($arrIds as $id) {
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
