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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

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

    // Create a stub for the SomeClass class.
    $stub = $this->createMock(SyncSacMemberDatabase::class);

    // Configure the stub.
    $stub->method('openFtpConnection')
    ->willReturn(null);
    // Calling $stub->convert() will now return null
    $this->assertNull($stub->openFtpConnection());
     /**
     * $container = $this->mockContainer();
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../../src/Resources/config')
        );

        $loader->load('listener.yml');
        $loader->load('parameters.yml');
        $loader->load('services.yml');

        $container = $this->_getFtpConnectionParams($container);


        $objDatabaseSync = $container->get('markocupic.sac_event_tool_bundle.sync_sac_member_database');

        // Check class instantiation
        $this->assertInstanceOf(SyncSacMemberDatabase::class, $objDatabaseSync);
        die();
      * **/
    }



    /**
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
        $container = $this->mockContainer();
        // Load the configuration file
        require_once $this->rootDir . '/sac_event_tool_parameters.php';

        //$container = $this->mockContainer();
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../../src/Resources/config')
        );

        $loader->load('listener.yml');
        $loader->load('parameters.yml');
        $loader->load('services.yml');

        if (!empty($GLOBALS['TL_CONFIG']) && is_array($GLOBALS['TL_CONFIG']))
        {
            foreach ($GLOBALS['TL_CONFIG'] as $key => $value)
            {
                if (strpos($key, 'SAC_EVT_') !== false)
                {
                    if (!empty($value) && is_array($value))
                    {
                        $container->setParameter($key, \json_encode($value));
                    }
                    else
                    {
                        $container->setParameter($key, $value);
                    }
                }
            }
        }

        // FTP Credentials SAC Switzerland link: Daniel Fernandez Daniel.Fernandez@sac-cas.ch
        $this->arrSectionIds = json_decode($container->getParameter('SAC_EVT_SAC_SECTION_IDS'));
        $this->hostname = $container->getParameter('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME');
        $this->username = $container->getParameter('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME');
        $this->password = $container->getParameter('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD');

        return $container;
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
