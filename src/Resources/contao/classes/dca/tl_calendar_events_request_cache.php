<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Class tl_calendar_sac_event_tool
 */
class tl_calendar_events_request_cache extends Backend
{
    /**
     * tl_calendar_events_request_cache constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     *
     */
    public static function deleteRequestCache()
    {
        mail('m.cupic@gmx.ch', 'sdfsd', 'sdfsfd');
        Contao\Database::getInstance()->execute('DELETE FROM tl_calendar_events_request_cache');
    }
}