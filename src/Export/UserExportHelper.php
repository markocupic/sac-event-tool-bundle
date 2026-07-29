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

namespace Markocupic\SacEventToolBundle\Export;

use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\MemberGroupModel;
use Contao\StringUtil;
use Contao\UserGroupModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Download\CsvDownload;
use Markocupic\SacEventToolBundle\Model\UserRoleModel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserExportHelper
{
    private array $roleNameCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAvailableUserRoles(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT id,title FROM tl_user_role ORDER BY sorting');
    }

    public function getAvailableUserGroups(): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT id,name FROM tl_user_group WHERE disable = 0 AND (start = "" OR start <= ?) AND (stop = "" OR stop > ?) ORDER BY name',
            [
                $this->getCurrentTime(),
                $this->getCurrentTime(),
            ],
            [
                Types::INTEGER,
                Types::INTEGER,
            ],
        );
    }

    public function getAvailableMemberGroups(): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT id,name FROM tl_member_group WHERE disable = 0 AND (start = "" OR start <= ?) AND (stop = "" OR stop > ?) ORDER BY name',
            [
                $this->getCurrentTime(),
                $this->getCurrentTime(),
            ],
            [
                Types::INTEGER,
                Types::INTEGER,
            ],
        );
    }

    public function getHeadline(array $columns, string $tableName): array
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $controllerAdapter->loadLanguageFile($tableName);

        $headline = [];

        foreach ($columns as $columnName) {
            $headline[] = match ($columnName) {
                'id' => 'ID',
                default => $this->getTranslatedHeadline(tableName: $tableName, columnName: $columnName),
            };
        }

        return $headline;
    }

    public function getFormattedFieldValue(string $columnName, string $tableName, array $record): string
    {
        if (!\array_key_exists($columnName, $record)) {
            throw new \Exception('Field '.$columnName.' does not exist in table '.$tableName);
        }

        if ('password' === $columnName) {
            return '#######';
        }

        if ('lastLogin' === $columnName) {
            return empty($record['lastLogin'])
                ? ''
                : $this->framework->getAdapter(Date::class)->parse('Y-m-d', $record['lastLogin']);
        }

        if ('leiterQualifikation' === $columnName) {
            $this->framework->getAdapter(Controller::class)->loadLanguageFile($tableName);
            $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
            $qualifications = $stringUtilAdapter->deserialize($record[$columnName] ?? '', true);
            $qualifications = array_map(fn ($item) => $this->translator->trans($tableName.'.refLeiterQualifikation.'.((int) $item), [], 'contao_default'), $qualifications);

            return implode(', ', $qualifications);
        }

        if ('rescissionCause' === $columnName && '' !== $record['rescissionCause']) {
            return $this->translator->trans('tl_user.rescissionCauseOptions.'.$record[$columnName], [], 'contao_default');
        }

        return (string) $record[$columnName];
    }

    /**
     * IMPORTANT: The filter column ($filterKey, i.e., the roles/groups column) MUST be the
     * last entry in $columns.
     *
     * When $keepRolesInOneLine is false, one record is written per role while iterating over
     * $columns. Any column that comes AFTER $filterKey has not been appended to the record at
     * that point and would therefore be missing from the exported rows. So $filterKey has to
     * be positioned last.
     */
    public function exportTable(string $exportType, string $tableName, array $columns, string $filterKey, array $filterRoles, Result $dbalResult, string $filterModelFQCN, bool $keepRolesInOneLine = false): StreamedResponse
    {
        $records = $this->buildRecords(
            rows: $this->iterateRows(dbalResult: $dbalResult),
            tableName: $tableName,
            columns: $columns,
            filterKey: $filterKey,
            filterRoles: $filterRoles,
            filterModelFQCN: $filterModelFQCN,
            keepRolesInOneLine: $keepRolesInOneLine,
        );

        $filename = \sprintf('%s_%s.csv', $exportType, $this->framework->getAdapter(Date::class)->parse('Y-m-d_H-i-s'));

        // Download data as a CSV spreadsheet
        return $this->sendToBrowser(records: $records, filename: $filename);
    }

    /**
     * Builds the CSV record matrix (headline + data rows) from the given rows.
     *
     * This is the pure, side effect-free part of the export: it takes an iterable of
     * associative row arrays and returns the finished record matrix. No DBAL Result, no HTTP
     * response, and no output are involved, which makes it fully unit-testable.
     */
    public function buildRecords(iterable $rows, string $tableName, array $columns, string $filterKey, array $filterRoles, string $filterModelFQCN, bool $keepRolesInOneLine = false): array
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var UserRoleModel|MemberGroupModel|UserGroupModel $filterModelAdapter */
        $filterModelAdapter = $this->framework->getAdapter($filterModelFQCN);

        // Filter by user role
        $hasUserRoleFilter = !empty($filterRoles);

        $records = [];

        // Write headline
        $records[] = $this->getHeadline(columns: $columns, tableName: $tableName);

        // Write rows
        foreach ($rows as $rowUser) {
            // Filter by user role
            if ($hasUserRoleFilter) {
                $userRoles = $stringUtilAdapter->deserialize($rowUser[$filterKey], true);

                if (\count(array_intersect($filterRoles, $userRoles)) < 1) {
                    continue;
                }
            }

            $record = [];
            $hasWrittenRecords = false;

            foreach ($columns as $columnName) {
                if ($columnName !== $filterKey) {
                    $record[] = $this->getFormattedFieldValue(columnName: $columnName, tableName: $tableName, record: $rowUser);
                } else {
                    $rolesUser = $stringUtilAdapter->deserialize($rowUser[$columnName], true);
                    $rolesUser = array_map('intval', $rolesUser);

                    if (empty($rolesUser)) {
                        $record[] = '';
                    } else {
                        // Write all groups/roles into a single line
                        if ($keepRolesInOneLine) {
                            $record[] = implode(
                                ', ',
                                array_filter(
                                    array_map(
                                        function ($roleId) use ($filterModelAdapter) {
                                            // Handle different model types
                                            return $this->getRoleName(roleId: $roleId, filterModelAdapter: $filterModelAdapter);
                                        },
                                        $rolesUser,
                                    ),
                                ),
                            );
                        } else {
                            // Create a row for each group/role.
                            // NOTE: This relies on $filterKey being the LAST column (see method
                            // doc block) - the record is flushed here before any later columns
                            // could be appended.
                            foreach ($rolesUser as $roleId) {
                                if ($hasUserRoleFilter && \count($filterRoles) > 0) {
                                    if (!\in_array($roleId, $filterRoles, false)) {
                                        continue;
                                    }
                                }

                                // Handle different model types
                                $record[] = $this->getRoleName(roleId: $roleId, filterModelAdapter: $filterModelAdapter);

                                $records[] = $record;
                                $hasWrittenRecords = true;

                                array_pop($record);
                            }
                        }
                    }
                }
            }

            if (!$hasWrittenRecords) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Thin I/O wrapper around the (unmockable, final) DBAL Result so that buildRecords() can
     * work on a plain iterable.
     */
    private function iterateRows(Result $dbalResult): \Generator
    {
        while (false !== ($rowUser = $dbalResult->fetchAssociative())) {
            yield $rowUser;
        }
    }

    private function sendToBrowser(array $records, string $filename): StreamedResponse
    {
        $finalRecords = [];

        foreach ($records as $record) {
            $finalRecord = array_map(
                fn ($v) => $this->framework->getAdapter(StringUtil::class)->revertInputEncoding((string) $v),
                $record,
            );
            $finalRecords[] = $finalRecord;
        }

        $csv = $this->createCsvDownloadInstance();
        $csv->setOutputBOM(CsvDownload::BOM_UTF8);
        $csv->setRecords($finalRecords);

        return $csv->createResponse($filename);
    }

    private function createCsvDownloadInstance(): CsvDownload
    {
        return new CsvDownload();
    }

    private function getTranslatedHeadline(string $tableName, string $columnName): string
    {
        $key = \sprintf('%s.%s.0', $tableName, $columnName);
        $translated = $this->translator->trans($key, [], 'contao_default');

        // Fallback
        if ($translated === $key) {
            return ucfirst($columnName);
        }

        return $translated;
    }

    private function getRoleName(int $roleId, Adapter $filterModelAdapter): string
    {
        if (!isset($this->roleNameCache[$roleId])) {
            $roleModel = $filterModelAdapter->findById($roleId);

            $this->roleNameCache[$roleId] = match (true) {
                ($roleModel instanceof UserRoleModel) => $roleModel->title,
                ($roleModel instanceof MemberGroupModel) => $roleModel->name,
                ($roleModel instanceof UserGroupModel) => $roleModel->name,
                default => 'Unbekannte Gruppe/Rolle mit ID: '.$roleId,
            };
        }

        return $this->roleNameCache[$roleId];
    }

    private function getCurrentTime(): int
    {
        return time();
    }
}
