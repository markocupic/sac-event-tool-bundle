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

use League\Csv\CharsetConverter;
use League\Csv\Writer;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles CSV file creation and response generation for download purposes.
 */
#[Exclude]
class CsvDownload
{
    private array $headline = [];

    private array $records = [];

    private string $delimiter;

    private string $enclosure;

    private string $newline;

    private bool $convertOutput = false;

    /**
     * @var string Can be utf-8, iso-8859-15, ...
     */
    private string $outputEncoding = 'UTF-8';

    private string|null $bom = "\xEF\xBB\xBF"; // UTF-8 BOM

    private array $headers = [
        'Content-Type' => 'application/vnd.ms-excel',
        'Cache-Control' => 'max-age=0',
    ];

    public function __construct(string $delimiter = ';', string $enclosure = '"', string $newline = "\r\n")
    {
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->newline = $newline;
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

    public function setRecords(array $records): self
    {
        $this->records = $records;

        return $this;
    }

    public function setOutputEncoding(string $outputEncoding): self
    {
        $this->outputEncoding = $outputEncoding;

        return $this;
    }

    public function setBom(string $bom): self
    {
        $this->bom = $bom;

        return $this;
    }

    public function removeBom(): self
    {
        $this->bom = null;

        return $this;
    }

    public function convertOutput(string $outputEncoding): self
    {
        $this->convertOutput = true;
        $this->outputEncoding = $outputEncoding;

        return $this;
    }

    public function createResponse(string $filename, int $status = 200, $headers = []): StreamedResponse
    {
        $csv = $this->createCsvWriter();

        $csv->insertAll(array_merge($this->headline, $this->records));

        $response = new StreamedResponse(
            static function () use ($csv, $filename): void {
                $csv->download($filename);
            },
            $status,
            $headers,
        );

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
        );

        foreach ($this->headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        $response->headers->set('Content-Disposition', $disposition);

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    private function createCsvWriter(): Writer
    {
        $csv = Writer::createFromString();

        if ($this->convertOutput) {
            $encoder = (new CharsetConverter())
                ->outputEncoding($this->outputEncoding)
            ;

            $csv->addFormatter($encoder);
        }

        if ($this->bom) {
            $csv->setOutputBOM($this->bom);
        }

        $csv->setDelimiter($this->delimiter);
        $csv->setEnclosure($this->enclosure);
        $csv->setEndOfLine($this->newline);

        return $csv;
    }
}
