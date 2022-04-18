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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\File;
use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use FTP\Connection as FtpConnection;
use Markocupic\SacEventToolBundle\Config\Log;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Safe\Exceptions\StringsException;
use function Safe\sprintf;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;

class SyncSacMemberDatabase
{
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

    private ContaoFramework $framework;
    private Connection $connection;
    private EncoderFactory $encoderFactory;
    private array $credentials;
    private string $projectDir;
    private string $locale;
    private ?LoggerInterface $logger;

    private ?string $ftp_hostname = null;
    private ?string $ftp_username = null;
    private ?string $ftp_password = null;

    public function __construct(ContaoFramework $framework, Connection $connection, EncoderFactory $encoderFactory, array $credentials, string $projectDir, string $locale, LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->encoderFactory = $encoderFactory;
        $this->credentials = $credentials;
        $this->projectDir = $projectDir;
        $this->locale = $locale;
        $this->logger = $logger;
    }

    /**
     * @throws StringsException
     * @throws Exception
     */
    public function run(): void
    {
        $this->prepare();
        $this->fetchFilesFromFtp();
        $this->syncContaoDatabase();
    }

    /**
     * @throws StringsException
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
                'SELECT id FROM tl_member WHERE password = ? LIMIT 0,%s',
                (string) $limit,
            );

            $stmt = $this->connection->executeQuery($strUpd, ['']);

            while (false !== ($id = $stmt->fetchOne())) {
                $password = $this->encoderFactory
                    ->getEncoder(FrontendUser::class)
                    ->encodePassword(uniqid(), null)
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

    private function prepare(): void
    {
        $this->framework->initialize(false);
        $this->ftp_hostname = (string) $this->credentials['hostname'];
        $this->ftp_username = (string) $this->credentials['username'];
        $this->ftp_password = (string) $this->credentials['password'];
    }

    /**
     * @throws StringsException
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
     * @throws StringsException
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
     * @throws StringsException
     * @throws Exception
     */
    private function syncContaoDatabase(): void
    {
        $startTime = time();

        try {
            $this->connection->executeStatement('LOCK TABLES tl_member WRITE, tl_sac_section WRITE;');
            $this->connection->beginTransaction();

            // All users members & nonmembers
            $arrMemberIDS = $this->connection->fetchFirstColumn('SELECT sacMemberId FROM tl_member');

            // Members only
            $arrDisabledMemberIDS = $this->connection->fetchFirstColumn('SELECT sacMemberId FROM tl_member WHERE isSacMember = ?', ['']);

            // Valid/active members
            $arrSacMemberIds = [];

            $arrMember = [];

            $arrSectionIds = $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section', []);

            foreach ($arrSectionIds as $sectionId) {
                $objFile = new File(sprintf(static::FTP_DB_DUMP_FILE_PATH, $sectionId));

                $arrFile = $objFile->getContentAsArray();

                foreach ($arrFile as $line) {
                    // End of line
                    if (str_contains($line, static::FTP_DB_DUMP_END_OF_FILE_STRING)) {
                        continue;
                    }
                    $arrLine = explode(static::FTP_DB_DUMP_FIELD_DELIMITER, $line);

                    $arrSacMemberIds[] = (int) ($arrLine[0]);

                    $set = [];
                    $set['sacMemberId'] = (int) ($arrLine[0]);
                    $set['username'] = (int) ($arrLine[0]);
                    // Allow multi membership
                    $set['sectionId'] = [ltrim((string) $arrLine[1], '0')];
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
                    $set['language'] = 'd' === strtolower((string) $arrLine[19]) ? $this->locale : strtolower((string) $arrLine[19]);
                    $set['entryYear'] = $arrLine[20];
                    $set['membershipType'] = $arrLine[23];
                    $set['sectionInfo1'] = $arrLine[24];
                    $set['sectionInfo2'] = $arrLine[25];
                    $set['sectionInfo3'] = $arrLine[26];
                    $set['sectionInfo4'] = $arrLine[27];
                    $set['debit'] = $arrLine[28];
                    $set['memberStatus'] = $arrLine[29];

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

                if (!\in_array($sacMemberId, $arrMemberIDS, false)) {
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
                    ->setParameter('arr_sac_member_ids', $arrSacMemberIds, Connection::PARAM_INT_ARRAY)

                    // Reset sacMemberId to true, if member exists in the downloaded database dump
                    ->set('m.isSacMember', ':isSacMember')
                    ->set('m.disable', ':disable')
                    ->set('m.login', ':login')
                    ->setParameter('isSacMember', '1')
                    ->setParameter('disable', '')
                    ->setParameter('login', '1')
                    ->execute()
                ;
            }

            $this->connection->commit();
            $this->connection->executeStatement('UNLOCK TABLES;');
        } catch (\Exception $e) {
            $msg = 'Error during the database sync process. Starting transaction rollback now.';
            $this->log(LogLevel::CRITICAL, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_TRANSACTION_ERROR);

            // Transaction rollback
            $this->connection->rollBack();
            $this->connection->executeStatement('UNLOCK TABLES;');

            // Throw exception
            throw $e;
        }

        // Set tl_member.disable to true if member was not found in the csv-file (is no more a valid SAC member)
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_member WHERE isSacMember = ?', ['']);

        while (false !== ($rowDisabledMember = $stmt->fetchAssociative())) {
            $set = [
                'tstamp' => time(),
                'disable' => '1',
                'isSacMember' => '',
                'login' => '',
            ];

            $id = $rowDisabledMember['id'];

            $this->connection->update('tl_member', $set, ['id' => $id]);

            // Log if user has been disabled
            if (!\in_array($rowDisabledMember['sacMemberId'], $arrDisabledMemberIDS, false)) {
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
            $duration = time() - $startTime;

            // Log
            $msg = sprintf(
                'Finished syncing SAC member database with tl_member. Traversed %s entries. Total inserts: %s. Total updates: %s. Duration: %s s.',
                \count($arrMember),
                $countInserts,
                $countUpdates,
                $duration
            );

            $this->log(LogLevel::INFO, $msg, __METHOD__, Log::MEMBER_DATABASE_SYNC_SUCCESS);
        }

        // Set password if there isn't one
        $this->setPassword();
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
