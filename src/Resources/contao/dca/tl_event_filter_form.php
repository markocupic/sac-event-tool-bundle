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

use Contao\Date;
use Markocupic\SacEventToolBundle\Dca\TlEventFilterForm;

$GLOBALS['TL_DCA']['tl_event_filter_form'] = [
    'fields' => [
        'year'                 => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['year'],
            'inputType' => 'select',
            'options'   => range(
                2016,
                (int)Date::parse(
                    'Y'
                ) + 1
            ),
            'eval'      => [
                'includeBlankOption' => true,
                'blankOptionLabel'   => &$GLOBALS['TL_LANG']['tl_event_filter_form']['blankOptionLabel'],
            ],
        ],
        'dateStart'            => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['dateStart'],
            'inputType' => 'text',
            'eval'      => [
                'placeholder' => 'yyyy-mm-dd',
                'maxlength'   => 12,
            ],
        ],
        'tourType'             => [
            'label'            => &$GLOBALS['TL_LANG']['tl_event_filter_form']['tourType'],
            'inputType'        => 'select',
            'options_callback' => [
                TlEventFilterForm::class,
                'getTourTypes',
            ],
            'eval'             => [
                'includeBlankOption' => true,
                'blankOptionLabel'   => &$GLOBALS['TL_LANG']['tl_event_filter_form']['showAll'],
            ],
        ],
        'courseType'           => [
            'label'            => &$GLOBALS['TL_LANG']['tl_event_filter_form']['courseType'],
            'inputType'        => 'select',
            'options_callback' => [
                TlEventFilterForm::class,
                'getCourseTypes',
            ],
            'eval'             => [
                'includeBlankOption' => true,
                'blankOptionLabel'   => &$GLOBALS['TL_LANG']['tl_event_filter_form']['showAll'],
            ],
        ],
        'organizers'           => [
            'label'            => &$GLOBALS['TL_LANG']['tl_event_filter_form']['organizers'],
            'inputType'        => 'select',
            'options_callback' => [
                TlEventFilterForm::class,
                'getOrganizers',
            ],
            'eval'             => ['multiple' => true],
        ],
        'textsearch'           => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['textsearch'],
            'inputType' => 'text',
            'eval'      => ['placeholder' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['enterSearchTerms']],
        ],
        'eventId'              => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['eventId'],
            'inputType' => 'text',
            'eval'      => [
                'placeholder' => Date::parse(
                        'Y'
                    ).'-****',
            ],
        ],
        'courseId'             => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['courseId'],
            'inputType' => 'text',
            'eval'      => ['placeholder' => $GLOBALS['TL_LANG']['tl_event_filter_form']['courseId'][0]],
        ],
        'suitableForBeginners' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['suitableForBeginners'],
            'inputType' => 'checkbox',
        ],
    ],
];
