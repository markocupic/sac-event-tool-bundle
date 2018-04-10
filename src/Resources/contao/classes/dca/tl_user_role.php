<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */


/**
 * Class tl_user_role
 */
class tl_user_role extends Backend
{

    /**
     * Return the paste button
     *
     * @param \DataContainer $dc
     * @param array          $row
     * @param string         $table
     * @param boolean        $cr
     * @param array|bool     $arrClipboard
     *
     * @return string
     */
    public function pasteTag(DataContainer $dc, $row, $table, $cr, $arrClipboard=false)
    {
        $imagePasteAfter = \Image::getHtml('pasteafter.gif', sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteafter'][1], $row['id']));
        $imagePasteInto = \Image::getHtml('pasteinto.gif', sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteinto'][1], $row['id']));

        if ($row['id'] == 0) {
            return $cr ? \Image::getHtml('pasteinto_.gif').' ' : '<a href="'.\Backend::addToUrl('act='.$arrClipboard['mode'].'&mode=2&pid='.$row['id'].'&id='.$arrClipboard['id']).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteinto'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ';
        }

        return (('cut' === $arrClipboard['mode'] && $arrClipboard['id'] == $row['id']) || $cr) ? \Image::getHtml('pasteafter_.gif').' ' : '<a href="'.\Backend::addToUrl('act='. $arrClipboard['mode'].'&mode=1&pid='. $row['id'].'&id='. $arrClipboard['id']).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteafter'][1], $row['id'])).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ';
    }
}