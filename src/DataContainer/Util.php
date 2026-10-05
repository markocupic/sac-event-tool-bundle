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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

readonly class Util
{
    public function __construct(
        private Connection $connection,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @throws Exception
     */
    public function listSacSections(): array
    {
        return $this->connection
            ->fetchAllKeyValue('SELECT sectionId, name FROM tl_sac_section ORDER BY sectionId ASC')
        ;
    }

    /**
     * Non-admins may neither create, copy nor delete records and only see the
     * fields they are allowed to edit.
     */
    public function restrictDcaForNonAdmins(string $table): void
    {
        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return;
        }

        $GLOBALS['TL_DCA'][$table]['config']['closed'] = true;
        $GLOBALS['TL_DCA'][$table]['config']['notCopyable'] = true;
        $GLOBALS['TL_DCA'][$table]['config']['notDeletable'] = true;
        unset($GLOBALS['TL_DCA'][$table]['list']['operations']['copy'], $GLOBALS['TL_DCA'][$table]['list']['operations']['delete']);

        foreach (array_keys($GLOBALS['TL_DCA'][$table]['fields']) as $fieldName) {
            if (!$this->authorizationChecker->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, $table.'::'.$fieldName)) {
                $GLOBALS['TL_DCA'][$table]['fields'][$fieldName]['eval']['doNotShow'] = true;
                $GLOBALS['TL_DCA'][$table]['fields'][$fieldName]['sorting'] = false;
                $GLOBALS['TL_DCA'][$table]['fields'][$fieldName]['filter'] = false;
                $GLOBALS['TL_DCA'][$table]['fields'][$fieldName]['search'] = false;
            }
        }
    }

    /**
     * Display the section names instead of the section ids in the "show" view:
     * 4250,4252 becomes SAC PILATUS, SAC PILATUS NAPF.
     *
     * @throws Exception
     */
    public function decryptSectionIds(array $data, array $row, DataContainer $dc, string $table): array
    {
        if (!isset($row['sectionId']) || !\is_array($data[$table][0] ?? null)) {
            return $data;
        }

        $labels = array_filter(
            array_keys($data[$table][0]),
            static fn ($label): bool => str_contains((string) $label, '<small>sectionId</small>'),
        );

        if (empty($labels)) {
            return $data;
        }

        $sections = $this->listSacSections();
        $sectionNames = [];

        foreach (StringUtil::deserialize($row['sectionId'], true) as $sectionId) {
            $sectionNames[] = $sections[$sectionId] ?? '' ?: $sectionId;
        }

        foreach ($data[$table] as $key => $record) {
            if (!\is_array($record)) {
                continue;
            }

            foreach ($labels as $label) {
                $data[$table][$key][$label] = implode(', ', $sectionNames);
            }
        }

        return $data;
    }
}
