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

$GLOBALS['TL_DCA']['tl_event_filter_form'] = [
    'fields' => [
        'year'                 => [
            'inputType' => 'select',
            'options'   => range(2016, (int)date('Y') + 1),
            'eval'      => ['includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['blankOptionLabel']],
        ],
        'dateStart'            => [
            'inputType' => 'text',
            'eval'      => ['placeholder' => 'yyyy-mm-dd', 'maxlength' => 12],
        ],
        'tourType'             => [
            'inputType'  => 'select',
            'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
            'foreignKey' => 'tl_tour_type.title',
            'eval'       => ['includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['showAll']],
        ],
        'courseType'           => [
            'inputType' => 'select',
            'eval'      => ['includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['showAll']],
        ],
        'organizers'           => [
            'inputType' => 'select',
            'eval'      => ['multiple' => true],
        ],
        'textsearch'           => [
            'inputType' => 'text',
            'eval'      => ['placeholder' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['enterSearchTerms']],
        ],
        'eventId'              => [
            'inputType' => 'text',
            'eval'      => ['placeholder' => date('Y').'-****'],
        ],
        'courseId'             => [
            'inputType' => 'text',
            'eval'      => ['placeholder' => $GLOBALS['TL_LANG']['tl_event_filter_form']['courseId'][0]],
        ],
        'suitableForBeginners' => [
            'inputType' => 'checkbox',
        ],
    ],
];
