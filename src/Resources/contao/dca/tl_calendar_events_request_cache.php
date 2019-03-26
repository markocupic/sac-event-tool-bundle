<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$GLOBALS['TL_DCA']['tl_calendar_events_request_cache'] = array
(

    // Config
    'config' => array
    (
        'dataContainer' => 'Table',
        'switchToEdit'  => true,
        'sql'           => array
        (
            'keys' => array
            (
                'id'    => 'primary',
                'input' => 'index'
            )
        )
    ),
    // Fields
    'fields' => array
    (
        'id'     => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp' => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'input'  => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_request_cache']['input'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "text NULL",
        ),
        'value'  => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events_request_cache']['value'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "text NULL",
        )
    )
);
