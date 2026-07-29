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

namespace Markocupic\SacEventToolBundle\Tests\Download;

use Markocupic\SacEventToolBundle\Download\CsvDownload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvDownloadTest extends TestCase
{
    public function testCreateResponseReturnsANonStreamedResponse(): void
    {
        $response = (new CsvDownload())
            ->setRecords([['ID', 'Nachname']])
            ->createResponse('export.csv')
        ;

        // A plain Response has its body available up front (unlike a StreamedResponse).
        $this->assertSame(Response::class, $response::class);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateResponseRendersRowsWithSemicolonDelimiterAndCrlf(): void
    {
        $response = (new CsvDownload())
            ->setRecords([
                ['ID', 'Nachname', 'Vorname'],
                ['7', 'Ammann', 'Beat'],
            ])
            ->createResponse('export.csv')
        ;

        $body = $response->getContent();

        $this->assertStringContainsString("ID;Nachname;Vorname\r\n", $body);
        $this->assertStringContainsString("7;Ammann;Beat\r\n", $body);
    }

    public function testCreateResponsePrependsBomWhenConfigured(): void
    {
        $response = (new CsvDownload())
            ->setOutputBOM(CsvDownload::BOM_UTF8)
            ->setRecords([['ID', 'Nachname']])
            ->createResponse('export.csv')
        ;

        $this->assertStringStartsWith(CsvDownload::BOM_UTF8, $response->getContent());
    }

    public function testCreateResponseHasNoBomByDefault(): void
    {
        $response = (new CsvDownload())
            ->setRecords([['ID', 'Nachname']])
            ->createResponse('export.csv')
        ;

        $this->assertStringStartsNotWith(CsvDownload::BOM_UTF8, $response->getContent());
    }

    public function testCreateResponseSetsCsvDownloadHeaders(): void
    {
        $response = (new CsvDownload())
            ->setRecords([['ID', 'Nachname']])
            ->createResponse('export.csv')
        ;

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        // Symfony appends "private" to Cache-Control, so match the substring we set.
        $this->assertStringContainsString('max-age=0', $response->headers->get('Cache-Control'));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('export.csv', $disposition);
    }

    public function testCreateResponseHonoursCustomStatusAndHeaders(): void
    {
        $response = (new CsvDownload())
            ->setRecords([['ID', 'Nachname']])
            ->createResponse('export.csv', 201, ['X-Generated-By' => 'sac-event-tool'])
        ;

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('sac-event-tool', $response->headers->get('X-Generated-By'));
    }

    public function testCreateResponseReflectsTheConfiguredOutputEncodingInContentType(): void
    {
        $response = (new CsvDownload())
            ->setRecords([['ID', 'Nachname']])
            ->convertOutputEncoding(CsvDownload::ENCODING_ISO_8859_1)
            ->createResponse('export.csv')
        ;

        $this->assertSame('text/csv; charset=ISO-8859-1', $response->headers->get('Content-Type'));
    }

    public function testCreateStreamedResponseReturnsAStreamedResponse(): void
    {
        $response = (new CsvDownload())
            ->setRecords([['ID', 'Nachname']])
            ->createStreamedResponse('export.csv')
        ;

        $this->assertSame(StreamedResponse::class, $response::class);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateStreamedResponseStreamsTheSameRowsAsCreateResponse(): void
    {
        $response = (new CsvDownload())
            ->setRecords([
                ['ID', 'Nachname', 'Vorname'],
                ['7', 'Ammann', 'Beat'],
            ])
            ->createStreamedResponse('export.csv')
        ;

        $body = $this->captureStreamedContent($response);

        $this->assertStringContainsString("ID;Nachname;Vorname\r\n", $body);
        $this->assertStringContainsString("7;Ammann;Beat\r\n", $body);
    }

    public function testCreateStreamedResponsePrependsBomWhenConfigured(): void
    {
        $response = (new CsvDownload())
            ->setOutputBOM(CsvDownload::BOM_UTF8)
            ->setRecords([['ID', 'Nachname']])
            ->createStreamedResponse('export.csv')
        ;

        $this->assertStringStartsWith(CsvDownload::BOM_UTF8, $this->captureStreamedContent($response));
    }

    public function testCreateStreamedResponseSetsCsvDownloadHeaders(): void
    {
        $response = (new CsvDownload())
            ->setRecords([['ID', 'Nachname']])
            ->createStreamedResponse('export.csv')
        ;

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        // Symfony appends "private" to Cache-Control, so match the substring we set.
        $this->assertStringContainsString('max-age=0', $response->headers->get('Cache-Control'));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('export.csv', $disposition);
    }

    public function testCreateStreamedResponseHonoursCustomStatusAndHeaders(): void
    {
        $response = (new CsvDownload())
            ->setRecords([['ID', 'Nachname']])
            ->createStreamedResponse('export.csv', 201, ['X-Generated-By' => 'sac-event-tool'])
        ;

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('sac-event-tool', $response->headers->get('X-Generated-By'));
    }

    /**
     * Runs a StreamedResponse's output callback and returns what it would write to the client,
     * without sending any HTTP headers (sendContent() only, not send()).
     */
    private function captureStreamedContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
