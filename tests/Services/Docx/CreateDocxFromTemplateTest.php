<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 27.12.2017
 * Time: 15:28
 */

namespace Markocupic\SacEventToolBundle\Services\Docx;

use PhpOffice\PhpWord\CreateDocxFromTemplate;
use Contao\TestCase\ContaoTestCase;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;


class CreateDocxFromTemplateTest extends ContaoTestCase
{

    /**
     *
     */
    public function testCanBeInstantiated()
    {
        $this->expectException(FileNotFoundException::class);
        CreateDocxFromTemplate::create(array('foo' => 'bar'), 'bar','foo');
    }

    /**
     * Tests if the method can be called.
     */
    public function testSendToBrowserCanBeCalled()
    {
        // Create a stub for the SomeClass class.
        $stub = $this->createMock(CreateDocxFromTemplate::class);

        // Configure the stub.
        $stub->method('sendToBrowser')
            ->willReturn(null);

        // Calling $stub->convert() will now return
        // null
        $this->assertNull($stub->generate());

    }

    /**
     * Tests if the method can be called.
     */
    public function testGenerateUncachedCanBeCalled()
    {
        // Create a stub for the SomeClass class.
        $stub = $this->createMock(CreateDocxFromTemplate::class);

        // Configure the stub.
        $stub->method('generateUncached')
            ->willReturn(null);

        // Calling $stub->generate() will now return
        // null
        $this->assertNull($stub->generate());

    }


    /**
     * Tests if the method can be called.
     */
    public function testGenerateCanBeCalled()
    {
        // Create a stub for the SomeClass class.
        $stub = $this->createMock(CreateDocxFromTemplate::class);

        // Configure the stub.
        $stub->method('generate')
            ->willReturn(null);

        // Calling $stub->convert() will now return
        // null
        $this->assertNull($stub->generate());

    }

}