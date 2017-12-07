<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Services\SacMemberDatabase;


use Contao\System;
use Contao\File;
use Contao\Database;
use Contao\Date;


/**
 * Class SyncSacMemberDatabase
 * @package Markocupic\SacEventToolBundle\Services\SacMemberDatabase
 */
class SyncSacMemberDatabase
{


    /**
     * @var
     */
    private $sectionIds;

    /**
     * @var
     */
    private $ftp_hostname;

    /**
     * @var
     */
    private $ftp_username;

    /**
     * @var
     */
    private $ftp_password;


    /**
     * SyncSacMemberDatabase constructor.
     * @param $sectionIds
     * @param $ftp_hostname
     * @param $ftp_username
     * @param $ftp_password
     */
    public function __construct($sectionIds, $ftp_hostname, $ftp_username, $ftp_password)
    {
        $this->sectionIds = $sectionIds;
        $this->ftp_hostname = $ftp_hostname;
        $this->ftp_username = $ftp_username;
        $this->ftp_password = $ftp_password;
    }


    /**
     * @throws \Exception
     */
    public function loadDataFromFtp()
    {

        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');


        // Run once per day
        $objDbLog = Database::getInstance()->prepare('SELECT * FROM tl_log WHERE action=? ORDER BY tstamp DESC')->limit(1)->execute(SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC);
        if ($objDbLog->numRows)
        {
            if (Date::parse('Y-m-d', $objDbLog->tstamp) === Date::parse('Y-m-d', time()))
            {
                return;
            }
        }


        $connId = ftp_connect($this->ftp_hostname);
        ftp_login($connId, $this->ftp_username, $this->ftp_password);

        foreach ($this->sectionIds as $sectionId)
        {
            $localFile = $rootDir . '/system/tmp/Adressen_0000' . $sectionId . '.csv';
            $remoteFile = 'Adressen_0000' . $sectionId . '.csv';


            if (ftp_get($connId, $localFile, $remoteFile, FTP_BINARY))
            {
                // Write csv file to the tmp folder
            }
            else
            {
                System::log('Error during SAC member database sync. Could not open FTP connection.', __FILE__ . ' Line: ' . __LINE__, TL_ERROR);
                throw new \Exception("Tried to open FTP connection.");
            }
        }
        ftp_close($connId);
    }

    /**
     *
     */
    public function syncContaoDatabase()
    {
        $startTime = time();


        // Run once per day
        $objDbLog = Database::getInstance()->prepare('SELECT * FROM tl_log WHERE action=? ORDER BY tstamp DESC')->limit(1)->execute(SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC);
        if ($objDbLog->numRows)
        {
            if (Date::parse('Y-m-d', $objDbLog->tstamp) === Date::parse('Y-m-d', time()))
            {
                return;
            }
        }

        $objDb = Database::getInstance()->execute('SELECT sacMemberId FROM tl_member');
        $arrMemberIDS = $objDb->fetchEach('sacMemberId');

        $arrMember = array();
        foreach ($this->sectionIds as $sectionId)
        {

            $objFile = new File('system/tmp/Adressen_0000' . $sectionId . '.csv');
            if ($objFile !== null)
            {
                $arrFile = $objFile->getContentAsArray();
                foreach ($arrFile as $line)
                {
                    // End of line
                    if (strpos($line, '* * * Dateiende * * *') !== false)
                    {
                        continue;
                    }

                    $arrLine = explode('$', $line);
                    $set = array();
                    $set['sacMemberId'] = intval($arrLine[0]);
                    $set['username'] = intval($arrLine[0]);
                    // Mehrere Sektionsmitgliedschaften möglich
                    $set['sectionId'] = array(intval($arrLine[1]));
                    $set['lastname'] = $arrLine[2];
                    $set['firstname'] = $arrLine[3];
                    $set['addressExtra'] = $arrLine[4];
                    $set['street'] = trim($arrLine[5]);
                    $set['streetExtra'] = $arrLine[6];
                    $set['postal'] = $arrLine[7];
                    $set['city'] = $arrLine[8];
                    $set['country'] = strtolower($arrLine[9]) == '' ? 'ch' : strtolower($arrLine[9]);
                    $set['dateOfBirth'] = strtotime($arrLine[10]);
                    $set['phoneBusiness'] = $arrLine[11];
                    $set['phone'] = $arrLine[12];
                    $set['mobile'] = $arrLine[14];
                    $set['fax'] = $arrLine[15];
                    $set['email'] = $arrLine[16];
                    $set['gender'] = strtolower($arrLine[17]) == 'weiblich' ? 'female' : 'male';
                    $set['profession'] = $arrLine[18];
                    $set['language'] = strtolower($arrLine[19]) == 'd' ? 'de' : strtolower($arrLine[19]);
                    $set['entryYear'] = $arrLine[20];
                    $set['membershipType'] = $arrLine[23];
                    $set['sectionInfo1'] = $arrLine[24];
                    $set['sectionInfo2'] = $arrLine[25];
                    $set['sectionInfo3'] = $arrLine[26];
                    $set['sectionInfo4'] = $arrLine[27];
                    $set['debit'] = $arrLine[28];
                    $set['memberStatus'] = $arrLine[29];
                    $set['tstamp'] = time();
                    $set['disable'] = '';
                    $set['isSacMember'] = '1';


                    $set = array_map(function ($value) {
                        if (!is_array($value))
                        {
                            $value = trim($value);
                            return utf8_encode($value);
                        }
                        return $value;

                    }, $set);

                    // Check if the member is already in the array
                    if (isset($arrMember[$set['sacMemberId']]))
                    {
                        $arrMember[$set['sacMemberId']]['sectionId'] = array_merge($arrMember[$set['sacMemberId']]['sectionId'], $set['sectionId']);
                    }
                    else
                    {
                        $arrMember[$set['sacMemberId']] = $set;
                    }
                }
            }
        }

        // Set tl_member.isSacMember to ''
        Database::getInstance()->prepare('UPDATE tl_member SET isSacMember=?')->execute('');

        $i = 0;
        foreach ($arrMember as $sacMemberId => $arrValues)
        {
            $arrValues['sectionId'] = serialize($arrValues['sectionId']);
            if (!in_array($sacMemberId, $arrMemberIDS))
            {
                // Add new user
                Database::getInstance()->prepare('INSERT INTO tl_member %s')->set($arrValues)->execute();
                System::log(sprintf('Insert new SAC-member with SAC-User-ID: %s to tl_member.', $arrValues['sacMemberId']), __FILE__ . ' Line: ' . __LINE__, SAC_EVT_LOG_ADD_NEW_MEMBER);
            }
            else
            {
                // Sync datarecord
                Database::getInstance()->prepare('UPDATE tl_member %s WHERE sacMemberId=?')->set($arrValues)->execute($sacMemberId);
            }


            // Log, if sync has finished without errors (max script execution time!!!!)
            $i++;
            if ($i == count($arrMember))
            {
                $duration = time() - $startTime;

                // Log
                System::log('Finished syncing SAC member database with tl_member. Synced ' . count($arrMember) . ' entries. Duration: ' . $duration . ' s', __FILE__ . ' Line: ' . __LINE__, SAC_EVT_LOG_SAC_MEMBER_DATABASE_SYNC);
            }
        }

        // Set tl_member.disable to true if member was not found in the csv-file
        $objDisabledMember = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE disable=? AND isSacMember=?')->execute('', '');
        while ($objDisabledMember->next())
        {
            $set = array(
                'tstamp' => time(),
                'disable' => '1'
            );
            Database::getInstance()->prepare('UPDATE tl_member %s WHERE id=?')->set($set)->execute($objDisabledMember->id);

            // Log
            System::log(sprintf('Disable SAC-Member "%s %s" SAC-User-ID: %s during the sync process. The user can not be found in the SAC main database from Bern.', $objDisabledMember->firstname, $objDisabledMember->lastname, $objDisabledMember->sacMemberId), __FILE__ . ' Line: ' . __LINE__, SAC_EVT_LOG_DISABLE_MEMBER);
        }
    }
}