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

namespace Markocupic\SacEventToolBundle\Services\Docx;

use Contao\TestCase\ContaoTestCase;
use PhpOffice\PhpWord\CreateDocxFromTemplate;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class CreateDocxFromTemplateTest extends ContaoTestCase
{
    public function testCanBeInstantiated(): void
    {
        $this->expectException(FileNotFoundException::class);
        CreateDocxFromTemplate::create(['foo' => 'bar'], 'bar', 'foo');
    }

    /**
     * Tests if the method can be called.
     */
    public function testSendToBrowserCanBeCalled(): void
    {
        // Create a stub for the SomeClass class.
        $stub = $this->createMock(CreateDocxFromTemplate::class);

        // Configure the stub.
        $stub
            ->method('sendToBrowser')
            ->willReturn(null)
        ;

        // Calling $stub->convert() will now return
        // null
        $this->assertNull($stub->generate());
    }

    /**
     * Tests if the method can be called.
     */
    public function testGenerateUncachedCanBeCalled(): void
    {
        // Create a stub for the SomeClass class.
        $stub = $this->createMock(CreateDocxFromTemplate::class);

        // Configure the stub.
        $stub
            ->method('generateUncached')
            ->willReturn(null)
        ;

        // Calling $stub->generate() will now return
        // null
        $this->assertNull($stub->generate());
    }

    /**
     * Tests if the method can be called.
     */
    public function testGenerateCanBeCalled(): void
    {
        // Create a stub for the SomeClass class.
        $stub = $this->createMock(CreateDocxFromTemplate::class);

        // Configure the stub.
        $stub
            ->method('generate')
            ->willReturn(null)
        ;

        // Calling $stub->convert() will now return
        // null
        $this->assertNull($stub->generate());
    }
}
