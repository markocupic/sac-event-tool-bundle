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

namespace Markocupic\SacEventToolBundle\Download;

use League\Csv\Bom;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Represents a utility class for generating and downloading CSV files. Provides
 * functionality to configure CSV writer settings, manage records, and create HTTP
 * responses for CSV download.
 */
#[Exclude]
class CsvDownload
{
    public const string BOM_UTF8 = Bom::Utf8->value;

    public const string ENCODING_UTF8 = 'UTF-8';

    public const string ENCODING_ISO_8859_1 = 'ISO-8859-1'; // Latin

    private const int FLUSH_THRESHOLD = 1024;

    private string $outputEncoding = self::ENCODING_UTF8;

    private array $headline = [];

    private array $records = [];

    private Writer $writer;

    private array $headers = [
        'Cache-Control' => 'max-age=0',
    ];

    public function __construct(string $delimiter = ';', string $enclosure = '"', string $endOfLine = "\r\n")
    {
        $this->createWriter();
        $this->writer->setEndOfLine($endOfLine);
        $this->writer->setEnclosure($enclosure);
        $this->writer->setDelimiter($delimiter);
    }

    public function getOutputEncoding(): string
    {
        return $this->outputEncoding;
    }

    public function setOutputEncoding(string $outputEncoding): self
    {
        $this->outputEncoding = $outputEncoding;

        return $this;
    }

    public function setHeadline(array $headline): self
    {
        $this->headline[0] = $headline;

        return $this;
    }

    public function addRecord(array $record): self
    {
        $this->records[] = $record;

        return $this;
    }

    public function getRecords(bool $includeHeadline): array
    {
        if ($includeHeadline) {
            return array_merge($this->headline, $this->records);
        }

        return $this->records;
    }

    public function setRecords(array $records): self
    {
        $this->records = $records;

        return $this;
    }

    public function forceEnclosure(): self
    {
        $this->writer->forceEnclosure();

        return $this;
    }

    public function necessaryEnclosure(): self
    {
        $this->writer->necessaryEnclosure();

        return $this;
    }

    public function noEnclosure(): self
    {
        $this->writer->noEnclosure();

        return $this;
    }

    public function configureWriter(callable $callback): self
    {
        $callback($this->writer);

        return $this;
    }

    public function convertOutputEncoding(string $outputEncoding): self
    {
        if (!$this->isValidOutputEncoding($outputEncoding)) {
            throw new \InvalidArgumentException(\sprintf('Invalid output encoding "%s". Use one of these: %s', $outputEncoding, implode(', ', mb_list_encodings())));
        }

        $encoder = (new CharsetConverter())
            ->outputEncoding($outputEncoding)
        ;

        $this->setOutputEncoding($outputEncoding);

        $this->writer->addFormatter($encoder);

        return $this;
    }

    public function setOutputBOM(string $bom): self
    {
        $this->writer->setOutputBOM($bom);

        return $this;
    }

    public function createResponse(string $filename, int $status = 200, $headers = []): StreamedResponse
    {
        $this->writer->insertAll($this->getRecords(true));

        $response = new StreamedResponse();
        $this->configureResponseHeaders($response, $filename, $headers);
        $response->setCallback($this->createContentCallback());

        return $response->send();
    }

    private function createContentCallback(): callable
    {
        return function (): void {
            foreach ($this->writer->chunk(self::FLUSH_THRESHOLD) as $offset => $chunk) {
                echo $chunk;
                if (0 === $offset % self::FLUSH_THRESHOLD) {
                    flush();
                }
            }
        };
    }

    private function createWriter(): void
    {
        $this->writer = Writer::from('php://temp', 'r+');
    }

    private function isValidOutputEncoding(string $encoding): bool
    {
        return \in_array($encoding, mb_list_encodings(), true);
    }

    private function configureResponseHeaders(StreamedResponse $response, string $filename, array $customHeaders): void
    {
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
        );

        foreach ($this->headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Set the content type
        $contentType = "text/csv; charset=$this->outputEncoding";
        $response->headers->set('Content-Type', $contentType);

        $response->headers->set('Content-Disposition', $disposition);

        foreach ($customHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }
    }
}
