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

namespace Markocupic\SacEventToolBundle\Feature\AboSurvey;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Download\CsvDownload;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Exports the entries of the abo survey (tl_abo_survey) as a CSV file (UTF-8 with BOM, ";").
 * Always all entries are exported (filters of the back end list view are not applied).
 */
final readonly class AboSurveyCsvExport
{
    public const array FIELDS = [
        'year',
        'eventOrganizer',
        'previousAboType',
        'aboType',
        'sacMemberId',
        'firstname',
        'lastname',
        'email',
        'street',
        'postal',
        'city',
        'notice',
        'dateAdded',
    ];

    public function __construct(
        private Connection $connection,
        private TranslatorInterface $translator,
    ) {
    }

    public function generate(): Response
    {
        $csv = new CsvDownload();
        $csv->setOutputBOM(CsvDownload::BOM_UTF8);
        $csv->setHeadline($this->buildHeadline());

        foreach ($this->fetchEntries() as $entry) {
            $csv->addRecord($this->buildRecord($entry));
        }

        $filename = \sprintf('abo_umfrage_%s.csv', date('Y-m-d'));

        return $csv->createResponse($filename);
    }

    private function fetchEntries(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM '.AboSurveyRepository::TABLE.' ORDER BY year DESC, lastname, firstname, sacMemberId',
        );
    }

    private function buildHeadline(): array
    {
        $headline = [];

        foreach (self::FIELDS as $field) {
            $headline[] = $this->translator->trans('tl_abo_survey.'.$field.'.0', [], 'contao_tl_abo_survey');
        }

        return $headline;
    }

    private function buildRecord(array $entry): array
    {
        $record = [];

        foreach (self::FIELDS as $field) {
            $record[] = match ($field) {
                'previousAboType', 'aboType' => empty($entry[$field]) ? '' : $this->translator->trans('tl_abo_survey.'.$entry[$field], [], 'contao_tl_abo_survey'),
                'dateAdded' => empty($entry['dateAdded']) ? '' : date('d.m.Y H:i', (int) $entry['dateAdded']),
                'notice' => $this->removeLineBreaks((string) ($entry['notice'] ?? '')),
                default => (string) ($entry[$field] ?? ''),
            };
        }

        return $record;
    }

    /**
     * One line per record: line breaks (\n, \r\n, \r) are replaced by a single space;
     * several consecutive line breaks (incl. the spaces around them) become one space.
     */
    private function removeLineBreaks(string $text): string
    {
        return trim(preg_replace('/\h*(?:\R\h*)+/u', ' ', $text) ?? $text);
    }
}
