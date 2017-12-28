<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Services\SacMemberDatabase;

use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Services\SacMemberDatabase;
use Symfony\Component\Filesystem\Filesystem;

class SyncSacMemberDatabaseTest extends ContaoTestCase
{
    /**
     * @var
     */
    private $rootDir;

    /**
     * @var array
     */
    private $arrSectionIds = [];

    /**
     * @var
     */
    private $hostname;

    /**
     * @var
     */
    private $username;

    /**
     * @var
     */
    private $password;

    /**
     * This method is called before each test
     */
    public function setUp()
    {
        // Get the root dir
        $this->rootDir = __DIR__ . '/../../../../../../';
    }

    /**
     * Test if SyncSacMemberDatabase is correctly instantiated
     */
    public function testClassInstantiation()
    {
        $objDatabaseSync = new SyncSacMemberDatabase(array('999', '888'), 'someHost', 'someUser', 'somePassword');

        // Check class instantiation
        $this->assertInstanceOf(SyncSacMemberDatabase::class, $objDatabaseSync);
    }


    /**
     * @depends testClassInstantiation
     */
    public function testFtpConnection()
    {
        // Check for parameter file
        $this->assertTrue(\is_file($this->rootDir . '/sac_event_tool_parameters.php'));

        $this->_getFtpConnectionParams();
        $this->assertTrue(!empty($this->hostname));
        $this->assertTrue(!empty($this->username));
        $this->assertTrue(!empty($this->password));
        $this->assertTrue(!empty($this->arrSectionIds));

        $objDatabaseSync = new SyncSacMemberDatabase($this->arrSectionIds, $this->hostname, $this->username, $this->password);
        $connId = $objDatabaseSync->openFtpConnection();
        $this->assertStringStartsWith('Resource id #', (string)$connId);

    }

    /**
     * @depends testFtpConnection
     */
    public function testFtpDownload()
    {

        $this->_getFtpConnectionParams();

        $objDatabaseSync = new SyncSacMemberDatabase($this->arrSectionIds, $this->hostname, $this->username, $this->password);
        $connId = $objDatabaseSync->openFtpConnection();

        $fs = new Filesystem();
        $tempDir = $this->getTempDir() . '/ftp';
        $fs->mkdir($tempDir);
        foreach ($this->arrSectionIds as $sectionId)
        {
            $localFile = $tempDir . '/Adressen_0000' . $sectionId . '.csv';
            $remoteFile = 'Adressen_0000' . $sectionId . '.csv';
            $this->assertTrue($objDatabaseSync->loadFileFromFtp($connId, $localFile, $remoteFile));
            $this->assertTrue(\is_file($localFile));
        }
    }

    /**
     * Helper method
     */
    private function _getFtpConnectionParams()
    {
        require_once $this->rootDir . '/sac_event_tool_parameters.php';

        // FTP Credentials SAC Switzerland link: Daniel Fernandez Daniel.Fernandez@sac-cas.ch
        $this->arrSectionIds = $GLOBALS['TL_CONFIG']['SAC_EVT_SAC_SECTION_IDS'];
        $this->hostname = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'];
        $this->username = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'];
        $this->password = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD'];
    }

    /**
     * Delete temprary files and folders
     */
    public static function tearDownAfterClass(): void
    {
        // The temporary directory would not be removed without this call!
        parent::tearDownAfterClass();
    }
}
