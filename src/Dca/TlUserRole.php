<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\DataContainer;
use Contao\Image;
use Contao\Backend;
use Contao\StringUtil;

/**
 * Class TlUserRole
 * @package Markocupic\SacEventToolBundle\Dca
 */
class TlUserRole extends Backend
{

    /**
     * Return the paste button
     *
     * @param DataContainer $dc
     * @param array $row
     * @param string $table
     * @param boolean $cr
     * @param array|bool $arrClipboard
     *
     * @return string
     */
    public function pasteTag(DataContainer $dc, $row, $table, $cr, $arrClipboard = false)
    {
        $imagePasteAfter = Image::getHtml('pasteafter.gif', sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteafter'][1], $row['id']));
        $imagePasteInto = Image::getHtml('pasteinto.gif', sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteinto'][1], $row['id']));

        if ((int)$row['id'] === 0) {
            return $cr ? Image::getHtml('pasteinto_.gif').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&mode=2&pid='.$row['id'].'&id='.$arrClipboard['id']).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteinto'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ';
        }

        return (('cut' === $arrClipboard['mode'] && (int)$arrClipboard['id'] === (int)$row['id']) || $cr) ? Image::getHtml('pasteafter_.gif').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&mode=1&pid='.$row['id'].'&id='.$arrClipboard['id']).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteafter'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ';
    }

    /**
     * Add the not used label to each record, if the user role could not be found in tl_user
     * @param array $row
     * @param string $label
     * @param DataContainer $dc
     * @param array $args
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

        $blnUsed = in_array($row['id'], $arrRoles, false) ? true : false;
        $style = !$blnUsed ? ' title="Derzeit nicht in Gebrauch" style="color:red"' : '';

        return sprintf('<span%s>%s</span> <span style="color:gray">%s</span>', $style, $row['title'], $row['email']);
    }
}