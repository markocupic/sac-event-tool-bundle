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
     * @param resource $stream
     *
     * @return \Generator<CsvMemberDto>
     */
    public function readStream($stream, string $defaultLocale, string $defaultCountry): \Generator
    {
        if (!\is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a valid resource.');
        }

        while (!feof($stream)) {
            $line = fgetcsv($stream, null, self::DELIMITER);

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
