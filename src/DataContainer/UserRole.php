<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Image;
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
     * Return the paste button.
     *
     * @Callback(table="tl_user_role", target="list.sorting.paste_button")
     *
     * @param array|bool $arrClipboard
     */
    public function pasteButton(DataContainer $dc, array $row, string $table, bool $cr, $arrClipboard = false): string
    {
        $imagePasteAfter = Image::getHtml('pasteafter.gif', $this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default'));
        $imagePasteInto = Image::getHtml('pasteinto.gif', $this->translator->trans('DCA.pasteinto.1', [$row['id']], 'contao_default'));

        if (0 === (int) $row['id']) {
            return $cr ? Image::getHtml('pasteinto_.gif').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&mode=2&pid='.$row['id'].'&id='.$arrClipboard['id']).'" title="'.StringUtil::specialchars($this->translator->trans('DCA.pasteinto.1', [$row['id']], 'contao_default')).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ';
        }

        return ('cut' === $arrClipboard['mode'] && (int) $arrClipboard['id'] === (int) $row['id']) || $cr ? Image::getHtml('pasteafter_.gif').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&mode=1&pid='.$row['id'].'&id='.$arrClipboard['id']).'" title="'.StringUtil::specialchars($this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default')).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ';
    }

    /**
     * Add the not "role currently vacant" label to each record,
     * if the user role could not be found in tl_user.
     *
     * @Callback(table="tl_user_role", target="list.label.label")
     *
     * @throws Exception
     */
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
