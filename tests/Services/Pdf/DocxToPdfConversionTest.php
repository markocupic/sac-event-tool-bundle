<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Services\Pdf;

use Contao\TestCase\ContaoTestCase;


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
        $dtpc = DocxToPdfConversion::create('foo', 'bar');

        $this->assertInstanceOf($this->instanceOf, $dtpc);
    }

    /**
     * Tests if the method can be called.
     */
    public function testSendToBrowserCanBeCalled()
    {
        $dtpc = DocxToPdfConversion::create('foo', 'bar')->sendToBrowser(true);
        $this->assertInstanceOf($this->instanceOf, $dtpc);
    }

    /**
     * Tests if the method can be called.
     */
    public function testCreateUncachedCanBeCalled()
    {
        $dtpc = DocxToPdfConversion::create('foo', 'bar')->createUncached(true);
        $this->assertInstanceOf($this->instanceOf, $dtpc);
    }

    /**
     * Tests if the method can be called.
     */
    public function testConvertCanBeCalled()
    {
        // Create a stub for the SomeClass class.
        $stub = $this->createMock(DocxToPdfConversion::class);

        // Configure the stub.
        $stub->method('convert')
            ->willReturn(null);

        // Calling $stub->convert() will now return
        // null
        $this->assertNull($stub->convert());

    }

}
