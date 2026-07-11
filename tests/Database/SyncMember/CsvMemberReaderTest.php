<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Tests\Database\SyncMember;

use Markocupic\SacEventToolBundle\Database\SyncMember\CsvMemberDto;
use Markocupic\SacEventToolBundle\Database\SyncMember\CsvMemberReader;
use PHPUnit\Framework\TestCase;

class CsvMemberReaderTest extends TestCase
{
    public function testReadStreamYieldsDtoForEveryValidLine(): void
    {
        $content = implode("\n", [
            $this->line([0 => '111111', 1 => '00004250']),
            $this->line([0 => '222222', 1 => '00003000']),
        ]);

        $dtos = $this->read($content);

        $this->assertCount(2, $dtos);
        $this->assertContainsOnlyInstancesOf(CsvMemberDto::class, $dtos);
        $this->assertSame(111111, $dtos[0]->sacMemberId);
        $this->assertSame(222222, $dtos[1]->sacMemberId);
        $this->assertSame(['4250'], $dtos[0]->sectionId);
    }

    public function testReadStreamReturnsAGenerator(): void
    {
        $reader = new CsvMemberReader();
        $stream = $this->streamFromString($this->line());

        $result = $reader->readStream($stream, 'de', 'CH');

        $this->assertInstanceOf(\Generator::class, $result);

        fclose($stream);
    }

    /**
     * @dataProvider skippableFirstFieldProvider
     */
    public function testReadStreamSkipsInvalidMemberIds(string $rawId): void
    {
        $content = implode("\n", [
            $this->line([0 => $rawId]),
            $this->line([0 => '999999']),
        ]);

        $dtos = $this->read($content);

        $this->assertCount(1, $dtos);
        $this->assertSame(999999, $dtos[0]->sacMemberId);
    }

    public static function skippableFirstFieldProvider(): iterable
    {
        return [
            'empty' => [''],
            'zero' => ['0'],
            'negative' => ['-5'],
            'non numeric' => ['abc'],
        ];
    }

    public function testReadStreamSkipsBlankLines(): void
    {
        $content = implode("\n", [
            $this->line([0 => '111111']),
            '',
            '   ',
            $this->line([0 => '222222']),
        ]);

        $dtos = $this->read($content);

        $this->assertCount(2, $dtos);
    }

    public function testReadStreamHandlesAnEmptyStream(): void
    {
        $this->assertSame([], $this->read(''));
    }

    public function testDefaultCountryAndLocaleArePassedToTheDto(): void
    {
        // Empty country field (index 9) -> falls back to the given default country.
        // German language field (index 19 = 'd') -> falls back to the given default locale.
        $content = $this->line([9 => '', 19 => 'd']);

        $dtos = $this->read($content, 'gsw', 'LI');

        $this->assertCount(1, $dtos);
        $this->assertSame('LI', $dtos[0]->country);
        $this->assertSame('gsw', $dtos[0]->language);
    }

    public function testReadStreamThrowsOnNonResource(): void
    {
        $reader = new CsvMemberReader();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream must be a valid resource.');

        // The generator body only runs once it is iterated.
        iterator_to_array($reader->readStream('not-a-resource', 'de', 'CH'));
    }

    /**
     * Reads the given CSV content from an in-memory stream and returns the DTOs.
     *
     * @return array<int, CsvMemberDto>
     */
    private function read(string $content, string $defaultLocale = 'de', string $defaultCountry = 'CH'): array
    {
        $reader = new CsvMemberReader();
        $stream = $this->streamFromString($content);

        try {
            return iterator_to_array($reader->readStream($stream, $defaultLocale, $defaultCountry), false);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return resource
     */
    private function streamFromString(string $content)
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /**
     * Builds a single CSV line (30 fields) using the reader's '$' delimiter.
     *
     * @param array<int, string> $overrides
     */
    private function line(array $overrides = []): string
    {
        $fields = array_fill(0, 30, '');
        $fields[0] = '123456'; // member id
        $fields[1] = '00004250'; // section id
        $fields[2] = 'Muster'; // last name
        $fields[3] = 'Hans'; // first name
        $fields[9] = 'CH'; // country
        $fields[16] = 'hans@example.ch';
        $fields[19] = 'de'; // language

        foreach ($overrides as $index => $value) {
            $fields[$index] = $value;
        }

        return implode(CsvMemberReader::DELIMITER, $fields);
    }
}
