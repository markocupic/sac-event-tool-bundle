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

namespace Markocupic\SacEventToolBundle\SacMemberDatabase;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use FTP\Connection as FtpConnection;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\String\PhoneNumber;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Mirror/Update tl_member from SAC Member Database Zentralverband Bern
 * Unidirectional sync SAC Member Database Zentralverband Bern -> tl_member.
 */
class SyncSacMemberDatabase
{
    public const FTP_DB_DUMP_FILE_PATH = 'system/tmp/Adressen_0000%s.csv';
    public const FTP_DB_DUMP_END_OF_FILE_STRING = '* * * Dateiende * * *';
    public const FTP_DB_DUMP_FIELD_DELIMITER = '$';
    public const STOP_WATCH_EVENT = 'sac_database_sync';

    private string|null $ftp_hostname = null;
    private string|null $ftp_username = null;
    private string|null $ftp_password = null;
    private array $syncLog = [
        'log' => [],
        'processed' => 0,
        'inserts' => 0,
        'updates' => 0,
        'duration' => 0,
        'with_error' => false,
        'exception' => '',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly PasswordHasherFactory $passwordHasherFactory,
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

        $this->contaoSystemLog();
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

            // Set a password if there isn't one.
            $strUpd = sprintf(
                'SELECT id FROM tl_member WHERE password = ? LIMIT 0,%d',
                $limit,
            );

            $result = $this->connection->executeQuery($strUpd, ['']);

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

    private function prepare(): void
    {
        $this->ftp_hostname = (string) $this->sacevtMemberSyncCredentials['hostname'];
        $this->ftp_username = (string) $this->sacevtMemberSyncCredentials['username'];
        $this->ftp_password = (string) $this->sacevtMemberSyncCredentials['password'];
    }

    /**
     * @throws Exception
     */
    private function fetchFilesFromFtp(): void
    {
        // Open FTP connection
        $connId = $this->openFtpConnection();

        $arrSectionIds = $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section', []);

        foreach ($arrSectionIds as $sectionId) {
            $localFile = $this->projectDir.'/'.sprintf(static::FTP_DB_DUMP_FILE_PATH, $sectionId);
            $remoteFile = basename($localFile);

            // Delete old file
            if (is_file($localFile)) {
                unlink($localFile);
            }

            // Fetch file
            if (!ftp_get($connId, $localFile, $remoteFile, FTP_BINARY)) {
                $msg = sprintf('Could not find db dump "%s" at "%s".', $remoteFile, $this->ftp_hostname);
                $this->log(LogLevel::CRITICAL, $msg, __METHOD__, ContaoContext::ERROR);

                throw new \Exception($msg);
            }
        }

        ftp_close($connId);
    }

    /**
     * @throws \Exception
     */
    private function openFtpConnection(): FtpConnection
    {
        $connId = ftp_connect($this->ftp_hostname);

        if (!ftp_login($connId, $this->ftp_username, $this->ftp_password) || !$connId) {
            $msg = sprintf('Could not establish ftp connection to %s.', $this->ftp_hostname);
            $this->log(LogLevel::CRITICAL, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_TRANSACTION_ERROR);

            throw new \Exception($msg);
        }

        return $connId;
    }

    /**
     * Sync tl_member with Navision db dump.
     *
     * @throws Exception
     */
    private function syncContaoDatabase(): void
    {
        try {
            $this->connection->executeStatement('LOCK TABLES tl_member WRITE, tl_sac_section WRITE;');
            $this->connection->beginTransaction();

            // All members & non-members
            $arrMemberIDS = array_map('intval', $this->connection->fetchFirstColumn('SELECT sacMemberId FROM tl_member'));

            // Members only
            $arrDisabledMemberIDS = $this->connection->fetchFirstColumn('SELECT sacMemberId FROM tl_member WHERE isSacMember = ?', ['']);
            $arrDisabledMemberIDS = array_map('intval', $arrDisabledMemberIDS);

            // Valid/active members
            $arrSacMemberIds = [];

            $arrMember = [];

            $arrSectionIds = $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section', []);

            foreach ($arrSectionIds as $sectionId) {
                $stream = fopen($this->projectDir.'/'.sprintf(static::FTP_DB_DUMP_FILE_PATH, $sectionId), 'r');

                if ($stream) {
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

                            $arrSacMemberIds[] = $arrLine[0];

                            $set = [];
                            $set['sacMemberId'] = $arrLine[0]; // int
                            $set['username'] = (string) ($arrLine[0]); // string
                            $set['sectionId'] = [(int) ($arrLine[1])]; // array => allow multi membership
                            $set['lastname'] = $arrLine[2]; // string
                            $set['firstname'] = $arrLine[3]; // string
                            $set['addressExtra'] = $arrLine[4]; // string
                            $set['street'] = trim($arrLine[5]); // string
                            $set['streetExtra'] = $arrLine[6]; // string
                            $set['postal'] = $arrLine[7]; // string
                            $set['city'] = $arrLine[8]; // string
                            $set['country'] = empty(strtolower($arrLine[9])) ? 'ch' : strtolower($arrLine[9]); // string
                            $set['dateOfBirth'] = strtotime($arrLine[10]); // int
                            $set['phoneBusiness'] = PhoneNumber::beautify($arrLine[11]); // string
                            $set['phone'] = PhoneNumber::beautify($arrLine[12]); // string
                            $set['mobile'] = PhoneNumber::beautify($arrLine[14]); // string
                            $set['fax'] = $arrLine[15]; // string
                            $set['email'] = $arrLine[16]; // string
                            $set['gender'] = 'weiblich' === strtolower($arrLine[17]) ? 'female' : 'male'; // string
                            $set['profession'] = $arrLine[18]; // string
                            $set['language'] = 'd' === strtolower($arrLine[19]) ? $this->sacevtLocale : strtolower($arrLine[19]); // string
                            $set['entryYear'] = $arrLine[20]; // string
                            $set['membershipType'] = $arrLine[23]; // string
                            $set['sectionInfo1'] = $arrLine[24]; // string
                            $set['sectionInfo2'] = $arrLine[25]; // string
                            $set['sectionInfo3'] = $arrLine[26]; // string
                            $set['sectionInfo4'] = $arrLine[27]; // string
                            $set['debit'] = $arrLine[28]; // string
                            $set['memberStatus'] = $arrLine[29]; // string

                            $set = array_map(
                                static function ($value) {
                                    if (!\is_array($value)) {
                                        $value = \is_string($value) ? trim((string) $value) : $value;

                                        return \is_string($value) ? utf8_encode($value) : $value;
                                    }

                                    return $value;
                                },
                                $set
                            );

                            // Check if member is already in the array (allow multi membership)
                            if (isset($arrMember[$set['sacMemberId']])) {
                                $arrMember[$set['sacMemberId']]['sectionId'] = array_merge($arrMember[$set['sacMemberId']]['sectionId'], $set['sectionId']);
                            } else {
                                $arrMember[$set['sacMemberId']] = $set;
                            }
                        }
                    }
                    fclose($stream);
                }
            }

            @ini_set('max_execution_time', 0);

            // Consider the suhosin.memory_limit (see #7035)
            if (\extension_loaded('suhosin')) {
                if (($limit = \ini_get('suhosin.memory_limit')) !== '') {
                    @ini_set('memory_limit', $limit);
                }
            } else {
                @ini_set('memory_limit', -1);
            }

            // Set tl_member.isSacMember and tl_member.disable to '' for all records
            $this->connection->executeStatement('UPDATE tl_member SET disable = ?, isSacMember = ?', ['', '']);

            $countInserts = 0;

            $countUpdates = 0;

            $i = 0;

            foreach ($arrMember as $sacMemberId => $arrValues) {
                $arrValues['sectionId'] = serialize($arrValues['sectionId']);

                if (!\in_array($sacMemberId, $arrMemberIDS, true)) {
                    $arrValues['dateAdded'] = time();
                    $arrValues['tstamp'] = time();

                    // Insert new member
                    if ($this->connection->insert('tl_member', $arrValues)) {
                        // Log
                        $msg = sprintf('Insert new SAC-member "%s %s" with SAC-User-ID: %s to tl_member.', $arrValues['firstname'], $arrValues['lastname'], $arrValues['sacMemberId']);

                        $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_INSERT_NEW_MEMBER);
                        ++$countInserts;
                    }
                } else {
                    // Update/sync data record
                    if ($this->connection->update('tl_member', $arrValues, ['sacMemberId' => $sacMemberId])) {
                        $set = [
                            'tstamp' => time(),
                        ];

                        $this->connection->update('tl_member', $set, ['sacMemberId' => $sacMemberId]);

                        $msg = sprintf('Update SAC-member "%s %s" with SAC-User-ID: %s in tl_member.', $arrValues['firstname'], $arrValues['lastname'], $arrValues['sacMemberId']);
                        $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_UPDATE_NEW_MEMBER);

                        ++$countUpdates;
                    }
                }

                ++$i;
            }

            if (!empty($arrSacMemberIds)) {
                $qb = $this->connection->createQueryBuilder();
                $qb->update('tl_member', 'm')
                    ->add('where', $qb->expr()->in('m.sacMemberId', ':arr_sac_member_ids'))
                    ->setParameter('arr_sac_member_ids', $arrSacMemberIds, ArrayParameterType::INTEGER)

                    // Reset sacMemberId to true, if member exists in the downloaded database dump
                    ->set('m.isSacMember', ':isSacMember')
                    ->set('m.disable', ':disable')
                    ->set('m.login', ':login')
                    ->setParameter('isSacMember', '1')
                    ->setParameter('disable', '')
                    ->setParameter('login', '1')
                    ->executeStatement()
                ;
            }

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

        // Set tl_member.disable to true if member was not found in the csv-file (is no more a valid SAC member)
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_member WHERE isSacMember = ?', ['']);

        while (false !== ($rowDisabledMember = $stmt->fetchAssociative())) {
            $rowDisabledMember['sacMemberId'] = (int) ($rowDisabledMember['sacMemberId']);

            $set = [
                'tstamp' => time(),
                'disable' => '1',
                'isSacMember' => '',
                'login' => '',
            ];

            $id = $rowDisabledMember['id'];

            $this->connection->update('tl_member', $set, ['id' => $id]);

            // Log if user has been disabled
            if (!\in_array($rowDisabledMember['sacMemberId'], $arrDisabledMemberIDS, true)) {
                $msg = sprintf(
                    'Disable SAC-Member "%s %s" SAC-User-ID: %s during the sync process. Could not find the user in the SAC main database from Bern.',
                    $rowDisabledMember['firstname'],
                    $rowDisabledMember['lastname'],
                    $rowDisabledMember['sacMemberId']
                );

                $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_DISABLE_MEMBER);
            }
        }

        if ($i === \count($arrMember)) {
            $this->syncLog['processed'] = $i;
            $this->syncLog['inserts'] = $countInserts;
            $this->syncLog['updates'] = $countUpdates;
        }
    }

    private function contaoSystemLog(): void
    {
        // Log
        $msg = sprintf(
            'Finished syncing SAC member database with tl_member. Processed %d data records. Total inserts: %d. Total updates: %d. Duration: %d s.',
            $this->syncLog['processed'],
            $this->syncLog['inserts'],
            $this->syncLog['updates'],
            $this->syncLog['duration'],
        );

        $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_SUCCESS);
    }

    private function log(string $strLogLevel, string $strText, string $strMethod, string $strCategory): void
    {
        $this->syncLog['log'][] = $strText;

        $this->logger?->log(
            $strLogLevel,
            $strText,
            ['contao' => new ContaoContext($strMethod, $strCategory)]
        );
    }

    private function resetSyncLog(): void
    {
        // Reset sync log
        $this->syncLog = [
            'log' => [],
            'processed' => 0,
            'inserts' => 0,
            'updates' => 0,
            'duration' => 0,
            'with_error' => false,
            'exception' => '',
        ];
    }
}
