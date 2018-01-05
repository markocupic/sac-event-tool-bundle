<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 * @link https://jtreminio.com/2013/03/unit-testing-tutorial-introduction-to-phpunit/
 */

namespace Markocupic\SacEventToolBundle\Services\Pdf;

use Contao\TestCase\ContaoTestCase;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;


class DocxToPdfConversionTest extends ContaoTestCase
{
    /**
     * @var
     */
    protected $instanceOf;

    /**
     *
     */
    public function setUp()
    {
        $this->instanceOf = 'Markocupic\SacEventToolBundle\Services\Pdf\DocxToPdfConversion';
    }

    /**
     * Tests the object instantiation.
     */
    public function testCanBeInstantiated()
    {

        $this->expectException(FileNotFoundException::class);
        DocxToPdfConversion::create('foo', 'bar');
    }

    /**
     * Tests if the method can be called.
     */
    public function testSendToBrowserCanBeCalled()
    {

        // Create a stub for the DocxToPdfConversion class.
        $stub = $this->createMock(DocxToPdfConversion::class);

        // Configure the stub.
        $stub->method('sendToBrowser')
            ->willReturn(null);

        // Calling $stub->sendToBrowser() will now return
        // null
        $this->assertNull($stub->sendToBrowser());
    }

    /**
     * Tests if the method can be called.
     */
    public function testCreateUncachedCanBeCalled()
    {

        // Create a stub for the DocxToPdfConversion class.
        $stub = $this->createMock(DocxToPdfConversion::class);

        // Configure the stub.
        $stub->method('createUncached')
            ->willReturn(null);

        // Calling $stub->createUncached() will now return
        // null
        $this->assertNull($stub->createUncached());
    }

    /**
     * Tests if the method can be called.
     */
    public function testConvertCanBeCalled()
    {
        // Create a stub for the DocxToPdfConversion class.
        $stub = $this->createMock(DocxToPdfConversion::class);

        // Configure the stub.
        $stub->method('convert')
            ->willReturn(null);

        // Calling $stub->convert() will now return
        // null
        $this->assertNull($stub->convert());
    }

}
