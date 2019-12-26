<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\Services\SacMemberDatabase;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\File;
use Contao\System;
use Doctrine\DBAL\Connection;
use Psr\Log\LogLevel;

/**
 * Class SyncSacMemberDatabase
 * @package Markocupic\SacEventToolBundle\Services\SacMemberDatabase
 */
class SyncSacMemberDatabase
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Connection
     */
    private $connection;

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
     * @param Connection $connection
     * @param $projectDir
     */
    public function __construct(ContaoFramework $framework, Connection $connection, $projectDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->projectDir = $projectDir;

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $this->ftp_hostname = $configAdapter->get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME');
        $this->ftp_username = (string)$configAdapter->get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME');
        $this->ftp_password = (string)$configAdapter->get('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD');
        $this->section_ids = explode(',', $configAdapter->get('SAC_EVT_SAC_SECTION_IDS'));
    }

    /**
     * @throws \Exception
     */
    public function run()
    {
        $this->loadFilesFtp();
        $this->syncContaoDatabase();
    }

    /**
     * @throws \Exception
     */
    private function loadFilesFtp(): void
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

            $this->log(LogLevel::CRITICAL, $msg, __METHOD__, ContaoContext::ERROR);
            throw new \Exception($msg);
        }

        return $connId;
    }

    /**
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     */
    private function syncContaoDatabase(): void
    {
        $startTime = \time();
        sleep(2);
        $statement = $this->connection->query('SELECT sacMemberId FROM tl_member');
        $arrMemberIDS = $statement->fetchAll(\PDO::FETCH_COLUMN);

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
                    $set['sectionId'] = array((string)ltrim($arrLine[1], '0'));
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

        // Set tl_member.isSacMember to empty string
        $this->connection->executeUpdate('UPDATE tl_member SET isSacMember = ?', array(''));

        // Start transaction (big thank to cyon.ch)
        $this->connection->beginTransaction();
        try
        {
            $i = 0;
            foreach ($arrMember as $sacMemberId => $arrValues)
            {
                $arrValues['sectionId'] = \serialize($arrValues['sectionId']);

                if (!in_array($sacMemberId, $arrMemberIDS))
                {
                    // Add new user
                    $this->connection->insert('tl_member', $arrValues);

                    // Log
                    $msg = \sprintf('Insert new SAC-member "%s %s" with SAC-User-ID: %s to tl_member.', $arrValues['firstname'], $arrValues['lastname'], $arrValues['sacMemberId']);
                    $this->log(LogLevel::INFO, $msg, __METHOD__, self::SAC_EVT_LOG_ADD_NEW_MEMBER);
                }
                else
                {
                    // Sync datarecord
                    $this->connection->update('tl_member', $arrValues, array('sacMemberId' => $sacMemberId));
                }

                $i++;
            }
            $this->connection->commit();
        } catch (\Exception $e)
        {
            $msg = 'Error during the database sync process. Starting transaction rollback, now.';
            $this->log(LogLevel::ERROR, $msg, __METHOD__, self::SAC_EVT_LOG_SAC_MEMBER_DATABASE_TRANSACTION_ERROR);

            //transaction rollback
            $this->connection->rollBack();

            // Throw exception
            throw $e;
        }

        // Set tl_member.disable to true if member was not found in the csv-file
        $statement = $this->connection->executeQuery('SELECT * FROM tl_member WHERE disable=? AND isSacMember=?', array('', ''));
        while (false !== ($objDisabledMember = $statement->fetch(\PDO::FETCH_OBJ)))
        {
            $arrSet = array(
                'tstamp'  => \time(),
                'disable' => '1',
            );
            $this->connection->update('tl_member', $arrSet, array('id' => $objDisabledMember->id));

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
        $logger = System::getContainer()->get('monolog.logger.contao');
        $logger->log(
            $strLogLevel,
            $strText,
            array('contao' => new ContaoContext($strMethod, $strCategory))
        );
    }
}
