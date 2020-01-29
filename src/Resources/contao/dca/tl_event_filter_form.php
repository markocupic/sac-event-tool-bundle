<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

$GLOBALS['TL_DCA']['tl_event_filter_form'] = [
    'fields' => [
        'year'       => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['year'],
            'inputType' => 'select',
            'options'   => range(2016, (int) Date::parse('Y') + 1),
            'eval'      => ['includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['blankOptionLabel']],

        ],
        'dateStart'  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['dateStart'],
            'inputType' => 'text',
            'eval'      => ['placeholder' => 'yyyy-mm-dd', 'maxlength' => 12],
        ],
        'tourType'   => [
            'label'            => &$GLOBALS['TL_LANG']['tl_event_filter_form']['tourType'],
            'inputType'        => 'select',
            'options_callback' => ['tl_event_filter_form', 'getTourTypes'],
            'eval'             => ['includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['showAll']],

        ],
        'courseType' => [
            'label'            => &$GLOBALS['TL_LANG']['tl_event_filter_form']['courseType'],
            'inputType'        => 'select',
            'options_callback' => ['tl_event_filter_form', 'getCourseTypes'],
            'eval'             => ['includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['showAll']],

        ],
        'organizers' => [
            'label'            => &$GLOBALS['TL_LANG']['tl_event_filter_form']['organizers'],
            'inputType'        => 'select',
            'options_callback' => ['tl_event_filter_form', 'getOrganizers'],
            'eval'             => ['multiple' => true],
        ],
        'searchterm' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['searchterm'],
            'inputType' => 'text',
            'eval'      => ['placeholder' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['enterSearchTerms']],
        ],
        'eventId'    => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['eventId'],
            'inputType' => 'text',
            'eval'      => ['placeholder' => Contao\Date::parse('Y') . '-****'],
        ],
        'courseId'   => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['courseId'],
            'inputType' => 'text',
            'eval'      => ['placeholder' => $GLOBALS['TL_LANG']['tl_event_filter_form']['courseId'][0]],
        ],
    ],
];



