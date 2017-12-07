<?php

/**
 * Class tl_event_release_level_policy
 */
class tl_event_release_level_policy extends Backend
{
    /**
     * List a style sheet
     *
     * @param array $row
     *
     * @return string
     */
    public function listReleaseLevels($row)
    {
        return '<div class="tl_content_left"><span class="level">Stufe: ' . $row['level'] . '</span> ' . $row['title'] . "</div>\n";
    }

}