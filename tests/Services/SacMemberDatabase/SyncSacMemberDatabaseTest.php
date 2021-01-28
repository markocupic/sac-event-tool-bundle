<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Services\SacMemberDatabase;

use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
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
     * Delete temporary files and folders.
     */
    public static function tearDownAfterClass(): void
    {
        // The temporary directory would not be removed without this call!
        parent::tearDownAfterClass();
    }

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        // Get the root dir
        $this->rootDir = __DIR__.'/../../../../../../';
        $this->connection = $this->createMock(Connection::class);
        $this->framework = $this->mockContaoFramework();
        $this->arrSectionIds = explode(',', $GLOBALS['TL_CONFIG']['SAC_EVT_SAC_SECTION_IDS']);
        $this->hostname = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'];
        $this->username = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'];
        $this->password = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD'];
        $this->_getFtpConnectionParams();
    }

    /**
     * Test if SyncSacMemberDatabase is correctly instantiated.
     */
    public function testClassInstantiation(): void
    {
        $objDatabaseSync = new SyncSacMemberDatabase($this->framework, $this->connection, $this->rootDir);
        $this->assertInstanceOf(SyncSacMemberDatabase::class, $objDatabaseSync);
    }

    /**
     * @depends testClassInstantiation
     */
    public function testFtpConnection(): void
    {
        $this->assertTrue(!empty($this->hostname));
        $this->assertTrue(!empty($this->username));
        $this->assertTrue(!empty($this->password));
        $this->assertTrue(!empty($this->arrSectionIds));

        $objDatabaseSync = new SyncSacMemberDatabase($this->framework, $this->connection, $this->rootDir, $this->hostname, $this->username, $this->password, $this->arrSectionIds);

        // Make private method accessible
        $connId = $this->_invokeMethod($objDatabaseSync, 'openFtpConnection', []);

        $this->assertStringStartsWith('Resource id #', (string) $connId);
    }

    /**
     * @depends testFtpConnection
     */
    public function testFtpDownload(): void
    {
        $objDatabaseSync = new SyncSacMemberDatabase($this->framework, $this->connection, $this->rootDir);

        // Make private method accessible
        $connId = $this->_invokeMethod($objDatabaseSync, 'openFtpConnection', []);

        $filesystem = new Filesystem();
        $tempDir = $this->getTempDir().'/ftp';
        $filesystem->mkdir($tempDir);
        $arrSectionIds = $this->arrSectionIds;

        foreach ($arrSectionIds as $sectionId) {
            $localFile = $tempDir.'/Adressen_0000'.$sectionId.'.csv';
            $remoteFile = 'Adressen_0000'.$sectionId.'.csv';

            // Make private method accessible
            $invokedMethodResult = $this->_invokeMethod($objDatabaseSync, 'downloadFileFromFtp', [$connId, $localFile, $remoteFile]);

            $this->assertTrue($invokedMethodResult);
            $this->assertTrue(is_file($localFile));
        }
    }

    /**
     * Make private method accessible.
     *
     * @see https://jtreminio.com/2013/03/unit-testing-tutorial-part-3-testing-protected-private-methods-coverage-reports-and-crap/
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array  $parameters array of parameters to pass into method
     *
     * @return mixed method return
     */
    public function _invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(\get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Helper method.
     */
    private function _getFtpConnectionParams()
    {
        $container = $this->mockContainer();

        //$container = $this->mockContainer();
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../../src/Resources/config')
        );

        $loader->load('listener.yml');
        $loader->load('parameters.yml');
        $loader->load('services.yml');

        if (!empty($GLOBALS['TL_CONFIG']) && \is_array($GLOBALS['TL_CONFIG'])) {
            foreach ($GLOBALS['TL_CONFIG'] as $key => $value) {
                if (false !== strpos($key, 'SAC_EVT_')) {
                    if (!empty($value) && \is_array($value)) {
                        $container->setParameter($key, json_encode($value));
                    } else {
                        $container->setParameter($key, $value);
                    }
                }
            }
        }

        // FTP Credentials SAC Switzerland link: Daniel Fernandez Daniel.Fernandez@sac-cas.ch
        $this->arrSectionIds = explode(',', $GLOBALS['TL_CONFIG']['SAC_EVT_SAC_SECTION_IDS']);
        $this->hostname = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'];
        $this->username = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'];
        $this->password = $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD'];

        return $container;
    }
}
