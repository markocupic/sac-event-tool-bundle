<?php

/**
 * Class tl_tour_difficulty
 */
class tl_tour_difficulty extends Backend
{

    /**
     * List a style sheet
     *
     * @param array $row
     *
     * @return string
     */
    public function listDifficulties($row)
    {
        return '<div class="tl_content_left"><span class="level">' . $row['title'] . '</span> ' . $row['shortcut'] . "</div>\n";
    }
}