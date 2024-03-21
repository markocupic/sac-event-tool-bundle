<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\SacMemberDatabase;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use FTP\Connection as FtpConnection;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\DataContainer\Util;
use Markocupic\SacEventToolBundle\String\PhoneNumber;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Mirror/Update tl_member from SAC Member Database Zentralverband Bern
 * Unidirectional sync
 * SAC Member Database Zentralverband Bern -> tl_member.
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

        // Open FTP connection
        $connId = $this->openFtpConnection();

        $arrSectionIds = $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section', []);

        foreach ($arrSectionIds as $sectionId) {
            $localFile = $this->projectDir.'/'.sprintf(static::FTP_DB_DUMP_FILE_PATH, $sectionId);
            $remoteFile = basename($localFile);

            // Delete old file
            if ($fs->exists($localFile)) {
                $fs->remove($localFile);
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
    protected function openFtpConnection(): FtpConnection
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
    protected function syncContaoDatabase(): void
    {
        try {
            $this->connection->executeStatement('LOCK TABLES tl_member WRITE, tl_sac_section WRITE;');
            $this->connection->beginTransaction();

            // All members & non-members
            $arrAllMemberIDS = array_map('intval', $this->connection->fetchFirstColumn('SELECT sacMemberId FROM tl_member'));

            // Members only
            $arrDisabledMemberIDS = $this->connection->fetchFirstColumn('SELECT sacMemberId FROM tl_member WHERE isSacMember = ?', [0], [Types::INTEGER]);
            $arrDisabledMemberIDS = array_map('intval', $arrDisabledMemberIDS);

            $arrAllMembersRemote = [];

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

                            $setRemote = [];
                            $setRemote['sacMemberId'] = $arrLine[0]; // int
                            $setRemote['username'] = (string) ($arrLine[0]); // string
                            // Remove leading zeros 00004253 -> 4253 and convert to string again
                            $setRemote['sectionId'] = [(string) (int) ($arrLine[1])]; // array => allow multi membership
                            $setRemote['lastname'] = $arrLine[2]; // string
                            $setRemote['firstname'] = $arrLine[3]; // string
                            $setRemote['addressExtra'] = $arrLine[4]; // string
                            $setRemote['street'] = trim($arrLine[5]); // string
                            $setRemote['streetExtra'] = $arrLine[6]; // string
                            $setRemote['postal'] = $arrLine[7]; // string
                            $setRemote['city'] = $arrLine[8]; // string
                            $setRemote['country'] = empty($arrLine[9]) ? 'CH' : strtoupper($arrLine[9]); // string
                            $setRemote['dateOfBirth'] = (string) strtotime($arrLine[10]); // string!
                            $setRemote['phoneBusiness'] = PhoneNumber::beautify($arrLine[11]); // string
                            $setRemote['phone'] = PhoneNumber::beautify($arrLine[12]); // string
                            $setRemote['mobile'] = PhoneNumber::beautify($arrLine[14]); // string
                            $setRemote['fax'] = $arrLine[15]; // string
                            $setRemote['email'] = $arrLine[16]; // string
                            $setRemote['gender'] = 'weiblich' === strtolower($arrLine[17]) ? 'female' : 'male'; // string
                            $setRemote['profession'] = $arrLine[18]; // string
                            $setRemote['language'] = 'd' === strtolower($arrLine[19]) ? $this->sacevtLocale : strtolower($arrLine[19]); // string
                            $setRemote['entryYear'] = $arrLine[20]; // string
                            $setRemote['membershipType'] = $arrLine[23]; // string
                            $setRemote['sectionInfo1'] = $arrLine[24]; // string
                            $setRemote['sectionInfo2'] = $arrLine[25]; // string
                            $setRemote['sectionInfo3'] = $arrLine[26]; // string
                            $setRemote['sectionInfo4'] = $arrLine[27]; // string
                            $setRemote['debit'] = $arrLine[28]; // string
                            $setRemote['memberStatus'] = $arrLine[29]; // string

                            $setRemote = array_map(
                                static function ($value) {
                                    if (empty($value) || is_numeric($value) || is_array($value)) {
                                        return $value;
                                    }

	                                return utf8_encode(trim($value));
                                },
                                $setRemote
                            );

                            // Check if member is already in the array (allow multi membership)
                            if (isset($arrAllMembersRemote[$setRemote['sacMemberId']])) {
                                $arrAllMembersRemote[$setRemote['sacMemberId']]['sectionId'] = array_merge($arrAllMembersRemote[$setRemote['sacMemberId']]['sectionId'], $setRemote['sectionId']);
                            } else {
                                $arrAllMembersRemote[$setRemote['sacMemberId']] = $setRemote;
                            }
                        }
                    }
                    fclose($stream);
                }
            }

            // Disable all members
            $this->connection->executeStatement('UPDATE tl_member SET login = 0, disable = 1, isSacMember = 0');

            // Insert new and activate existing members again
            $countInserts = 0;
            $countUpdates = 0;
            $i = 0;

            foreach ($arrAllMembersRemote as $sacMemberId => $arrDataRemote) {
                // Set the correct order and set the right indices,
                // because we don't want the subsequent code
                // to update the whole database every time.
                $arrDataRemote['sectionId'] = serialize($this->formatSectionId($arrDataRemote['sectionId']));

                // Insert new member
                if (!\in_array($sacMemberId, $arrAllMemberIDS, true)) {
                    $arrDataRemote['dateAdded'] = time();
                    $arrDataRemote['tstamp'] = time();
                    $arrDataRemote['isSacMember'] = 1;
                    $arrDataRemote['login'] = 1;
                    $arrDataRemote['disable'] = 0;

                    // Insert new member
                    if ($this->connection->insert('tl_member', $arrDataRemote)) {
                        // Log
                        $msg = sprintf('Insert new SAC-member "%s %s" with SAC-User-ID: %s to tl_member.', $arrDataRemote['firstname'], $arrDataRemote['lastname'], $arrDataRemote['sacMemberId']);

                        $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_INSERT_NEW_MEMBER);
                        ++$countInserts;
                    }
                } else {
                    // Activate member account again
                    $set = [
                        'login' => 1,
                        'disable' => 0,
                        'isSacMember' => 1,
                    ];

                    $this->connection->update('tl_member', $set, ['sacMemberId' => $sacMemberId]);

                    // Update/sync data record, but only if there was a change
                    if ($this->connection->update('tl_member', $arrDataRemote, ['sacMemberId' => $sacMemberId])) {
                        $set = [
                            'tstamp' => time(),
                        ];

                        $this->connection->update('tl_member', $set, ['sacMemberId' => $sacMemberId]);

                        $msg = sprintf('Update SAC-member "%s %s" with SAC-User-ID: %s in tl_member.', $arrDataRemote['firstname'], $arrDataRemote['lastname'], $arrDataRemote['sacMemberId']);
                        $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_UPDATE_NEW_MEMBER);

                        ++$countUpdates;
                    }
                }

                ++$i;
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
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_member WHERE isSacMember = ?', [0], [Types::INTEGER]);

        while (false !== ($rowDisabledMember = $stmt->fetchAssociative())) {
            $rowDisabledMember['sacMemberId'] = (int) ($rowDisabledMember['sacMemberId']);

            $set = [
                'tstamp' => time(),
                'disable' => 1,
                'isSacMember' => 0,
                'login' => 0,
            ];

            $id = $rowDisabledMember['id'];

            $this->connection->update('tl_member', $set, ['id' => $id], [Types::INTEGER]);

            // Log if user has been disabled
            if (!\in_array($rowDisabledMember['sacMemberId'], $arrDisabledMemberIDS, true)) {
                $msg = sprintf(
                    'Disable SAC-Member "%s %s" SAC-User-ID: %s during the sync process. Could not find the user in the SAC main database from Bern.',
                    $rowDisabledMember['firstname'],
                    $rowDisabledMember['lastname'],
                    $rowDisabledMember['sacMemberId']
                );

                $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_DISABLE_MEMBER);

                ++$this->syncLog['disabled'];
            }
        }

        if ($i === \count($arrAllMembersRemote)) {
            $this->syncLog['processed'] = $i;
            $this->syncLog['inserts'] = $countInserts;
            $this->syncLog['updates'] = $countUpdates;
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
}
