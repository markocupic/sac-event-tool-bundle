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

namespace Markocupic\SacEventToolBundle\Database\SyncMember;

final class CsvMemberReader
{
    public const string DELIMITER = '$';

    /**
     * Reads a CSV stream and yields a CsvMemberDto for every valid line.
     *
     * Blank lines and lines without a positive member id (column 0) are skipped.
     *
     * @param resource $stream         An open, readable stream resource
     * @param string   $defaultLocale  Locale to use when the CSV field is empty (e.g. 'de')
     * @param string   $defaultCountry Country code to use when the CSV field is empty (e.g. 'CH')
     *
     * @return \Generator<CsvMemberDto>
     *
     * @throws \InvalidArgumentException When $stream is not a valid resource
     */
    public function readStream($stream, string $defaultLocale, string $defaultCountry): \Generator
    {
        if (!\is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a valid resource.');
        }

        while (!feof($stream)) {
            // Pass an explicit $escape ('' disables escaping) — omitting it is deprecated since PHP 8.4.
            $line = fgetcsv($stream, null, self::DELIMITER, '"', '');

            if (false === $line) {
                continue;
            }

            if (empty($line) || empty($line[0])) {
                continue;
            }

            if ((int) $line[0] < 1) {
                continue;
            }

            yield CsvMemberDto::fromCsv(
                line: $line,
                defaultCountry: $defaultCountry,
                defaultLocale: $defaultLocale,
            );
        }
    }
}
