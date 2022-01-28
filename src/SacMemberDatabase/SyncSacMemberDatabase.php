<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\SacMemberDatabase;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\File;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class SyncSacMemberDatabase.
 */
class SyncSacMemberDatabase
{
    /**
     * Log type for new member.
     */
    public const SAC_EVT_LOG_ADD_NEW_MEMBER = 'MEMBER_DATABASE_SYNC_INSERT_NEW_MEMBER';

    /**
     * Log type for member update.
     */
    public const SAC_EVT_LOG_UPDATE_MEMBER = 'MEMBER_DATABASE_SYNC_UPDATE_MEMBER';

    /**
     * Log type for a successful sync.
     */
    public const SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC = 'MEMBER_DATABASE_SYNC';

    /**
     * Log type if a member has been disabled.
     */
    public const SAC_EVT_LOG_DISABLE_MEMBER = 'DISABLE_MEMBER';

    /**
     * Log type if there is db transaction error.
     */
    public const SAC_EVT_LOG_SAC_MEMBER_DATABASE_TRANSACTION_ERROR = 'MEMBER_DATABASE_TRANSACTION_ERROR';

    /**
     * FTP db dump filepath.
     */
    public const FTP_DB_DUMP_FILE_PATH = 'system/tmp/Adressen_0000%s.csv';

    /**
     * End of file string.
     */
    public const FTP_DB_DUMP_END_OF_FILE_STRING = '* * * Dateiende * * *';

    /**
     * Field delimiter.
     */
    public const FTP_DB_DUMP_FIELD_DELIMITER = '$';
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * string $projectDir.
     */
    private $projectDir;

    /**
     * @var ?LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $section_ids = [];

    /**
     * @var string
     */
    private $ftp_hostname = '';

    /**
     * @var string
     */
    private $ftp_username = '';

    /**
     * @var string
     */
    private $ftp_password = '';

    /**
     * SyncSacMemberDatabase constructor.
     *
     * @param $projectDir
     */
    public function __construct(ContaoFramework $framework, $projectDir, LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;
        $this->logger = $logger;

        $this->framework->initialize();

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var string ftp_hostname */
        $this->ftp_hostname = (string) $configAdapter->get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME');

        /** @var string ftp_username */
        $this->ftp_username = (string) $configAdapter->get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME');

        /** @var string ftp_password */
        $this->ftp_password = (string) $configAdapter->get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD');

        /** @var array section_ids */
        $this->section_ids = !empty($configAdapter->get('SAC_EVT_SAC_SECTION_IDS')) ? explode(',', $configAdapter->get('SAC_EVT_SAC_SECTION_IDS')) : [];
    }

    /**
     * @throws \Exception
     */
    public function run(): void
    {
        $this->fetchFilesFromFtp();
        $this->syncContaoDatabase();
    }

    /**
     * @throws \Exception
     */
    private function fetchFilesFromFtp(): void
    {
        // Open FTP connection
        $connId = $this->openFtpConnection();

        foreach ($this->section_ids as $sectionId) {
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
     *
     * @return resource
     */
    private function openFtpConnection()
    {
        $connId = ftp_connect($this->ftp_hostname);

        if (!ftp_login($connId, $this->ftp_username, $this->ftp_password) || !$connId) {
            $msg = sprintf('Could not establish ftp connection to %s.', $this->ftp_hostname);
            $this->log(LogLevel::CRITICAL, $msg, __METHOD__, self::ERROR);

            throw new \Exception($msg);
        }

        return $connId;
    }

    /**
     * Sync tl_member with Navision db dump.
     *
     * @throws \Exception
     */
    private function syncContaoDatabase(): void
    {
        $startTime = time();
        $arrMemberIDS = [];

        $stmt1 = Database::getInstance()->execute('SELECT sacMemberId FROM tl_member');

        if ($stmt1->numRows) {
            $arrMemberIDS = $stmt1->fetchEach('sacMemberId');
        }

        $stmt2 = Database::getInstance()->prepare('SELECT sacMemberId FROM tl_member WHERE isSacMember=?')->execute('');

        if ($stmt2->numRows) {
            $arrDisabledMemberIDS = $stmt2->fetchEach('sacMemberId');
        }

        // Valid/active members
        $arrSacMemberIds = [];

        $arrMember = [];

        foreach ($this->section_ids as $sectionId) {
            $objFile = new File(sprintf(static::FTP_DB_DUMP_FILE_PATH, $sectionId));

            if (null !== $objFile) {
                $arrFile = $objFile->getContentAsArray();

                foreach ($arrFile as $line) {
                    // End of line
                    if (false !== strpos($line, static::FTP_DB_DUMP_END_OF_FILE_STRING)) {
                        continue;
                    }
                    $arrLine = explode(static::FTP_DB_DUMP_FIELD_DELIMITER, $line);

                    $arrSacMemberIds[] = (int) ($arrLine[0]);

                    $set = [];
                    $set['sacMemberId'] = (int) ($arrLine[0]);
                    $set['username'] = (int) ($arrLine[0]);
                    // Allow multi membership
                    $set['sectionId'] = [(string) ltrim((string) $arrLine[1], '0')];
                    $set['lastname'] = $arrLine[2];
                    $set['firstname'] = $arrLine[3];
                    $set['addressExtra'] = $arrLine[4];
                    $set['street'] = trim((string) $arrLine[5]);
                    $set['streetExtra'] = $arrLine[6];
                    $set['postal'] = $arrLine[7];
                    $set['city'] = $arrLine[8];
                    $set['country'] = empty(strtolower((string) $arrLine[9])) ? 'ch' : strtolower((string) $arrLine[9]);
                    $set['dateOfBirth'] = strtotime((string) $arrLine[10]);
                    $set['phoneBusiness'] = beautifyPhoneNumber($arrLine[11]);
                    $set['phone'] = beautifyPhoneNumber($arrLine[12]);
                    $set['mobile'] = beautifyPhoneNumber($arrLine[14]);
                    $set['fax'] = $arrLine[15];
                    $set['email'] = $arrLine[16];
                    $set['gender'] = 'weiblich' === strtolower((string) $arrLine[17]) ? 'female' : 'male';
                    $set['profession'] = $arrLine[18];
                    $set['language'] = 'd' === strtolower((string) $arrLine[19]) ? 'de' : strtolower((string) $arrLine[19]);
                    $set['entryYear'] = $arrLine[20];
                    $set['membershipType'] = $arrLine[23];
                    $set['sectionInfo1'] = $arrLine[24];
                    $set['sectionInfo2'] = $arrLine[25];
                    $set['sectionInfo3'] = $arrLine[26];
                    $set['sectionInfo4'] = $arrLine[27];
                    $set['debit'] = $arrLine[28];
                    $set['memberStatus'] = $arrLine[29];
                    $arrValues['disable'] = '';

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

                    // Check if member is already in the array
                    if (isset($arrMember[$set['sacMemberId']])) {
                        $arrMember[$set['sacMemberId']]['sectionId'] = array_merge($arrMember[$set['sacMemberId']]['sectionId'], $set['sectionId']);
                    } else {
                        $arrMember[$set['sacMemberId']] = $set;
                    }
                }
            }
        }

        @ini_set('max_execution_time', '0');
        // Consider the suhosin.memory_limit
        if (\extension_loaded('suhosin')) {
            if (($limit = ini_get('suhosin.memory_limit')) !== '') {
                @ini_set('memory_limit', (string) $limit);
            }
        } else {
            @ini_set('memory_limit', '-1');
        }

        try {
            // Lock table tl_member for writing
            Database::getInstance()->lockTables(['tl_member' => 'WRITE']);

            // Start transaction (big thank to cyon.ch)
            Database::getInstance()->beginTransaction();

            // Set tl_member.isSacMember && tl_member.disable to false for all entries
            $set = [
                'disable' => '',
                'isSacMember' => '',
            ];
            Database::getInstance()->prepare('UPDATE tl_member %s')->set($set)->execute();

            /** @var int $countInserts */
            $countInserts = 0;

            /** @var int $countUpdates */
            $countUpdates = 0;

            $i = 0;

            foreach ($arrMember as $sacMemberId => $arrValues) {
                $arrValues['sectionId'] = serialize($arrValues['sectionId']);

                if (!\in_array($sacMemberId, $arrMemberIDS, false)) {
                    $arrValues['dateAdded'] = time();
                    $arrValues['tstamp'] = time();

                    // Insert new member
                    /** @var Statement $objInsertStmt */
                    $objInsertStmt = Database::getInstance()
                        ->prepare('INSERT INTO tl_member %s')
                        ->set($arrValues)
                        ->execute()
                    ;

                    if ($objInsertStmt->affectedRows) {
                        // Log
                        $msg = sprintf('Insert new SAC-member "%s %s" with SAC-User-ID: %s to tl_member.', $arrValues['firstname'], $arrValues['lastname'], $arrValues['sacMemberId']);
                        $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_ADD_NEW_MEMBER);
                        ++$countInserts;
                    }
                } else {
                    // Update/sync datarecord
                    /** @var Statement $objUpdateStmt */
                    $objUpdateStmt = Database::getInstance()->prepare('UPDATE tl_member %s WHERE sacMemberId=?')->set($arrValues)->execute($sacMemberId);

                    if ($objUpdateStmt->affectedRows) {
                        Database::getInstance()->prepare('UPDATE tl_member SET tstamp=? WHERE sacMemberId=?')->execute(time(), $sacMemberId);
                        $msg = sprintf('Update SAC-member "%s %s" with SAC-User-ID: %s in tl_member.', $arrValues['firstname'], $arrValues['lastname'], $arrValues['sacMemberId']);
                        $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_UPDATE_MEMBER);
                        ++$countUpdates;
                    }
                }

                ++$i;
            }

            // Reset sacMemberId to true, if member exists in the downloaded database dump
            $set = [
                'isSacMember' => '1',
                'disable' => '',
                'login' => '1',
            ];

            if (!empty($arrSacMemberIds)) {
                Database::getInstance()
                    ->prepare('UPDATE tl_member %s WHERE sacMemberId IN ('.implode(',', $arrSacMemberIds).')')
                    ->set($set)
                    ->execute()
                ;
            }

            Database::getInstance()->commitTransaction();
        } catch (\Exception $e) {
            $msg = 'Error during the database sync process. Starting transaction rollback now.';
            $this->log(LogLevel::CRITICAL, $msg, __METHOD__, self::SAC_EVT_LOG_SAC_MEMBER_DATABASE_TRANSACTION_ERROR);

            // Transaction rollback
            Database::getInstance()->rollbackTransaction();

            // Unlock tables
            Database::getInstance()->unlockTables();

            // Throw exception
            throw $e;
        }

        // Set tl_member.disable to true if member was not found in the csv-file (is no more a valid SAC member)
        $objDisabledMember = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE isSacMember=?')->execute('');

        while ($objDisabledMember->next()) {
            $arrSet = [
                'tstamp' => time(),
                'disable' => '1',
                'isSacMember' => '',
                'login' => '',
            ];
            Database::getInstance()->prepare('UPDATE tl_member %s WHERE id=?')->set($arrSet)->execute($objDisabledMember->id);

            // Log if disable user
            if (!\in_array($objDisabledMember->sacMemberId, $arrDisabledMemberIDS, false)) {
                $msg = sprintf(
                    'Disable SAC-Member "%s %s" SAC-User-ID: %s during the sync process. Could not find the user in the SAC main database from Bern.',
                    $objDisabledMember->firstname,
                    $objDisabledMember->lastname,
                    $objDisabledMember->sacMemberId
                );
                $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_DISABLE_MEMBER);
            }
        }

        if ($i === \count($arrMember)) {
            $duration = time() - $startTime;

            // Log
            $msg = sprintf(
                'Finished syncing SAC member database with tl_member. Traversed %s entries. Total inserts: %s. Total updates: %s. Duration: %s s.',
                \count($arrMember),
                $countInserts,
                $countUpdates,
                $duration
            );
            $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC);
        }
    }

    private function log(string $strLogLevel, string $strText, string $strMethod, string $strCategory): void
    {
        if (null !== $this->logger) {
            $this->logger->log(
                $strLogLevel,
                $strText,
                ['contao' => new ContaoContext($strMethod, $strCategory)]
            );
        }
    }
}
