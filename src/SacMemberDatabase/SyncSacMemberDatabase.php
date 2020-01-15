<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
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
 * Class SyncSacMemberDatabase
 * @package Markocupic\SacEventToolBundle\SacMemberDatabase
 */
class SyncSacMemberDatabase
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var ?LoggerInterface
     */
    private $logger;

    /**
     * string $projectDir
     */
    private $projectDir;

    /**
     * Log type for new member
     */
    const SAC_EVT_LOG_ADD_NEW_MEMBER = 'ADD_NEW_MEMBER';

    /**
     * Log type for a successfull sync
     */
    const SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC = 'MEMBER_DATABASE_SYNC';

    /**
     * Log type if a member has been disabled
     */
    const SAC_EVT_LOG_DISABLE_MEMBER = 'DISABLE_MEMBER';

    /**
     * Log type if there is db transaction error
     */
    const SAC_EVT_LOG_SAC_MEMBER_DATABASE_TRANSACTION_ERROR = 'MEMBER_DATABASE_TRANSACTION_ERROR';

    /**
     * @var array
     */
    private $section_ids = array();

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
     * @param ContaoFramework $framework
     * @param null|LoggerInterface $logger
     * @param $projectDir
     */
    public function __construct(ContaoFramework $framework, ?LoggerInterface $logger = null, $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;
        $this->logger = $logger;

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $this->ftp_hostname = $configAdapter->get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME');
        $this->ftp_username = (string) $configAdapter->get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME');
        $this->ftp_password = (string) $configAdapter->get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD');
        $this->section_ids = explode(',', $configAdapter->get('SAC_EVT_SAC_SECTION_IDS'));
    }

    /**
     * @throws \Exception
     */
    public function run()
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

        foreach ($this->section_ids as $sectionId)
        {
            $localFile = $this->projectDir . '/system/tmp/Adressen_0000' . $sectionId . '.csv';
            // Check for old file
            if (is_file($localFile))
            {
                // Delete old file
                unlink($localFile);
            }

            $remoteFile = 'Adressen_0000' . $sectionId . '.csv';
            // Download file
            if (!\ftp_get($connId, $localFile, $remoteFile, FTP_BINARY))
            {
                $msg = 'Could not find/download ' . $remoteFile . ' from ' . $this->ftp_hostname;
                $this->log(LogLevel::CRITICAL, $msg, __METHOD__, ContaoContext::ERROR);

                throw new \Exception($msg);
            }
        }
        \ftp_close($connId);
    }

    /**
     * @return resource
     * @throws \Exception
     */
    private function openFtpConnection()
    {
        $connId = \ftp_connect($this->ftp_hostname);
        if (!\ftp_login($connId, $this->ftp_username, $this->ftp_password) || !$connId)
        {
            $msg = 'Could not establish ftp connection to ' . $this->ftp_hostname;
            $this->log(LogLevel::CRITICAL, $msg, __METHOD__, self::ERROR);

            throw new \Exception($msg);
        }

        return $connId;
    }

    /**
     * @throws \Exception
     */
    private function syncContaoDatabase(): void
    {
        $startTime = \time();
        $arrMemberIDS = [];

        $statement = Database::getInstance()->execute('SELECT sacMemberId FROM tl_member');
        if ($statement->numRows)
        {
            $arrMemberIDS = $statement->fetchEach('sacMemberId');
        }
        $arrMember = array();
        foreach ($this->section_ids as $sectionId)
        {
            $objFile = new File('system/tmp/Adressen_0000' . $sectionId . '.csv');
            if ($objFile !== null)
            {
                $arrFile = $objFile->getContentAsArray();
                foreach ($arrFile as $line)
                {
                    // End of line
                    if (\strpos($line, '* * * Dateiende * * *') !== false)
                    {
                        continue;
                    }
                    $arrLine = \explode('$', $line);
                    $set = array();
                    $set['sacMemberId'] = \intval($arrLine[0]);
                    $set['username'] = \intval($arrLine[0]);
                    // Mehrere Sektionsmitgliedschaften möglich
                    $set['sectionId'] = array((string) ltrim($arrLine[1], '0'));
                    $set['lastname'] = $arrLine[2];
                    $set['firstname'] = $arrLine[3];
                    $set['addressExtra'] = $arrLine[4];
                    $set['street'] = trim($arrLine[5]);
                    $set['streetExtra'] = $arrLine[6];
                    $set['postal'] = $arrLine[7];
                    $set['city'] = $arrLine[8];
                    $set['country'] = \strtolower($arrLine[9]) == '' ? 'ch' : \strtolower($arrLine[9]);
                    $set['dateOfBirth'] = \strtotime($arrLine[10]);
                    $set['phoneBusiness'] = beautifyPhoneNumber($arrLine[11]);
                    $set['phone'] = beautifyPhoneNumber($arrLine[12]);
                    $set['mobile'] = beautifyPhoneNumber($arrLine[14]);
                    $set['fax'] = $arrLine[15];
                    $set['email'] = $arrLine[16];
                    $set['gender'] = \strtolower($arrLine[17]) == 'weiblich' ? 'female' : 'male';
                    $set['profession'] = $arrLine[18];
                    $set['language'] = \strtolower($arrLine[19]) == 'd' ? 'de' : \strtolower($arrLine[19]);
                    $set['entryYear'] = $arrLine[20];
                    $set['membershipType'] = $arrLine[23];
                    $set['sectionInfo1'] = $arrLine[24];
                    $set['sectionInfo2'] = $arrLine[25];
                    $set['sectionInfo3'] = $arrLine[26];
                    $set['sectionInfo4'] = $arrLine[27];
                    $set['debit'] = $arrLine[28];
                    $set['memberStatus'] = $arrLine[29];
                    $set['tstamp'] = \time();
                    $set['disable'] = '';
                    $set['isSacMember'] = '1';

                    $set = \array_map(function ($value) {
                        if (!\is_array($value))
                        {
                            $value = \is_string($value) ? \trim($value) : $value;
                            $value = \is_string($value) ? \utf8_encode($value) : $value;
                            return $value;
                        }
                        return $value;
                    }, $set);

                    // Check if the member is already in the array
                    if (isset($arrMember[$set['sacMemberId']]))
                    {
                        $arrMember[$set['sacMemberId']]['sectionId'] = \array_merge($arrMember[$set['sacMemberId']]['sectionId'], $set['sectionId']);
                    }
                    else
                    {
                        $arrMember[$set['sacMemberId']] = $set;
                    }
                }
            }
        }

        @ini_set('max_execution_time', '0');
        // Consider the suhosin.memory_limit
        if (\extension_loaded('suhosin'))
        {
            if (($limit = ini_get('suhosin.memory_limit')) !== '')
            {
                @ini_set('memory_limit', (string) $limit);
            }
        }
        else
        {
            @ini_set('memory_limit', '-1');
        }

        try
        {
            // Lock table tl_member for writing
            Database::getInstance()->lockTables(array('tl_member' => 'WRITE'));

            // Start transaction (big thank to cyon.ch)
            Database::getInstance()->beginTransaction();

            // Set tl_member.isSacMember to empty string
            Database::getInstance()->prepare('UPDATE tl_member SET isSacMember = ?')->execute('');

            $i = 0;
            foreach ($arrMember as $sacMemberId => $arrValues)
            {
                $arrValues['sectionId'] = \serialize($arrValues['sectionId']);

                if (!in_array($sacMemberId, $arrMemberIDS))
                {
                    $arrValues['dateAdded'] = time();

                    // Add new member
                    Database::getInstance()->prepare('INSERT INTO tl_member %s')->set($arrValues)->execute();

                    // Log
                    $msg = \sprintf('Insert new SAC-member "%s %s" with SAC-User-ID: %s to tl_member.', $arrValues['firstname'], $arrValues['lastname'], $arrValues['sacMemberId']);
                    $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_ADD_NEW_MEMBER);
                }
                else
                {
                    // Sync datarecord
                    Database::getInstance()->prepare('UPDATE tl_member %s WHERE sacMemberId=?')->set($arrValues)->execute($sacMemberId);
                }

                $i++;
            }

            Database::getInstance()->commitTransaction();
        } catch (\Exception $e)
        {
            $msg = 'Error during the database sync process. Starting transaction rollback, now.';
            $this->log(LogLevel::CRITICAL, $msg, __METHOD__, self::SAC_EVT_LOG_SAC_MEMBER_DATABASE_TRANSACTION_ERROR);

            //transaction rollback
            Database::getInstance()->rollbackTransaction();

            Database::getInstance()->unlockTables();

            // Throw exception
            throw $e;
        }

        // Set tl_member.disable to true if member was not found in the csv-file
        $objDisabledMember = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE disable=? AND isSacMember=?')->execute('', '');
        while ($objDisabledMember->next())
        {
            $arrSet = array(
                'tstamp'  => \time(),
                'disable' => '1',
            );
            Database::getInstance()->prepare('UPDATE tl_member %s WHERE id=?')->set($arrSet)->execute($objDisabledMember->id);

            // Log
            $msg = \sprintf('Disable SAC-Member "%s %s" SAC-User-ID: %s during the sync process. The user can not be found in the SAC main database from Bern.', $objDisabledMember->firstname, $objDisabledMember->lastname, $objDisabledMember->sacMemberId);
            $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_DISABLE_MEMBER);
        }

        if ($i === count($arrMember))
        {
            $duration = \time() - $startTime;

            // Log
            $msg = 'Finished syncing SAC member database with tl_member. Synced ' . \count($arrMember) . ' entries. Duration: ' . $duration . ' s';
            $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC);
        }
    }

    /**
     * @param string $strLogLevel
     * @param string $strText
     * @param string $strMethod
     * @param string $strCategory
     */
    private function log(string $strLogLevel, string $strText, string $strMethod, string $strCategory): void
    {
        if ($this->logger !== null)
        {
            $this->logger->log(
                $strLogLevel,
                $strText,
                array('contao' => new ContaoContext($strMethod, $strCategory))
            );
        }
    }
}
