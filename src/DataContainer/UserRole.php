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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserRole
{
    private Connection $connection;
    private TranslatorInterface $translator;

    public function __construct(Connection $connection, TranslatorInterface $translator)
    {
        $this->connection = $connection;
        $this->translator = $translator;
    }

    /**
     * Add the not "role currently vacant" label to each record,
     * if the user role could not be found in tl_user.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_user_role', target: 'list.label.label', priority: 100)]
    public function checkForUsage(array $row, string $label, DataContainer $dc, string $args): string
    {
        $arrRoles = [];

        $arrUserRoles = $this->connection->fetchFirstColumn('SELECT userRole FROM tl_user', []);

        if (!empty($arrUserRoles)) {
            foreach ($arrUserRoles as $roles) {
                $arrRecord = StringUtil::deserialize($roles, true);
                $arrRoles = array_merge($arrRecord, $arrRoles);
            }
        }

        $arrRoles = array_values(array_unique($arrRoles));

        $blnUsed = \in_array($row['id'], $arrRoles, false);

        $msg = $this->translator->trans('MSC.roleCurrentlyVacant', [], 'contao_default');

        $style = !$blnUsed ? sprintf(' title="%s" style="color:red"', htmlspecialchars($msg)) : '';

        return sprintf('<span%s>%s</span> <span style="color:gray">%s</span>', $style, $row['title'], $row['email']);
    }
}
