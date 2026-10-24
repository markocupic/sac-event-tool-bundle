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
 * The year and abo filter of the back end list view are applied, so the export contains
 * exactly the entries the user sees in the list.
 * First name and last name are taken from tl_member (joined by sacMemberId).
 */
final readonly class AboSurveyCsvExport
{
    public const array FIELDS = [
        'year',
        'aboType',
        'sacMemberId',
        'firstname',
        'lastname',
        'email',
        'street',
        'postal',
        'city',
        'dateAdded',
    ];

    public function __construct(
        private Connection $connection,
        private TranslatorInterface $translator,
    ) {
    }

    public function generate(int|null $year = null, string|null $aboType = null): Response
    {
        $csv = new CsvDownload();
        $csv->setOutputBOM(CsvDownload::BOM_UTF8);
        $csv->setHeadline($this->buildHeadline());

        foreach ($this->fetchEntries($year, $aboType) as $entry) {
            $csv->addRecord($this->buildRecord($entry));
        }

        $filename = \sprintf('abo_umfrage_%s_%s.csv', $year ?? 'alle', date('Y-m-d'));

        return $csv->createResponse($filename);
    }

    private function fetchEntries(int|null $year, string|null $aboType): array
    {
        $sql = 'SELECT s.*, m.firstname, m.lastname FROM '.AboSurveyRepository::TABLE.' s LEFT JOIN tl_member m ON m.sacMemberId = s.sacMemberId WHERE 1 = 1';
        $params = [];

        if (null !== $year) {
            $sql .= ' AND s.year = ?';
            $params[] = $year;
        }

        if (null !== $aboType) {
            $sql .= ' AND s.aboType = ?';
            $params[] = $aboType;
        }

        $sql .= ' ORDER BY s.year DESC, m.lastname, m.firstname, s.sacMemberId';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    private function buildHeadline(): array
    {
        $headline = [];

        foreach (self::FIELDS as $field) {
            $headline[] = match ($field) {
                'firstname', 'lastname' => $this->translator->trans('tl_member.'.$field.'.0', [], 'contao_tl_member'),
                default => $this->translator->trans('tl_abo_survey.'.$field.'.0', [], 'contao_tl_abo_survey'),
            };
        }

        return $headline;
    }

    private function buildRecord(array $entry): array
    {
        $record = [];

        foreach (self::FIELDS as $field) {
            $record[] = match ($field) {
                'aboType' => $this->translator->trans('tl_abo_survey.'.$entry['aboType'], [], 'contao_tl_abo_survey'),
                'dateAdded' => empty($entry['dateAdded']) ? '' : date('d.m.Y H:i', (int) $entry['dateAdded']),
                default => (string) ($entry[$field] ?? ''),
            };
        }

        return $record;
    }
}
