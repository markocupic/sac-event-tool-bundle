<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class Util
{
    public function __construct(
        private RequestStack $requestStack,
        private Connection $connection,
    ) {
    }

    public function listSacSections(): array
    {
        return $this->connection
            ->fetchAllKeyValue('SELECT sectionId, name FROM tl_sac_section ORDER BY sectionId ASC')
            ;
    }

    /**
     * Display the section name instead of the section id
     * 4250,4252 becomes SAC PILATUS, SAC PILATUS NAPF.
     */
    public function decryptSectionIds(array $data, array $row, DataContainer $dc, string $strTable): array
    {
        if (isset($data[$strTable]) && \is_array($data[$strTable])) {
            foreach (array_keys($data[$strTable]) as $k) {
                if (isset($data[$strTable][$k]) && \is_array($data[$strTable][$k])) {
                    foreach (array_keys($data[$strTable][0]) as $kk) {
                        if (str_contains($kk, '<small>sectionId</small>')) {
                            if (isset($row['sectionId'])) {
                                $arrSections = StringUtil::deserialize($row['sectionId'], true);
                                $arrSectionNames = [];

                                foreach ($arrSections as $id) {
                                    $result = $this->connection->fetchOne('SELECT name FROM tl_sac_section WHERE sectionId = ?', [$id]);
                                    $arrSectionNames[] = $result ?: $id;
                                }

                                $data[$strTable][$k][$kk] = implode(', ', $arrSectionNames);
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }
}
