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
use Markocupic\SacEventToolBundle\String\Formatter\PhoneNumberFormatter;
use PHPUnit\Framework\TestCase;

class CsvMemberDtoTest extends TestCase
{
    public function testFromCsvMapsAllFields(): void
    {
        $dto = CsvMemberDto::fromCsv($this->baseLine());

        $this->assertSame(123456, $dto->sacMemberId);
        $this->assertSame('123456', $dto->username);
        $this->assertSame(['4250'], $dto->sectionId);
        $this->assertSame('Hans', $dto->firstname);
        $this->assertSame('Müller', $dto->lastname);
        $this->assertSame('c/o Firma', $dto->addressExtra);
        $this->assertSame('Musterstrasse 1', $dto->street);
        $this->assertSame('Postfach 5', $dto->poBox);
        $this->assertSame('6003', $dto->postal);
        $this->assertSame('Luzern', $dto->city);
        $this->assertSame('CH', $dto->country);
        $this->assertSame((string) strtotime('1990-05-20'), $dto->dateOfBirth);
        $this->assertSame(PhoneNumberFormatter::format('041 123 45 67'), $dto->phone);
        $this->assertSame(PhoneNumberFormatter::format('079 987 12 34'), $dto->mobile);
        $this->assertSame('hans@example.ch', $dto->email);
        $this->assertSame('male', $dto->gender);
        $this->assertSame('Ingenieur', $dto->profession);
        $this->assertSame('de', $dto->language);
        $this->assertSame('2015', $dto->entryYear);
        $this->assertSame(0, $dto->honoraryMember);
        $this->assertSame('Einzel', $dto->membershipType);
        $this->assertSame('info1', $dto->sectionInfo1);
        $this->assertSame('info2', $dto->sectionInfo2);
        $this->assertSame('info3', $dto->sectionInfo3);
        $this->assertSame('0.00', $dto->debit);
    }

    /**
     * @dataProvider honoraryMemberProvider
     */
    public function testHonoraryMemberMapping(string $raw, int $expected): void
    {
        $line = $this->baseLine();
        $line[22] = $raw;

        $dto = CsvMemberDto::fromCsv($line);

        $this->assertSame($expected, $dto->honoraryMember);
    }

    public static function honoraryMemberProvider(): iterable
    {
        return [
            'yes' => ['Yes', 1],
            'no' => ['No', 0],
            'empty' => ['', 0],
        ];
    }

    public function testProfessionIsReadFromColumn27(): void
    {
        $line = $this->baseLine();
        $line[18] = 'ignored'; // upstream ":empty" column, must NOT be used
        $line[27] = 'Sekundarlehrer'; // real profession column

        $dto = CsvMemberDto::fromCsv($line);

        $this->assertSame('Sekundarlehrer', $dto->profession);
    }

    public function testSectionIdStripsLeadingZeros(): void
    {
        $line = $this->baseLine();
        $line[1] = '00001234';

        $dto = CsvMemberDto::fromCsv($line);

        $this->assertSame(['1234'], $dto->sectionId);
    }

    /**
     * @dataProvider genderProvider
     */
    public function testGenderMapping(string $rawUtf8, string $expected): void
    {
        $line = $this->baseLine();
        $line[17] = $this->latin1($rawUtf8);

        $dto = CsvMemberDto::fromCsv($line);

        $this->assertSame($expected, $dto->gender);
    }

    public static function genderProvider(): iterable
    {
        return [
            'male' => ['Männlich', 'male'],
            'female' => ['Weiblich', 'female'],
            'unknown value' => ['Divers', 'other'],
            'empty' => ['', 'other'],
        ];
    }

    /**
     * @dataProvider countryProvider
     */
    public function testCountryNormalization(string $raw, string $expected): void
    {
        $line = $this->baseLine();
        $line[9] = $raw;

        $dto = CsvMemberDto::fromCsv($line);

        $this->assertSame($expected, $dto->country);
    }

    public static function countryProvider(): iterable
    {
        return [
            'empty falls back to default' => ['', 'CH'],
            'lowercase is upcased' => ['ch', 'CH'],
            'mixed case is upcased' => ['fr', 'FR'],
            'already upper' => ['DE', 'DE'],
        ];
    }

    public function testCustomDefaultCountryIsUsedWhenEmpty(): void
    {
        $line = $this->baseLine();
        $line[9] = '';

        $dto = CsvMemberDto::fromCsv($line, 'AT');

        $this->assertSame('AT', $dto->country);
    }

    /**
     * @dataProvider languageProvider
     */
    public function testLanguageMapping(string $raw, string $expected): void
    {
        $line = $this->baseLine();
        $line[19] = $raw;

        $dto = CsvMemberDto::fromCsv($line);

        $this->assertSame($expected, $dto->language);
    }

    public static function languageProvider(): iterable
    {
        return [
            'lowercase d maps to default locale' => ['d', 'de'],
            'uppercase D maps to default locale' => ['D', 'de'],
            'french stays' => ['f', 'f'],
            'italian stays' => ['i', 'i'],
            'uppercase is lowered' => ['F', 'f'],
        ];
    }

    public function testCustomDefaultLocaleIsUsedForGerman(): void
    {
        $line = $this->baseLine();
        $line[19] = 'd';

        $dto = CsvMemberDto::fromCsv($line, 'CH', 'gsw');

        $this->assertSame('gsw', $dto->language);
    }

    public function testEmptyBirthdayResultsInEmptyString(): void
    {
        $line = $this->baseLine();
        $line[10] = '';

        $dto = CsvMemberDto::fromCsv($line);

        $this->assertSame('', $dto->dateOfBirth);
    }

    public function testLatin1ValuesAreConvertedToUtf8(): void
    {
        $line = $this->baseLine();
        $line[8] = $this->latin1('Zürich');

        $dto = CsvMemberDto::fromCsv($line);

        $this->assertSame('Zürich', $dto->city);
    }

    public function testValuesAreTrimmed(): void
    {
        $line = $this->baseLine();
        $line[5] = '  Bahnhofstrasse 3  ';

        $dto = CsvMemberDto::fromCsv($line);

        $this->assertSame('Bahnhofstrasse 3', $dto->street);
    }

    public function testToArrayContainsAllProperties(): void
    {
        $dto = CsvMemberDto::fromCsv($this->baseLine());

        $arr = $dto->toArray();

        $this->assertCount(25, $arr);
        $this->assertSame(123456, $arr['sacMemberId']);
        $this->assertSame('Müller', $arr['lastname']);
        $this->assertSame(['4250'], $arr['sectionId']);
        $this->assertSame('Postfach 5', $arr['poBox']);
    }

    /**
     * A representative SAC CSV export line. String values are ISO-8859-1 encoded,
     * just like the real dump. Columns 18 and 29 are empty in the real export; the
     * profession is delivered in column 27.
     *
     * @return array<int, string>
     */
    private function baseLine(): array
    {
        return [
            0 => '123456',
            1 => '00004250',
            2 => $this->latin1('Müller'),
            3 => 'Hans',
            4 => 'c/o Firma',
            5 => '  Musterstrasse 1  ',
            6 => 'Postfach 5',
            7 => '6003',
            8 => 'Luzern',
            9 => '',
            10 => '1990-05-20',
            11 => '',
            12 => '041 123 45 67',
            13 => '',
            14 => '079 987 12 34',
            15 => '',
            16 => 'hans@example.ch',
            17 => $this->latin1('Männlich'),
            18 => '',
            19 => 'd',
            20 => '2015',
            21 => '',
            22 => '',
            23 => 'Einzel',
            24 => 'info1',
            25 => 'info2',
            26 => 'info3',
            27 => 'Ingenieur',
            28 => '0.00',
            29 => '',
        ];
    }

    /**
     * Simulates the ISO-8859-1 byte representation the real CSV dump contains.
     */
    private function latin1(string $utf8): string
    {
        return mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');
    }
}
