<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;

/**
 * Class TlUserRole.
 */
class TlUserRole extends Backend
{
    /**
     * Return the paste button.
     *
     * @param array      $row
     * @param string     $table
     * @param bool       $cr
     * @param array|bool $arrClipboard
     *
     * @return string
     */
    public function pasteTag(DataContainer $dc, $row, $table, $cr, $arrClipboard = false)
    {
        $imagePasteAfter = Image::getHtml('pasteafter.gif', sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteafter'][1], $row['id']));
        $imagePasteInto = Image::getHtml('pasteinto.gif', sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteinto'][1], $row['id']));

        if (0 === (int) $row['id']) {
            return $cr ? Image::getHtml('pasteinto_.gif').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&mode=2&pid='.$row['id'].'&id='.$arrClipboard['id']).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteinto'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ';
        }

        return ('cut' === $arrClipboard['mode'] && (int) $arrClipboard['id'] === (int) $row['id']) || $cr ? Image::getHtml('pasteafter_.gif').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&mode=1&pid='.$row['id'].'&id='.$arrClipboard['id']).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteafter'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ';
    }

    /**
     * Add the not used label to each record, if the user role could not be found in tl_user.
     *
     * @param array  $row
     * @param string $label
     * @param array  $args
     *
     * @return array
     */
    public function checkForUsage($row, $label, DataContainer $dc, $args)
    {
        $arrRoles = [];
        $objDb = $this->Database->execute('SELECT * FROM tl_user');

        if ($objDb->numRows) {
            $dataRecords = $objDb->fetchEach('userRole');

            foreach ($dataRecords as $record) {
                $arrRecord = StringUtil::deserialize($record, true);
                $arrRoles = array_merge($arrRecord, $arrRoles);
            }
        }

        $arrRoles = array_values(array_unique($arrRoles));

        $blnUsed = \in_array($row['id'], $arrRoles, false) ? true : false;
        $style = !$blnUsed ? ' title="Derzeit nicht in Gebrauch" style="color:red"' : '';

        return sprintf('<span%s>%s</span> <span style="color:gray">%s</span>', $style, $row['title'], $row['email']);
    }
}
