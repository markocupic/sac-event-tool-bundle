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
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\DataContainer\Util;
use Markocupic\SacEventToolBundle\String\PhoneNumber;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Mirror/Update tl_member from SAC Member Database Zentralverband Bern
 * Unidirectional sync
 * SAC Member Database Zentralverband Bern -> tl_member.
 */
class SyncMemberDatabase
{
    public const FTP_DB_DUMP_TARGET_PATH = '%s/system/tmp/Adressen_0000%d.csv';
    public const FTP_DB_DUMP_END_OF_FILE_STRING = '* * * Dateiende * * *';
    public const FTP_DB_DUMP_FIELD_DELIMITER = '$';
    public const STOP_WATCH_EVENT = 'sac_database_sync';

    public const SYNC_TABLE_NAME = 'tl_member_sync';

    private string|null $ftp_hostname = null;
    private string|null $ftp_username = null;
    private string|null $ftp_password = null;
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
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function run(): void
    {
        $stopWatchEvent = (new Stopwatch())->start(self::STOP_WATCH_EVENT);

        $this->resetSyncLog();
        $this->prepare();
        $this->fetchFilesFromFtp();
        $this->syncContaoDatabase();
        $this->setPassword();
        $this->syncLog['duration'] = round($stopWatchEvent->stop()->getDuration() / 1000);
    }

    /**
     * @throws Exception
     */
    public function setPassword(int $limit = 20): int
    {
        if (!$limit) {
            return $limit;
        }

        $count = 0;

        try {
            $this->connection->executeStatement('LOCK TABLES tl_member WRITE;');
            $this->connection->beginTransaction();

            $result = $this->connection->executeQuery("SELECT id FROM tl_member WHERE password = ? LIMIT 0,$limit", ['']);

            while (false !== ($id = $result->fetchOne())) {
                $password = $this->passwordHasherFactory
                    ->getPasswordHasher(FrontendUser::class)
                    ->hash(uniqid())
                ;

                $set = ['password' => $password];

                if ($this->connection->update('tl_member', $set, ['id' => $id])) {
                    ++$count;
                }
            }

            $this->connection->commit();
            $this->connection->executeStatement('UNLOCK TABLES;');
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->connection->executeStatement('UNLOCK TABLES;');

            throw $e;
        }

        return $count;
    }

    public function getSyncLog(): array
    {
        return $this->syncLog;
    }

    protected function prepare(): void
    {
        $this->ftp_hostname = (string) $this->sacevtMemberSyncCredentials['hostname'];
        $this->ftp_username = (string) $this->sacevtMemberSyncCredentials['username'];
        $this->ftp_password = (string) $this->sacevtMemberSyncCredentials['password'];
    }

    /**
     * @throws Exception
     */
    protected function fetchFilesFromFtp(): void
    {
        $fs = new Filesystem();

        $arrSectionIds = $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section', []);

        $cred = sprintf('ftp://%s:%s@%s/', $this->ftp_username, $this->ftp_password, $this->ftp_hostname);

        $finder = new Finder();
        $finder
            ->files()
            ->in($cred)
            ->name('*.csv')
        ;

        if (!$finder->hasResults()) {
            throw new \RuntimeException('Could not load CSV spreadsheets from remote. Database sync failed.');
        }

        $fileMap = [];

        foreach ($finder as $file) {
            // Extract the section id from filename: Adressen_00004250.csv -> 4250
            $sectionId = (int) filter_var($file->getBasename(), FILTER_SANITIZE_NUMBER_INT);

            $fileMap[$sectionId] = $file;
        }

        foreach ($arrSectionIds as $sectionId) {
            $targetPath = sprintf(static::FTP_DB_DUMP_TARGET_PATH, $this->projectDir, $sectionId);

            // Delete old file
            if ($fs->exists($targetPath)) {
                $fs->remove($targetPath);
            }

            if (!isset($fileMap[$sectionId])) {
                $errMsg = sprintf('Could not find db dump "%s" at "%s".', basename($targetPath), $this->ftp_hostname);
                $this->log(LogLevel::CRITICAL, $errMsg, __METHOD__, ContaoContext::ERROR);

                throw new \Exception($errMsg);
            }

            $objSplFile = $fileMap[$sectionId];

            try {
                $fs->copy($objSplFile->getPathname(), $targetPath);
            } catch (FileNotFoundException $e) {
                $msg = sprintf('Could not find db dump "%s" at "%s".', basename($targetPath), $this->ftp_hostname);
                $this->log(LogLevel::CRITICAL, $msg, __METHOD__, ContaoContext::ERROR);

                throw new \Exception($msg);
            } catch (\Exception $e) {
                $this->log(LogLevel::CRITICAL, $e->getMessage(), __METHOD__, ContaoContext::ERROR);

                throw $e;
            }
        }
    }

    /**
     * Sync tl_member with SAC Zentralverband Database.
     *
     * @throws Exception
     */
    protected function syncContaoDatabase(): void
    {
        $this->createTempTable();

        try {
            $this->connection->executeStatement('LOCK TABLES tl_member WRITE, tl_sac_section WRITE;');
            $this->connection->beginTransaction();

            $arrSectionIds = $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section', []);

            foreach ($arrSectionIds as $sectionId) {
                $targetPath = sprintf(static::FTP_DB_DUMP_TARGET_PATH, $this->projectDir, $sectionId);
                $stream = fopen($targetPath, 'r');

                if (!$stream) {
                    throw new \RuntimeException(sprintf('Could not open file "%s".', $targetPath));
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

                        // Add row to the temp table.
                        $this->insertRowIntoTempTable($this->parseRow($arrLine));
                    }
                }
                fclose($stream);
            }

            $arrAllTemp = $this->connection->fetchAllAssociative('SELECT * FROM '.self::SYNC_TABLE_NAME);

            foreach ($arrAllTemp as $rowTemp) {
                unset($rowTemp['id']);

                $sacMemberId = $rowTemp['sacMemberId'];

                // Insert new member
                if (false === $this->connection->fetchOne('SELECT id FROM tl_member WHERE sacMemberId = ?', [$sacMemberId], [Types::INTEGER])) {
                    $rowTemp['dateAdded'] = time();
                    $rowTemp['tstamp'] = time();
                    $rowTemp['isSacMember'] = 1;
                    $rowTemp['login'] = 1;
                    $rowTemp['disable'] = 0;
                    $rowTemp['password'] = $this->passwordHasherFactory->getPasswordHasher(FrontendUser::class)->hash(uniqid());

                    if ($this->connection->insert('tl_member', $rowTemp)) {
                        $msg = sprintf(
                            'Inserted new SAC-member "%s %s" with SAC-User-ID: %s to tl_member.',
                            $rowTemp['firstname'],
                            $rowTemp['lastname'],
                            $rowTemp['sacMemberId'],
                        );

                        $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_INSERT_NEW_MEMBER);

                        ++$this->syncLog['inserts'];
                    }
                } else {
                    // Update member if necessary
                    $rowTemp['login'] = 1;
                    $rowTemp['disable'] = 0;
                    $rowTemp['isSacMember'] = 1;

                    // Update record if there was a change
                    if ($this->connection->update('tl_member', $rowTemp, ['sacMemberId' => $sacMemberId])) {
                        $set = [
                            'tstamp' => time(),
                        ];

                        $this->connection->update('tl_member', $set, ['sacMemberId' => $sacMemberId]);

                        $msg = sprintf(
                            'Updated SAC-member "%s %s" with SAC-User-ID: %s in tl_member.',
                            $rowTemp['firstname'],
                            $rowTemp['lastname'],
                            $rowTemp['sacMemberId'],
                        );
                        $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_UPDATE_NEW_MEMBER);

                        ++$this->syncLog['updates'];
                    }
                }
            }

            // Disable members that could not be found in the database dump.
            $this->disableNonMembers();

            $this->connection->commit();
            $this->connection->executeStatement('UNLOCK TABLES;');
        } catch (\Exception $e) {
            $msg = 'Error during the database sync process. Starting transaction rollback now.';
            $this->log(LogLevel::CRITICAL, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_TRANSACTION_ERROR);

            $this->syncLog['with_error'] = true;
            $this->syncLog['exception'] = $e->getMessage();

            // Transaction rollback
            $this->connection->rollBack();
            $this->connection->executeStatement('UNLOCK TABLES;');

            // Throw exception
            throw $e;
        }

        $this->syncLog['processed'] = \count($arrAllTemp);

        $this->dropTempTable();
    }

    /**
     * Return a CSV line as an associative array.
     */
    protected function parseRow(array $arrLine): array
    {
        $row = [];
        $row['sacMemberId'] = (int) $arrLine[0]; // int
        $row['username'] = (string) ($arrLine[0]); // string
        // Remove leading zeros 00004253 -> 4253 and convert to string again
        $row['sectionId'] = [(string) (int) ($arrLine[1])]; // array => allow multi membership
        $row['firstname'] = $arrLine[3]; // string
        $row['lastname'] = $arrLine[2]; // string
        $row['addressExtra'] = $arrLine[4]; // string
        $row['street'] = trim($arrLine[5]); // string
        $row['streetExtra'] = $arrLine[6]; // string
        $row['postal'] = $arrLine[7]; // string
        $row['city'] = $arrLine[8]; // string
        $row['country'] = empty($arrLine[9]) ? 'CH' : strtoupper($arrLine[9]); // string
        $row['dateOfBirth'] = (string) strtotime($arrLine[10]); // string!
        $row['phoneBusiness'] = PhoneNumber::beautify($arrLine[11]); // string
        $row['phone'] = PhoneNumber::beautify($arrLine[12]); // string
        $row['mobile'] = PhoneNumber::beautify($arrLine[14]); // string
        $row['fax'] = $arrLine[15]; // string
        $row['email'] = $arrLine[16]; // string
        $row['gender'] = 'weiblich' === strtolower($arrLine[17]) ? 'female' : 'male'; // string
        $row['profession'] = $arrLine[18]; // string
        $row['language'] = 'd' === strtolower($arrLine[19]) ? $this->sacevtLocale : strtolower($arrLine[19]); // string
        $row['entryYear'] = $arrLine[20]; // string
        $row['membershipType'] = $arrLine[23]; // string
        $row['sectionInfo1'] = $arrLine[24]; // string
        $row['sectionInfo2'] = $arrLine[25]; // string
        $row['sectionInfo3'] = $arrLine[26]; // string
        $row['sectionInfo4'] = $arrLine[27]; // string
        $row['debit'] = $arrLine[28]; // string
        $row['memberStatus'] = $arrLine[29]; // string

        $row = array_map(
            static function ($value) {
                if (empty($value) || is_numeric($value) || \is_array($value)) {
                    return $value;
                }

                return mb_convert_encoding(trim($value), 'UTF-8', 'ISO-8859-1');
            },
            $row
        );

        return $row;
    }

    protected function insertRowIntoTempTable(array $arrData): void
    {
        if (empty($arrData['sacMemberId'])) {
            return;
        }

        $arrTempMember = $this->connection
            ->fetchAssociative(
                'SELECT id,sectionId FROM '.self::SYNC_TABLE_NAME.' WHERE sacMemberId = ?',
                [$arrData['sacMemberId']],
                [Types::INTEGER],
            )
        ;

        if (false === $arrTempMember) {
            $arrData['sectionId'] = serialize($this->formatSectionId($arrData['sectionId']));
            $this->connection->insert(self::SYNC_TABLE_NAME, $arrData);
        } else {
            // The user is a member of multiple sections
            // Add the additional sectionId
            $arrSectionIds = array_merge(unserialize($arrTempMember['sectionId']), $arrData['sectionId']);
            $set = [
                'sectionId' => serialize($this->formatSectionId($arrSectionIds)),
            ];

            $this->connection->update(self::SYNC_TABLE_NAME, $set, ['id' => $arrTempMember['id']]);
        }
    }

    /**
     * Disable members that could not be found in the database dump.
     *
     * @throws Exception
     */
    protected function disableNonMembers(): void
    {
        $arrDisabledIDS = $this->connection
            ->fetchFirstColumn('SELECT id FROM tl_member WHERE sacMemberId NOT IN (SELECT sacMemberId FROM tl_member_sync)')
        ;

        foreach ($arrDisabledIDS as $id) {
            $set = [
                'disable' => 1,
                'isSacMember' => 0,
                'login' => 0,
            ];

            if (1 === $this->connection->update('tl_member', $set, ['id' => $id], [Types::INTEGER])) {
                $set = [
                    'tstamp' => time(),
                ];

                $this->connection->update('tl_member', $set, ['id' => $id], [Types::INTEGER]);
                $rowDisabledMember = $this->connection->fetchAssociative('SELECT * FROM tl_member WHERE id = ?', [$id], [Types::INTEGER]);

                if (false !== $rowDisabledMember) {
                    $msg = sprintf(
                        'Disable SAC-Member "%s %s" SAC-User-ID: %s during the sync process. Could not find the user in the SAC main database from Bern.',
                        $rowDisabledMember['firstname'],
                        $rowDisabledMember['lastname'],
                        $rowDisabledMember['sacMemberId']
                    );

                    $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_DISABLE_MEMBER);
                }

                ++$this->syncLog['disabled'];
            }
        }
    }

    protected function log(string $strLogLevel, string $strText, string $strMethod, string $strCategory): void
    {
        $this->syncLog['log'][] = $strText;

        $this->logger?->log(
            $strLogLevel,
            $strText,
            ['contao' => new ContaoContext($strMethod, $strCategory)]
        );
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
        $arrAll = array_map('strval', array_keys($this->util->listSacSections()));

        $arrValue = array_filter(
            $arrAll,
            static fn ($v, $k) => \in_array(
                $v,
                $arrValue,
                true,
            ),
            ARRAY_FILTER_USE_BOTH,
        );

        return array_map('strval', $arrValue);
    }

    protected function dropTempTable(): void
    {
        $schema = $this->connection->createSchemaManager();

        if ($schema->tablesExist(self::SYNC_TABLE_NAME)) {
            $schema->dropTable(self::SYNC_TABLE_NAME);
        }
    }

    protected function createTempTable(): void
    {
        // Drop temp table if exists
        $this->dropTempTable();

        $table = new Table(self::SYNC_TABLE_NAME);
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
        $table->addColumn('phoneBusiness', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('phone', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('mobile', 'string', ['length' => 256, 'default' => '']);
        $table->addColumn('fax', 'string', ['length' => 256, 'default' => '']);
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

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['username']);
        $table->addUniqueIndex(['sacMemberId']);

        $schema = $this->connection->createSchemaManager();
        $schema->createTable($table);
    }
}
