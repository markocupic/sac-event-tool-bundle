<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 25.12.2017
 * Time: 13:10
 */

namespace Markocupic\SacEventToolBundle;


use Contao\TestCase\ContaoTestCase;


use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * Class PreparePluginEnvironmentTest
 * @package Markocupic\SacEventToolBundle
 */
class PreparePluginEnvironmentTest extends ContaoTestCase
{

    /**
     * My first unit test
     */
    public function testGetName()
    {
        $framework = $this->mockContaoFramework();
        $objPluginEnv =  new PreparePluginEnvironment($framework);

        $this->assertEquals('bla',  $objPluginEnv->getName('bla'));

    }
}
