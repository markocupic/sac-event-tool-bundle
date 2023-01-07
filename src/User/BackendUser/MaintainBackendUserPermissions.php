<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\User\BackendUser;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class MaintainBackendUserPermissions
{
    private ContaoFramework $framework;
    private Connection $connection;
    private string $sacevtUserBackendHomeDir;

    private Adapter $stringUtil;
    private Adapter $date;

    public function __construct(ContaoFramework $framework, Connection $connection, string $sacevtUserBackendHomeDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->sacevtUserBackendHomeDir = $sacevtUserBackendHomeDir;

        // Adapters
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->date = $this->framework->getAdapter(Date::class);
    }

    /**
     * @throws Exception
     */
    public function resetBackendUserPermissions(string $strUserIdentifier, array $arrSkip = [], bool $mergeGroupRights = false): void
    {
        // Initialize contao framework
        $this->framework->initialize();

        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist('tl_user')) {
            return;
        }

        $columns = $schemaManager->listTableColumns('tl_user');

        $arrUserProps = $this->connection
            ->fetchAssociative(
                'SELECT * FROM tl_user WHERE username = ?',
                [$strUserIdentifier],
            )
        ;

        if (false !== $arrUserProps) {
            // Contao core permissions
            $arrInherit = ['modules', 'themes', 'elements', 'fields', 'pagemounts', 'alpty', 'filemounts', 'fop', 'forms', 'formp', 'imageSizes', 'amg'];

            // Custom permissions like: faqs,faqp,news,newp,newsfeeds,newsfeedp,calendars,calendarp,calendarfeeds,calendarfeedp,newsletters,newsletterp,calendar_containers,calendar_containerp
            if (!empty($GLOBALS['TL_PERMISSIONS']) && \is_array($GLOBALS['TL_PERMISSIONS'])) {
                $arrInherit = array_merge($arrInherit, $GLOBALS['TL_PERMISSIONS']);
            }

            $arrInherit = array_diff($arrInherit, $arrSkip);
            $arrInherit = array_unique($arrInherit);

            if (!empty($arrInherit)) {
                $arrInheritNew = [];

                foreach ($arrInherit as $field) {
                    if (!isset($columns[strtolower($field)])) {
                        continue;
                    }

                    if ('filemounts' === $field) {
                        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

                        // Set users home directory, if there is one
                        $objFolder = $filesModelAdapter->findByPath($this->sacevtUserBackendHomeDir.'/'.$arrUserProps['id']);

                        if (null !== $objFolder) {
                            $permNew = [$objFolder->uuid];
                        } else {
                            // empty array
                            $permNew = [];
                        }
                    } else {
                        // empty array
                        $permNew = [];
                    }

                    $arrInheritNew[$field] = $permNew;
                }

                if ($mergeGroupRights) {
                    $groups = $this->stringUtil->deserialize($arrUserProps['groups'], true);

                    foreach ($groups as $id) {
                        $time = $this->date->floorToMinute();

                        $arrGroup = $this->connection
                            ->fetchAssociative(
                                "SELECT * FROM tl_user_group WHERE id = ? AND disable != '1' AND (start = '' OR start <= '$time') AND (stop='' OR stop > '$time')",
                                [$id],
                            )
                        ;

                        if (false !== $arrGroup) {
                            foreach (array_keys($arrGroup) as $field) {
                                if (!\in_array($field, $arrInherit, true)) {
                                    continue;
                                }

                                $value = $this->stringUtil->deserialize($arrGroup[$field], true);

                                // The page/file picker can return integers instead of arrays, so use empty() instead of is_array() and StringUtil::deserialize(true) here
                                if (!empty($value)) {
                                    $arrInheritNew[$field] = array_merge((\is_array($arrInheritNew[$field]) ? $arrInheritNew[$field] : ($arrInheritNew[$field] ? [$arrInheritNew[$field]] : [])), $value);
                                    $arrInheritNew[$field] = array_unique($arrInheritNew[$field]);
                                }
                            }
                        }
                    }
                }

                $set = array_map('serialize', $arrInheritNew);

                if (!empty($set)) {
                    $set['tstamp'] = time();
                    $this->connection->update('tl_user', $set, ['username' => $strUserIdentifier]);
                }
            }
        }
    }
}
