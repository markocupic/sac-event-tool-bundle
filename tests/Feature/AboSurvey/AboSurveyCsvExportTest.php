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

namespace Markocupic\SacEventToolBundle\Tests\Feature\AboSurvey;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyAboType;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyCsvExport;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AboSurveyCsvExportTest extends TestCase
{
    public function testExportsTheEntriesWithLabels(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'year' => 2027,
                    'aboType' => AboSurveyAboType::WHITE_RISK,
                    'sacMemberId' => 123456,
                    'firstname' => 'Hans',
                    'lastname' => 'Muster',
                    'email' => 'hans@example.com',
                    'street' => 'Bergweg 1',
                    'postal' => '6000',
                    'city' => 'Luzern',
                    'dateAdded' => mktime(14, 30, 0, 11, 2, 2026),
                ],
            ])
        ;

        $content = $this->createExport($connection)->generate()->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = explode("\r\n", trim(substr($content, 3)));

        $this->assertCount(2, $lines);
        $this->assertSame('label:year;label:aboType;label:sacMemberId;label:firstname;label:lastname;label:email;label:street;label:postal;label:city;label:dateAdded', $lines[0]);
        $this->assertSame('2027;"White Risk";123456;Hans;Muster;hans@example.com;"Bergweg 1";6000;Luzern;"02.11.2026 14:30"', $lines[1]);
    }

    public function testAppliesTheFilter(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('s.year = ?'),
                    $this->stringContains('s.aboType = ?'),
                ),
                [2027, AboSurveyAboType::SCHWEIZ_MOBIL],
            )
            ->willReturn([])
        ;

        $response = $this->createExport($connection)->generate(2027, AboSurveyAboType::SCHWEIZ_MOBIL);

        $this->assertStringContainsString('abo_umfrage_2027_', (string) $response->headers->get('Content-Disposition'));
    }

    public function testWithoutFilterAllEntriesAreExported(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->logicalNot($this->stringContains('s.year = ?')), [])
            ->willReturn([])
        ;

        $response = $this->createExport($connection)->generate();

        $this->assertStringContainsString('abo_umfrage_alle_', (string) $response->headers->get('Content-Disposition'));
    }

    private function createExport(Connection $connection): AboSurveyCsvExport
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(
                static fn (string $id): string => match ($id) {
                    'tl_abo_survey.'.AboSurveyAboType::WHITE_RISK => 'White Risk',
                    'tl_abo_survey.'.AboSurveyAboType::SCHWEIZ_MOBIL => 'SchweizMobil',
                    default => 'label:'.explode('.', $id)[1],
                },
            )
        ;

        return new AboSurveyCsvExport($connection, $translator);
    }
}
