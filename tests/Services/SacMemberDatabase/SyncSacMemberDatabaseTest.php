<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Services\SacMemberDatabase;

use Contao\TestCase\ContaoTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Doctrine\DBAL\Connection;


class SyncSacMemberDatabaseTest extends ContaoTestCase
{
    /**
     * @var
     */
    private $rootDir;

    /**
     * @var array
     */
    private $arrSectionIds;

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
        $this->connection = $this->createMock(Connection::class);
        $this->framework = $this->mockContaoFramework();
        $this->_getFtpConnectionParams();
    }

    /**
     * Test if SyncSacMemberDatabase is correctly instantiated
     */
    public function testClassInstantiation()
    {

        $objDatabaseSync = new SyncSacMemberDatabase($this->framework, $this->connection, $this->rootDir, $this->hostname, $this->username, $this->password, $this->arrSectionIds);
        $this->assertInstanceOf(SyncSacMemberDatabase::class, $objDatabaseSync);
    }


    /**
     * @depends testClassInstantiation
     */
    public function testFtpConnection()
    {

        // Check for parameter file
        $this->assertTrue(\is_file($this->rootDir . '/sac_event_tool_parameters.php'));

        $this->assertTrue(!empty($this->hostname));
        $this->assertTrue(!empty($this->username));
        $this->assertTrue(!empty($this->password));
        $this->assertTrue(!empty($this->arrSectionIds));

        $objDatabaseSync = new SyncSacMemberDatabase($this->framework, $this->connection, $this->rootDir, $this->hostname, $this->username, $this->password, $this->arrSectionIds);

        // Make private method accessible
        $connId = $this->_invokeMethod($objDatabaseSync, 'openFtpConnection', array());

        $this->assertStringStartsWith('Resource id #', (string) $connId);

    }

    /**
     * @depends testFtpConnection
     */
    public function testFtpDownload()
    {

        $objDatabaseSync = new SyncSacMemberDatabase($this->framework, $this->connection, $this->rootDir, $this->hostname, $this->username, $this->password, $this->arrSectionIds);

        // Make private method accessible
        $connId = $this->_invokeMethod($objDatabaseSync, 'openFtpConnection', array());

        $filesystem = new Filesystem();
        $tempDir = $this->getTempDir() . '/ftp';
        $filesystem->mkdir($tempDir);
        $arrSectionIds = json_decode($this->arrSectionIds);
        foreach ($arrSectionIds as $sectionId)
        {
            $localFile = $tempDir . '/Adressen_0000' . $sectionId . '.csv';
            $remoteFile = 'Adressen_0000' . $sectionId . '.csv';

            // Make private method accessible
            $invokedMethodResult = $this->_invokeMethod($objDatabaseSync, 'downloadFileFromFtp', array($connId, $localFile, $remoteFile));

            $this->assertTrue($invokedMethodResult);
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
        $this->arrSectionIds = $container->getParameter('SAC_EVT_SAC_SECTION_IDS');
        $this->hostname = $container->getParameter('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME');
        $this->username = $container->getParameter('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME');
        $this->password = $container->getParameter('SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD');

        return $container;
    }

    /**
     * Delete temporary files and folders
     */
    public static function tearDownAfterClass(): void
    {

        // The temporary directory would not be removed without this call!
        parent::tearDownAfterClass();
    }

    /**
     * Make private method accessible

     * @link https://jtreminio.com/2013/03/unit-testing-tutorial-part-3-testing-protected-private-methods-coverage-reports-and-crap/
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function _invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
