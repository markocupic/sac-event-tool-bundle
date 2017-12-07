<?php


/**
 * Class tl_tour_type
 */
class tl_tour_type extends Backend
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